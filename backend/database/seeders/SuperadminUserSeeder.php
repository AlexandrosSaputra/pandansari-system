<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminUserSeeder extends Seeder
{
    /**
     * Create the initial local-development account without changing it when the
     * seeder is run again.
     */
    public function run(): void
    {
        $role = Role::where('slug', 'superadmin')->firstOrFail();
        $email = config('auth.initial_superadmin_email');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Superadmin',
                'username' => 'admin',
                'password' => Hash::make('Password123!'),
                'role_id' => $role->id,
                'operational_unit_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->assignments()->firstOrCreate([
            'role_id' => $role->id,
            'operational_unit_id' => null,
        ], ['is_active' => true]);
    }
}
