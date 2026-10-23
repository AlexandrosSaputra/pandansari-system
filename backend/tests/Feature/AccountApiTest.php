<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_update_their_profile_without_changing_authorization(): void
    {
        $user = $this->actingAsSystemUser('warehouse');

        $this->patchJson('/api/auth/profile', [
            'name' => 'Nama Baru',
            'username' => 'NAMA-BARU',
            'email' => 'nama.baru@example.test',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.username', 'nama-baru')
            ->assertJsonPath('data.email', 'nama.baru@example.test')
            ->assertJsonPath('data.role.slug', 'warehouse');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'nama-baru',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'ACCOUNT_PROFILE_UPDATED',
            'subject_id' => $user->id,
        ]);
    }

    public function test_profile_update_rejects_another_users_identity(): void
    {
        $user = $this->actingAsSystemUser('warehouse');
        $other = User::factory()->create();

        $this->patchJson('/api/auth/profile', [
            'name' => $user->name,
            'username' => $other->username,
            'email' => $other->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_authenticated_user_can_change_password_with_the_current_password(): void
    {
        $user = $this->actingAsSystemUser('warehouse');

        $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(Hash::check('NewStrongPass1234', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'ACCOUNT_PASSWORD_UPDATED',
        ]);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = $this->actingAsSystemUser('warehouse');

        $this->putJson('/api/auth/password', [
            'current_password' => 'incorrect',
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_user_activity_endpoint_only_returns_the_authenticated_users_logs(): void
    {
        $user = $this->actingAsSystemUser('warehouse');
        $other = User::factory()->create();
        ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'OWN_ACTION',
            'subject_type' => 'User',
            'subject_id' => $user->id,
        ]);
        ActivityLog::create([
            'actor_id' => $other->id,
            'action' => 'OTHER_ACTION',
            'subject_type' => 'User',
            'subject_id' => $other->id,
        ]);

        $this->getJson('/api/auth/activity-logs')
            ->assertOk()
            ->assertJsonFragment(['action' => 'OWN_ACTION'])
            ->assertJsonMissing(['action' => 'OTHER_ACTION']);
    }

    public function test_account_endpoints_require_authentication(): void
    {
        $this->patchJson('/api/auth/profile')->assertUnauthorized();
        $this->putJson('/api/auth/password')->assertUnauthorized();
        $this->getJson('/api/auth/activity-logs')->assertUnauthorized();

    }
}
