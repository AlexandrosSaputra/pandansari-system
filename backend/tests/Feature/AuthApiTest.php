<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            'email' => $user->email,
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
            'email missing from database' => ['unknown_email'],
            'wrong password' => ['wrong_password'],
            'inactive user' => ['inactive_user'],
            'inactive role' => ['inactive_role'],
            'user without role' => ['no_role'],
            'admin without operational unit' => ['admin_without_unit'],
            'admin with inactive unit' => ['inactive_unit'],
        ];
    }

    #[DataProvider('invalidAccounts')]
    public function test_invalid_or_unusable_account_gets_the_same_generic_401(string $scenario): void
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
            'email' => $scenario === 'unknown_email' ? 'missing@example.com' : $user->email,
            'password' => $scenario === 'wrong_password' ? 'incorrect' : 'password',
        ]);

        $response->assertUnauthorized()->assertExactJson([
            'message' => 'The provided credentials are invalid.',
        ]);
        $this->assertGuest('web');
    }

    public function test_login_validation_returns_422_json(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => ['not', 'a', 'string'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_only_the_auth_resource_with_role_unit_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->createUnit('OUT-A');
        $user = $this->createAccount('admin', $unit, useSeededRole: true);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => true,
                'role' => ['id' => $user->role_id, 'name' => 'Admin', 'slug' => 'admin'],
                'operational_unit' => [
                    'id' => $unit->id,
                    'code' => 'OUT-A',
                    'name' => 'OUT-A',
                    'type' => 'OUTLET',
                    'scanner_enabled' => true,
                ],
                'permissions' => [
                    'dashboard.view',
                    'inventory.view',
                    'transaction.create',
                    'transaction.view',
                    'transfer.respond',
                    'transfer.view',
                ],
            ]]);
    }

    public function test_me_requires_authentication_and_logout_invalidates_the_session(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
        $this->get('/api/auth/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');

        $user = $this->createAccount('warehouse');

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out.']);

        $this->assertGuest('web');
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_is_limited_to_five_attempts_per_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'wrong',
        ])->assertTooManyRequests();
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available_for_the_spa(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
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
