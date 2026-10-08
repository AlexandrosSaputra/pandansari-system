<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'admin' => 'Admin',
            'warehouse' => 'Gudang',
            'superadmin' => 'Superadmin',
        ] as $slug => $name) {
            Role::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
