<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Models\ActivityLog;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterUserApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_endpoints_require_authentication_and_permission(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->postJson('/api/users', [])->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
    }

    public function test_superadmin_creates_admin_with_hashed_password_and_safe_audit_log(): void
    {
        $actor = $this->actingAsSystemUser();
        $unit = OperationalUnit::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'type' => OperationalUnitType::Outlet]);

        $response = $this->postJson('/api/users', [
            'name' => 'Admin Andi',
            'username' => 'admin-andi',
            'email' => 'andi@example.com',
            'password' => 'StrongPass1234',
            'password_confirmation' => 'StrongPass1234',
            'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
            'operational_unit_id' => $unit->id,
        ])->assertCreated()->assertJsonPath('data.username', 'admin-andi')
            ->assertJsonPath('data.role.slug', 'admin')
            ->assertJsonPath('data.operational_unit.id', $unit->id)
            ->assertJsonMissingPath('data.password');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('StrongPass1234', $user->password));
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $actor->id,
            'action' => 'USER_CREATED',
            'subject_type' => 'User',
            'subject_id' => $user->id,
        ]);
        $this->assertStringNotContainsString('password', json_encode(ActivityLog::where('action', 'USER_CREATED')->firstOrFail()->metadata));
    }

    public function test_superadmin_can_assign_the_same_admin_role_to_multiple_units_and_another_role(): void
    {
        $this->actingAsSystemUser();
        $unitA = OperationalUnit::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'type' => OperationalUnitType::Outlet]);
        $unitB = OperationalUnit::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'type' => OperationalUnitType::Outlet]);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();

        $response = $this->postJson('/api/users', [
            'name' => 'Multi Role User',
            'username' => 'multi-role-user',
            'email' => 'multi-role@example.com',
            'password' => 'StrongPass1234',
            'password_confirmation' => 'StrongPass1234',
            'assignments' => [
                ['role_id' => $adminRole->id, 'operational_unit_id' => $unitA->id],
                ['role_id' => $adminRole->id, 'operational_unit_id' => $unitB->id],
                ['role_id' => $warehouseRole->id, 'operational_unit_id' => null],
            ],
        ])->assertCreated()->assertJsonCount(3, 'data.assignments');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('user_role_assignments', 4);
        $this->assertSame(3, $user->assignments()->count());
        $this->assertSame(2, $user->assignments()->where('role_id', $adminRole->id)->count());
    }

    public function test_user_role_and_unit_assignments_are_validated(): void
    {
        $this->actingAsSystemUser();
        $unit = OperationalUnit::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'type' => OperationalUnitType::Outlet]);
        $inactive = OperationalUnit::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'type' => OperationalUnitType::Outlet, 'is_active' => false]);
        $roles = Role::pluck('id', 'slug');
        $base = [
            'name' => 'New User', 'username' => 'new-user', 'email' => 'new@example.com',
            'password' => 'StrongPass1234', 'password_confirmation' => 'StrongPass1234',
        ];

        $this->postJson('/api/users', [...$base, 'role_id' => $roles['admin']])->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');
        $this->postJson('/api/users', [...$base, 'role_id' => $roles['admin'], 'operational_unit_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');
        $this->postJson('/api/users', [...$base, 'role_id' => $roles['warehouse'], 'operational_unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');
        $this->postJson('/api/users', [...$base, 'role_id' => $roles['superadmin'], 'operational_unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');

        Role::where('slug', 'admin')->update(['is_active' => false]);
        $this->postJson('/api/users', [...$base, 'role_id' => $roles['admin'], 'operational_unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_update_keeps_password_unless_supplied_and_protects_last_superadmin(): void
    {
        $actor = $this->actingAsSystemUser();
        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $target = User::factory()->create();
        $target->forceFill(['role_id' => $warehouseRole->id])->save();
        $oldHash = $target->password;

        $this->patchJson('/api/users/'.$target->id, ['name' => 'Updated Name'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Name');
        $this->assertSame($oldHash, $target->fresh()->password);
        $this->assertDatabaseHas('activity_logs', ['action' => 'USER_UPDATED', 'subject_id' => $target->id]);

        $this->patchJson('/api/users/'.$target->id, [
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertOk();
        $this->assertTrue(Hash::check('NewStrongPass1234', $target->fresh()->password));

        $this->patchJson('/api/users/'.$target->id, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('activity_logs', ['action' => 'USER_STATUS_CHANGED', 'subject_id' => $target->id]);

        $this->patchJson('/api/users/'.$actor->id, ['is_active' => false])->assertConflict();
        $this->patchJson('/api/users/'.$actor->id, ['role_id' => $warehouseRole->id])->assertConflict();
        $this->assertTrue($actor->fresh()->is_active);
        $this->assertSame('superadmin', $actor->fresh()->role->slug);
    }

    public function test_user_list_is_paginated_filtered_and_never_exposes_credentials(): void
    {
        $this->actingAsSystemUser();
        $unit = OperationalUnit::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'type' => OperationalUnitType::Outlet]);
        $admin = User::factory()->create(['name' => 'Searchable Admin', 'username' => 'searchable-admin', 'email' => 'search@example.com']);
        $admin->forceFill([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'operational_unit_id' => $unit->id,
            'is_active' => false,
        ])->save();

        $this->getJson('/api/users?search=Searchable&role=admin&operational_unit_id='.$unit->id.'&is_active=0&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'search@example.com')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');

        $this->postJson('/api/users', [
            'name' => 'Duplicate', 'email' => 'search@example.com',
            'username' => 'duplicate-user',
            'password' => 'StrongPass1234', 'password_confirmation' => 'StrongPass1234',
            'role_id' => Role::where('slug', 'warehouse')->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_management_mutation_requires_superadmin_even_if_another_role_gains_permission(): void
    {
        $this->actingAsSystemUser();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'user.manage')->value('id'));
        $unit = OperationalUnit::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'type' => OperationalUnitType::Outlet]);
        $admin = User::factory()->create();
        $admin->forceFill(['role_id' => $adminRole->id, 'operational_unit_id' => $unit->id])->save();
        $this->actingAs($admin->refresh(), 'web');

        $this->postJson('/api/users', [])->assertForbidden();
    }
}
