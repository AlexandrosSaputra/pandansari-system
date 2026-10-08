<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_role_seeder_creates_only_three_roles_and_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseHas('roles', ['slug' => 'admin', 'name' => 'Admin']);
        $this->assertDatabaseHas('roles', ['slug' => 'warehouse', 'name' => 'Gudang']);
        $this->assertDatabaseHas('roles', ['slug' => 'superadmin', 'name' => 'Superadmin']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_permission_seeder_can_run_alone_and_maps_exact_role_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $adminPermissions = [
            'dashboard.view',
            'inventory.view',
            'transaction.create',
            'transaction.view',
            'transfer.respond',
            'transfer.view',
        ];
        $warehousePermissions = [
            'ai.generate',
            'ai.view',
            'dashboard.view',
            'inventory.stock-in',
            'inventory.stock-out',
            'inventory.view',
            'report.view',
            'transfer.create',
            'transfer.view',
        ];
        $allPermissions = [
            'ai.generate',
            'ai.view',
            'audit.view',
            'dashboard.view',
            'inventory.stock-in',
            'inventory.stock-out',
            'inventory.view',
            'item.manage',
            'item.view',
            'operational-unit.manage',
            'operational-unit.view',
            'rbac.manage',
            'rbac.view',
            'report.view',
            'transaction.create',
            'transaction.view',
            'transfer.create',
            'transfer.respond',
            'transfer.view',
            'user.manage',
            'user.view',
        ];

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 21);
        $this->assertDatabaseCount('role_permissions', 36);
        $this->assertSame($adminPermissions, $this->permissionSlugs('admin'));
        $this->assertSame($warehousePermissions, $this->permissionSlugs('warehouse'));
        $this->assertSame($allPermissions, $this->permissionSlugs('superadmin'));
        $this->assertDatabaseCount('users', 0);

        Permission::create(['name' => 'Custom action', 'slug' => 'custom.action']);
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('permissions', 22);
        $this->assertDatabaseCount('role_permissions', 37);
        $this->assertContains('custom.action', $this->permissionSlugs('superadmin'));
        $this->assertSame($adminPermissions, $this->permissionSlugs('admin'));
        $this->assertSame($warehousePermissions, $this->permissionSlugs('warehouse'));
    }

    public function test_database_seeder_creates_only_system_configuration_and_can_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 21);
        $this->assertDatabaseCount('role_permissions', 36);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('operational_units', 0);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('inventory_locations', 1);
        $this->assertDatabaseHas('inventory_locations', [
            'code' => 'WH-MAIN',
            'name' => 'Gudang Utama',
            'type' => 'WAREHOUSE',
            'operational_unit_id' => null,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * @return list<string>
     */
    private function permissionSlugs(string $roleSlug): array
    {
        return Role::where('slug', $roleSlug)
            ->firstOrFail()
            ->permissions()
            ->orderBy('permissions.slug')
            ->pluck('permissions.slug')
            ->all();
    }
}
