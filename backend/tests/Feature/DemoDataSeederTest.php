<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_demo_seeding_without_a_configured_password(): void
    {
        config(['demo.user_password' => null]);

        $this->expectException(LogicException::class);

        $this->seed(DemoDataSeeder::class);
    }

    public function test_creates_complete_demo_data_idempotently(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('operational_units', 2);
        $this->assertDatabaseCount('inventory_locations', 3);
        $this->assertDatabaseCount('items', 3);
        $this->assertDatabaseCount('unit_items', 4);
        $this->assertDatabaseCount('user_role_assignments', 4);
        $this->assertDatabaseCount('inventory_balances', 2);
        $this->assertDatabaseCount('stock_movements', 2);

        $warehouseRoleId = Role::where('slug', 'warehouse')->value('id');
        $adminRoleId = Role::where('slug', 'admin')->value('id');
        $outletId = $this->unitId('OUT-DEMO');
        $ticketingId = $this->unitId('TKT-DEMO');
        $warehouseUser = User::where('email', 'warehouse.demo@pandansari.local')->firstOrFail();
        $adminUser = User::where('email', 'admin.demo@pandansari.local')->firstOrFail();

        $this->assertSame('warehouse-demo', $warehouseUser->username);
        $this->assertSame('admin-demo', $adminUser->username);
        $this->assertTrue(Hash::check('DemoPassword123!', $warehouseUser->password));
        $this->assertTrue(Hash::check('DemoPassword123!', $adminUser->password));
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $warehouseUser->id,
            'role_id' => $warehouseRoleId,
            'operational_unit_id' => null,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $adminUser->id,
            'role_id' => $adminRoleId,
            'operational_unit_id' => $outletId,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $adminUser->id,
            'role_id' => $adminRoleId,
            'operational_unit_id' => $ticketingId,
            'is_active' => true,
        ]);

        $warehouseId = InventoryLocation::where('code', 'WH-MAIN')->value('id');
        $waterId = Item::where('item_code', 'PROD-WATER')->value('id');
        $snackId = Item::where('item_code', 'PROD-SNACK')->value('id');
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouseId,
            'item_id' => $waterId,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouseId,
            'item_id' => $snackId,
            'quantity_on_hand' => 8,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_location_id' => $warehouseId,
            'item_id' => $waterId,
            'movement_type' => 'STOCK_IN',
            'quantity' => 30,
            'performed_by' => $warehouseUser->id,
            'note' => 'Persediaan awal demo',
        ]);
        $this->assertDatabaseHas('unit_items', [
            'operational_unit_id' => $ticketingId,
            'item_id' => Item::where('item_code', 'SERVICE-TICKET')->value('id'),
            'selling_price' => '15000.00',
            'minimum_stock' => 0,
            'is_active' => true,
        ]);
    }

    private function unitId(string $code): int
    {
        return (int) OperationalUnit::where('code', $code)->value('id');
    }
}
