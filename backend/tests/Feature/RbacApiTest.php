<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RbacApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roles_and_permissions_are_paginated_and_readable_by_slug(): void
    {
        $this->actingAsSystemUser();

        $this->getJson('/api/roles?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/roles/'.Role::where('slug', 'admin')->value('id'))
            ->assertOk()->assertJsonPath('data.slug', 'admin')
            ->assertJsonPath('data.permissions.0', 'dashboard.view');
        $this->getJson('/api/permissions?domain=inventory&per_page=2')
            ->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_non_superadmin_role_mapping_syncs_slugs_without_duplicate_pivot(): void
    {
        $actor = $this->actingAsSystemUser();
        $admin = Role::where('slug', 'admin')->firstOrFail();
        $id = $admin->id;
        $countBefore = $admin->permissions()->count();

        $this->putJson('/api/roles/'.$id.'/permissions', [
            'permissions' => ['dashboard.view', 'item.view', 'item.view'],
        ])->assertOk()->assertJsonPath('data.permissions', ['dashboard.view', 'item.view']);

        $this->assertSame(2, $admin->permissions()->count());
        $this->assertGreaterThan(2, $countBefore);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ROLE_PERMISSIONS_UPDATED', 'actor_id' => $actor->id,
            'subject_type' => 'Role', 'subject_id' => $id,
        ]);

        $this->putJson('/api/roles/'.$id.'/permissions', [
            'permissions' => ['dashboard.view', 'item.view'],
        ])->assertOk();
        $this->assertSame(2, $admin->permissions()->count());

        $this->putJson('/api/roles/'.$id.'/permissions', ['permissions' => []])
            ->assertOk()->assertJsonPath('data.permissions', []);
        $this->assertSame(0, $admin->permissions()->count());
    }

    public function test_invalid_permission_and_superadmin_mapping_change_are_rejected(): void
    {
        $this->actingAsSystemUser();
        $admin = Role::where('slug', 'admin')->firstOrFail();
        $superadmin = Role::where('slug', 'superadmin')->firstOrFail();

        $this->putJson('/api/roles/'.$admin->id.'/permissions', ['permissions' => ['unknown.action']])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        $this->putJson('/api/roles/'.$superadmin->id.'/permissions', ['permissions' => ['dashboard.view']])
            ->assertConflict();
        $this->assertSame(Permission::count(), $superadmin->permissions()->count());
    }

    public function test_rbac_api_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->getJson('/api/permissions')->assertUnauthorized();
        $this->putJson('/api/roles/1/permissions', [])->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
        $this->putJson('/api/roles/'.Role::where('slug', 'admin')->value('id').'/permissions', [])->assertForbidden();

        $this->assertDatabaseCount('role_permissions', 37);
    }
}
