<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\InventoryLocation;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_api_is_read_only_paginated_and_filterable(): void
    {
        $actor = $this->actingAsSystemUser();
        ActivityLog::create([
            'actor_id' => $actor->id, 'action' => 'ITEM_CREATED',
            'subject_type' => 'Item', 'subject_id' => 11,
            'metadata' => ['item_code' => 'WATER'],
        ]);
        ActivityLog::create([
            'actor_id' => $actor->id, 'action' => 'USER_UPDATED',
            'subject_type' => 'User', 'subject_id' => 12,
        ]);

        $today = now()->toDateString();
        $this->getJson('/api/activity-logs?actor_id='.$actor->id.'&action=ITEM_CREATED&subject_type=Item&date_from='.$today.'&date_to='.$today.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.actor.id', $actor->id)
            ->assertJsonPath('data.0.metadata.item_code', 'WATER');
        $this->getJson('/api/activity-logs?date_to='.$today)->assertOk()->assertJsonPath('meta.total', 2);

        $this->postJson('/api/activity-logs', [])->assertStatus(405);
        $this->deleteJson('/api/activity-logs/1')->assertNotFound();
    }

    public function test_audit_api_requires_authentication_and_audit_permission(): void
    {
        $this->getJson('/api/activity-logs')->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $this->getJson('/api/activity-logs')->assertForbidden();
    }

    public function test_auth_login_and_logout_are_logged_without_changing_responses(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['password' => 'StrongPass1234']);
        $user->forceFill(['role_id' => Role::where('slug', 'warehouse')->value('id')])->save();

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/auth/login', [
            'username' => $user->username, 'password' => 'StrongPass1234',
        ])->assertOk()->assertJsonPath('data.role.slug', 'warehouse');
        $this->postJson('/api/auth/logout')->assertOk()->assertExactJson(['message' => 'Logged out.']);

        $this->assertDatabaseHas('activity_logs', ['actor_id' => $user->id, 'action' => 'AUTH_LOGIN_SUCCESS']);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $user->id, 'action' => 'AUTH_LOGOUT']);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_master_mutation_rolls_back_when_audit_log_cannot_be_written(): void
    {
        $this->actingAsSystemUser();
        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        $this->postJson('/api/operational-units', [
            'code' => 'OUT-A', 'name' => 'Toko A', 'type' => 'OUTLET',
        ])->assertInternalServerError()->assertExactJson(['message' => 'Server Error.']);

        $this->assertSame(0, OperationalUnit::count());
        $this->assertSame(1, InventoryLocation::count());
    }
}
