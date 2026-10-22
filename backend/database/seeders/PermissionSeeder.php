<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $permissions = [
            'dashboard.view' => 'View dashboard',
            'transaction.view' => 'View transactions',
            'transaction.create' => 'Create transactions',
            'inventory.view' => 'View inventory',
            'inventory.stock-in' => 'Record stock in',
            'inventory.stock-out' => 'Record stock out',
            'transfer.view' => 'View transfers',
            'transfer.create' => 'Create transfers',
            'transfer.respond' => 'Respond to transfers',
            'operational-unit.view' => 'View operational units',
            'operational-unit.manage' => 'Manage operational units',
            'item.view' => 'View items',
            'item.manage' => 'Manage items',
            'user.view' => 'View users',
            'user.manage' => 'Manage users',
            'rbac.view' => 'View roles and permissions',
            'rbac.manage' => 'Manage roles and permissions',
            'report.view' => 'View reports',
            'ai.view' => 'View AI results',
            'ai.generate' => 'Generate AI results',
            'audit.view' => 'View activity logs',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $rolePermissions = [
            'admin' => [
                'dashboard.view',
                'transaction.view',
                'transaction.create',
                'inventory.view',
                'transfer.view',
                'transfer.respond',
            ],
            'warehouse' => [
                'dashboard.view',
                'operational-unit.view',
                'inventory.view',
                'inventory.stock-in',
                'inventory.stock-out',
                'transfer.view',
                'transfer.create',
                'report.view',
                'ai.view',
                'ai.generate',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->firstOrFail()->permissions()->sync(
                Permission::whereIn('slug', $permissionSlugs)->pluck('id')->all(),
            );
        }

        Role::where('slug', 'superadmin')->firstOrFail()->permissions()->sync(
            Permission::pluck('id')->all(),
        );
    }
}
