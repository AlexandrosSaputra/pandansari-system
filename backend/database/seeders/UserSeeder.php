<?php

namespace Database\Seeders;

use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class UserSeeder extends Seeder
{
    /**
     * Seed the initial application account.
     */
    public function run(): void
    {
        $account = $this->accounts()['superadmin'];
        $role = Role::where('slug', $account['role'])->firstOrFail();

        $user = $this->upsertUser($account, $role);

        $user->assignments()->firstOrCreate([
            'role_id' => $role->id,
            'operational_unit_id' => null,
        ], ['is_active' => true]);
    }

    /**
     * @param  list<OperationalUnit>  $outlets
     * @return array{warehouse: User, admin: User}
     */
    public function seedDemoAccounts(array $outlets, OperationalUnit $ticketing): array
    {
        $this->validateDemoAccountConfiguration();

        $accounts = $this->accounts();
        $warehouseRole = Role::where('slug', $accounts['warehouse']['role'])->firstOrFail();
        $adminRole = Role::where('slug', $accounts['admin']['role'])->firstOrFail();

        $warehouseUser = $this->upsertUser($accounts['warehouse'], $warehouseRole);
        $warehouseUser->assignments()->updateOrCreate([
            'role_id' => $warehouseRole->id,
            'operational_unit_id' => null,
        ], ['is_active' => true]);

        $adminUser = $this->upsertUser($accounts['admin'], $adminRole, $outlets[0]);

        foreach ([...$outlets, $ticketing] as $unit) {
            $adminUser->assignments()->updateOrCreate([
                'role_id' => $adminRole->id,
                'operational_unit_id' => $unit->id,
            ], ['is_active' => true]);
        }

        return [
            'warehouse' => $warehouseUser,
            'admin' => $adminUser,
        ];
    }

    public function validateDemoAccountConfiguration(): void
    {
        if ((string) config('demo.user_password') === '') {
            throw new LogicException('DEMO_USER_PASSWORD must be configured before seeding demo accounts.');
        }
    }

    /**
     * Username and password for every seeded account are defined together here.
     *
     * @return array<string, array{name: string, username: string, email: string, password: string, role: string}>
     */
    private function accounts(): array
    {
        $demoPassword = (string) config('demo.user_password');

        return [
            'superadmin' => [
                'name' => 'Superadmin',
                'username' => 'admin',
                'password' => 'Password123!',
                'email' => (string) config('auth.initial_superadmin_email'),
                'role' => 'superadmin',
            ],
            'warehouse' => [
                'name' => 'Petugas Gudang Demo',
                'username' => 'warehouse-demo',
                'password' => $demoPassword,
                'email' => 'warehouse.demo@pandansari.local',
                'role' => 'warehouse',
            ],
            'admin' => [
                'name' => 'Petugas Outlet dan Ticketing Demo',
                'username' => 'admin-demo',
                'password' => $demoPassword,
                'email' => 'admin.demo@pandansari.local',
                'role' => 'admin',
            ],
        ];
    }

    /**
     * @param  array{name: string, username: string, email: string, password: string, role: string}  $account
     */
    private function upsertUser(array $account, Role $role, ?OperationalUnit $unit = null): User
    {
        $user = User::firstOrCreate(['email' => $account['email']], [
            'name' => $account['name'],
            'username' => $account['username'],
            'password' => Hash::make($account['password']),
            'email_verified_at' => now(),
        ]);

        $user->forceFill([
            'name' => $account['name'],
            'username' => $account['username'],
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
            'is_active' => true,
        ]);

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
