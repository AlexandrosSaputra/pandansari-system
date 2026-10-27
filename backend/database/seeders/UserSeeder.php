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
     * @return array{warehouse: User, ticketing: User, outlets: list<User>}
     */
    public function seedDemoAccounts(array $outlets, OperationalUnit $ticketing): array
    {
        $this->validateDemoAccountConfiguration();

        $accounts = $this->accounts();
        $warehouseRole = Role::where('slug', $accounts['warehouse']['role'])->firstOrFail();
        $adminRole = Role::where('slug', $accounts['ticketing']['role'])->firstOrFail();

        $warehouseUser = $this->upsertUser($accounts['warehouse'], $warehouseRole);
        $this->syncSingleAssignment($warehouseUser, $warehouseRole);

        $ticketingUser = $this->upsertUser($accounts['ticketing'], $adminRole, $ticketing);
        $this->syncSingleAssignment($ticketingUser, $adminRole, $ticketing);

        $outletUsers = [];
        foreach ($outlets as $index => $outlet) {
            $account = $accounts['outlet_'.($index + 1)] ?? null;

            if ($account === null) {
                throw new LogicException('A demo account must be configured for every demo outlet.');
            }

            $outletUser = $this->upsertUser($account, $adminRole, $outlet);
            $this->syncSingleAssignment($outletUser, $adminRole, $outlet);
            $outletUsers[] = $outletUser;
        }

        return [
            'warehouse' => $warehouseUser,
            'ticketing' => $ticketingUser,
            'outlets' => $outletUsers,
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
                'name' => 'Petugas Gudang',
                'username' => 'gudang',
                'password' => $demoPassword,
                'email' => 'warehouse.demo@pandansari.local',
                'role' => 'warehouse',
            ],
            'ticketing' => [
                'name' => 'Petugas Loket',
                'username' => 'loket',
                'password' => $demoPassword,
                'email' => 'loket.demo@pandansari.local',
                'role' => 'admin',
            ],
            'outlet_1' => [
                'name' => 'Petugas Outlet 1',
                'username' => 'outlet-1',
                'password' => $demoPassword,
                'email' => 'admin.demo@pandansari.local',
                'role' => 'admin',
            ],
            'outlet_2' => [
                'name' => 'Petugas Outlet 2',
                'username' => 'outlet-2',
                'password' => $demoPassword,
                'email' => 'outlet2.demo@pandansari.local',
                'role' => 'admin',
            ],
            'outlet_3' => [
                'name' => 'Petugas Outlet 3',
                'username' => 'outlet-3',
                'password' => $demoPassword,
                'email' => 'outlet3.demo@pandansari.local',
                'role' => 'admin',
            ],
        ];
    }

    private function syncSingleAssignment(User $user, Role $role, ?OperationalUnit $unit = null): void
    {
        $assignment = $user->assignments()->updateOrCreate([
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
        ], ['is_active' => true]);

        $user->assignments()->where('id', '!=', $assignment->id)->delete();
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
