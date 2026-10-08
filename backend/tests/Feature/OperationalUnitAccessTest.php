<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\OperationalUnitAccess;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalUnitAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_is_confined_to_its_active_unit_while_superadmin_can_access_all_units(): void
    {
        $unitA = OperationalUnit::create(['code' => 'A', 'name' => 'A', 'type' => OperationalUnitType::Outlet]);
        $unitB = OperationalUnit::create(['code' => 'B', 'name' => 'B', 'type' => OperationalUnitType::Outlet]);
        $unitA->refresh();
        $unitB->refresh();
        $adminA = $this->account('admin', $unitA);
        $adminB = $this->account('admin', $unitB);
        $superadmin = $this->account('superadmin');
        $warehouse = $this->account('warehouse');
        $access = new OperationalUnitAccess;

        $this->assertTrue($access->allows($adminA, $unitA));
        $this->assertFalse($access->allows($adminA, $unitB));
        $this->assertTrue($access->allows($adminB, $unitB));
        $this->assertFalse($access->allows($adminB, $unitA));
        $this->assertTrue($access->allows($superadmin, $unitA));
        $this->assertTrue($access->allows($superadmin, $unitB));
        $this->assertFalse($access->allows($warehouse, $unitA));
        $this->assertFalse($access->allows($warehouse, $unitB));

        $unitB->update(['is_active' => false]);

        $this->assertFalse($access->allows($adminB, $unitB));
        $this->assertTrue($access->allows($superadmin, $unitB));
    }

    public function test_missing_or_inactive_role_user_or_admin_unit_denies_scoped_access(): void
    {
        $unit = OperationalUnit::create(['code' => 'A', 'name' => 'A', 'type' => OperationalUnitType::Outlet]);
        $unit->refresh();
        $admin = $this->account('admin', $unit);
        $access = new OperationalUnitAccess;

        $this->assertTrue($access->canUseApplication($admin));

        $unit->update(['is_active' => false]);
        $admin->unsetRelation('operationalUnit');
        $this->assertFalse($access->canUseApplication($admin));
        $this->assertFalse($access->allows($admin, $unit));

        $unit->update(['is_active' => true]);
        $admin->forceFill(['operational_unit_id' => null])->save();
        $admin->unsetRelation('operationalUnit');
        $this->assertFalse($access->canUseApplication($admin));

        $admin->forceFill(['operational_unit_id' => $unit->id])->save();
        $admin->role->update(['is_active' => false]);
        $admin->unsetRelation('role');
        $this->assertFalse($access->canUseApplication($admin));

        $admin->role->update(['is_active' => true]);
        $admin->forceFill(['is_active' => false])->save();
        $admin->unsetRelation('role');
        $this->assertFalse($access->canUseApplication($admin));

        $admin->forceFill(['is_active' => true, 'role_id' => null])->save();
        $admin->unsetRelation('role');
        $this->assertFalse($access->canUseApplication($admin));
    }

    private function account(string $slug, ?OperationalUnit $unit = null): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
        ])->save();

        return $user->refresh();
    }
}
