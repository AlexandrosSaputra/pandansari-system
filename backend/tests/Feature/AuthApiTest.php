<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Mail\PasswordResetOtpMail;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function validRoles(): array
    {
        return [
            'admin' => ['admin', true],
            'warehouse' => ['warehouse', false],
            'superadmin' => ['superadmin', false],
        ];
    }

    #[DataProvider('validRoles')]
    public function test_valid_account_logs_in_with_a_stateful_session(string $slug, bool $needsUnit): void
    {
        $unit = $needsUnit ? $this->createUnit('OUT-A') : null;
        $user = $this->createAccount($slug, $unit);

        $response = $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('data.role.slug', $slug);
        $response->assertJsonPath('data.operational_unit.id', $unit?->id);
        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.remember_token');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public static function invalidAccounts(): array
    {
        return [
            'username missing from database' => ['unknown_username'],
            'wrong password' => ['wrong_password'],
            'inactive user' => ['inactive_user'],
            'inactive role' => ['inactive_role'],
            'user without role' => ['no_role'],
            'admin without operational unit' => ['admin_without_unit'],
            'admin with inactive unit' => ['inactive_unit'],
        ];
    }

    #[DataProvider('invalidAccounts')]
    public function test_invalid_or_unusable_account_is_rejected(string $scenario): void
    {
        $unit = $this->createUnit('OUT-A');
        $user = $this->createAccount('admin', $unit);

        if ($scenario === 'inactive_user') {
            $user->forceFill(['is_active' => false])->save();
        } elseif ($scenario === 'inactive_role') {
            $user->role->update(['is_active' => false]);
        } elseif ($scenario === 'no_role') {
            $user->forceFill(['role_id' => null])->save();
        } elseif ($scenario === 'admin_without_unit') {
            $user->forceFill(['operational_unit_id' => null])->save();
        } elseif ($scenario === 'inactive_unit') {
            $unit->update(['is_active' => false]);
        }

        $response = $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $scenario === 'unknown_username' ? 'missing-user' : $user->username,
            'password' => $scenario === 'wrong_password' ? 'incorrect' : 'password',
        ]);

        if (in_array($scenario, ['unknown_username', 'wrong_password'], true)) {
            $response->assertUnauthorized()
                ->assertJsonPath('message', 'Username atau kata sandi tidak sesuai.')
                ->assertJsonPath('errors.password.0', 'Kata sandi salah. Tersisa 2 percobaan sebelum login dikunci.');
        } else {
            $response->assertUnauthorized()->assertExactJson([
                'message' => 'The provided credentials are invalid.',
            ]);
        }
        $this->assertGuest('web');
    }

    public function test_login_validation_returns_422_json(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);

        $this->postJson('/api/auth/login', [
            'username' => 'invalid username!',
            'password' => ['not', 'a', 'string'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_me_returns_only_the_auth_resource_with_role_unit_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->createUnit('OUT-A');
        $user = $this->createAccount('admin', $unit, useSeededRole: true);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role.slug', 'admin')
            ->assertJsonPath('data.operational_unit.id', $unit->id)
            ->assertJsonPath('data.active_assignment.role.slug', 'admin')
            ->assertJsonPath('data.assignments.0.operational_unit.id', $unit->id)
            ->assertJsonPath('data.permissions', [
                'dashboard.view',
                'inventory.view',
                'transaction.create',
                'transaction.view',
                'transfer.respond',
                'transfer.view',
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_multi_role_login_requires_an_assignment_then_can_switch_session_context(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->createUnit('OUT-A');
        $unitB = $this->createUnit('OUT-B');
        $user = User::factory()->create();
        $admin = $user->assignments()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'operational_unit_id' => $unit->id,
        ]);
        $warehouse = $user->assignments()->create([
            'role_id' => Role::where('slug', 'warehouse')->value('id'),
        ]);
        $adminB = $user->assignments()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'operational_unit_id' => $unitB->id,
        ]);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.active_assignment', null)
            ->assertJsonPath('data.role', null)
            ->assertJsonCount(3, 'data.assignments');

        $this->getJson('/api/inventory/warehouse')->assertForbidden();

        $this->postJson('/api/auth/assignment', ['assignment_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('data.role.slug', 'admin')
            ->assertJsonPath('data.operational_unit.id', $unit->id);

        $this->postJson('/api/auth/assignment', ['assignment_id' => $warehouse->id])
            ->assertOk()
            ->assertJsonPath('data.role.slug', 'warehouse')
            ->assertJsonPath('data.operational_unit', null);

        $this->postJson('/api/auth/assignment', ['assignment_id' => $adminB->id])
            ->assertOk()
            ->assertJsonPath('data.role.slug', 'admin')
            ->assertJsonPath('data.operational_unit.id', $unitB->id);
    }

    public function test_session_bootstrap_returns_a_normal_guest_state_and_the_authenticated_user(): void
    {
        $this->getJson('/api/auth/session')
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $user = $this->createAccount('warehouse');

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/auth/session')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_requires_authentication_and_logout_invalidates_the_session(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
        $this->get('/api/auth/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');

        $user = $this->createAccount('warehouse');

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out.']);

        $this->assertGuest('web');
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_three_wrong_passwords_lock_username_until_otp_reset_succeeds(): void
    {
        Mail::fake();
        config(['password-reset.superadmin_email' => 'recovery@example.test']);
        $user = $this->createAccount('warehouse');
        $payload = [
            'username' => $user->username,
            'password' => 'wrong-password',
        ];

        $this->postJson('/api/auth/login', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('errors.password.0', 'Kata sandi salah. Tersisa 2 percobaan sebelum login dikunci.');
        $this->postJson('/api/auth/login', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('errors.password.0', 'Kata sandi salah. Tersisa 1 percobaan sebelum login dikunci.');
        $this->postJson('/api/auth/login', $payload)
            ->assertStatus(423)
            ->assertJsonPath('message', 'Login untuk username ini dikunci setelah tiga kali kata sandi salah. Gunakan Lupa password, minta kode OTP, lalu selesaikan reset kata sandi untuk membuka kembali akun.');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'AUTH_LOGIN_LOCKED',
            'subject_type' => 'User',
            'subject_id' => $user->id,
        ]);

        $this->postJson('/api/auth/password/forgot', [
            'username' => $user->username,
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertStatus(423);

        /** @var PasswordResetOtpMail $mail */
        $mail = Mail::sent(PasswordResetOtpMail::class)->first();

        $this->postJson('/api/auth/password/reset', [
            'username' => $user->username,
            'otp' => $mail->otp,
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertOk();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'username' => $user->username,
                'password' => 'NewStrongPass1234',
            ])->assertOk();
    }

    public function test_successful_login_clears_previous_password_failures(): void
    {
        $user = $this->createAccount('warehouse');
        $wrongCredentials = [
            'username' => $user->username,
            'password' => 'wrong-password',
        ];

        $this->postJson('/api/auth/login', $wrongCredentials)->assertUnauthorized();
        $this->postJson('/api/auth/login', $wrongCredentials)->assertUnauthorized();
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [
                'username' => $user->username,
                'password' => 'password',
            ])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();

        $this->postJson('/api/auth/login', $wrongCredentials)
            ->assertUnauthorized()
            ->assertJsonPath('errors.password.0', 'Kata sandi salah. Tersisa 2 percobaan sebelum login dikunci.');
    }

    public function test_login_endpoint_is_limited_to_five_requests_per_username_and_ip(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
                'username' => 'rate-limit-user',
                'password' => 'wrong',
            ])->assertStatus($attempt < 2 ? 401 : 423);
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'username' => 'rate-limit-user',
                'password' => 'wrong',
            ])->assertStatus(423);
        }

        $this->postJson('/api/auth/login', [
            'username' => 'rate-limit-user',
            'password' => 'wrong',
        ])->assertTooManyRequests();
    }

    #[DataProvider('localSpaOrigins')]
    public function test_sanctum_csrf_cookie_endpoint_is_available_for_the_spa(string $origin): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:*', 'http://127.0.0.1:*'],
            'sanctum.stateful' => ['localhost:*', '127.0.0.1:*'],
        ]);

        $this->withHeader('Origin', $origin)
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public static function localSpaOrigins(): array
    {
        return [
            'localhost default Vite port' => ['http://localhost:5173'],
            'localhost alternate Vite port' => ['http://localhost:5174'],
            'loopback alternate Vite port' => ['http://127.0.0.1:5174'],
        ];
    }

    private function createUnit(string $code): OperationalUnit
    {
        return OperationalUnit::create([
            'code' => $code,
            'name' => $code,
            'type' => OperationalUnitType::Outlet,
            'scanner_enabled' => true,
        ]);
    }

    private function createAccount(string $slug, ?OperationalUnit $unit = null, bool $useSeededRole = false): User
    {
        $role = $useSeededRole
            ? Role::where('slug', $slug)->firstOrFail()
            : Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
        ])->save();

        return $user;
    }
}
