<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\OperationalUnitType;
use App\Enums\TransactionStatus;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\UnitItem;
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
        $this->assertDatabaseCount('operational_units', 4);
        $this->assertDatabaseCount('inventory_locations', 5);
        $this->assertDatabaseCount('items', 30);
        $this->assertDatabaseCount('unit_items', 30);
        $this->assertDatabaseCount('user_role_assignments', 6);
        $this->assertDatabaseCount('inventory_balances', 29);
        $this->assertDatabaseCount('stock_movements', 29);

        $warehouseRoleId = Role::where('slug', 'warehouse')->value('id');
        $adminRoleId = Role::where('slug', 'admin')->value('id');
        $outletIds = [
            $this->unitId('OUT-DEMO'),
            $this->unitId('OUT-MINUMAN'),
            $this->unitId('OUT-POP-MIE'),
        ];
        $ticketingId = $this->unitId('TKT-DEMO');
        $warehouseUser = User::where('email', 'warehouse.demo@pandansari.local')->firstOrFail();
        $adminUser = User::where('email', 'admin.demo@pandansari.local')->firstOrFail();

        $this->assertSame('warehouse-demo', $warehouseUser->username);
        $this->assertSame('admin-demo', $adminUser->username);
        $this->assertSame($outletIds[0], $adminUser->operational_unit_id);
        $this->assertSame(
            ['Loket Ticket', 'Outlet 1', 'Outlet 2', 'Outlet 3'],
            OperationalUnit::query()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertTrue(Hash::check('DemoPassword123!', $warehouseUser->password));
        $this->assertTrue(Hash::check('DemoPassword123!', $adminUser->password));
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $warehouseUser->id,
            'role_id' => $warehouseRoleId,
            'operational_unit_id' => null,
            'is_active' => true,
        ]);

        foreach ([...$outletIds, $ticketingId] as $unitId) {
            $this->assertDatabaseHas('user_role_assignments', [
                'user_id' => $adminUser->id,
                'role_id' => $adminRoleId,
                'operational_unit_id' => $unitId,
                'is_active' => true,
            ]);
        }

        $this->assertSame(9, UnitItem::where('operational_unit_id', $outletIds[0])->count());
        $this->assertSame(10, UnitItem::where('operational_unit_id', $outletIds[1])->count());
        $this->assertSame(10, UnitItem::where('operational_unit_id', $outletIds[2])->count());
        $this->assertFalse(UnitItem::query()
            ->select(['operational_unit_id', 'item_id'])
            ->groupBy(['operational_unit_id', 'item_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists());

        $ticketingItems = UnitItem::query()
            ->where('operational_unit_id', $ticketingId)
            ->with('item:id,item_code,type')
            ->get();

        $this->assertCount(1, $ticketingItems);
        $this->assertSame('SERVICE-TICKET', $ticketingItems->first()->item->item_code);
        $this->assertSame('SERVICE', $ticketingItems->first()->item->type->value);

        $warehouseId = InventoryLocation::where('code', 'WH-MAIN')->value('id');
        $waterId = Item::where('item_code', 'PROD-AQUA-600ML')->value('id');
        $ticketId = Item::where('item_code', 'SERVICE-TICKET')->value('id');
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouseId,
            'item_id' => $waterId,
            'quantity_on_hand' => 30,
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
            'operational_unit_id' => $outletIds[0],
            'item_id' => $waterId,
            'selling_price' => '5000.00',
            'minimum_stock' => 5,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('unit_items', [
            'operational_unit_id' => $ticketingId,
            'item_id' => $waterId,
        ]);
        $this->assertDatabaseMissing('inventory_balances', [
            'inventory_location_id' => $warehouseId,
            'item_id' => $ticketId,
        ]);
    }

    public function test_removes_empty_duplicate_parents_created_by_the_previous_demo_seeder(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
        $adminUser = User::where('email', 'admin.demo@pandansari.local')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        foreach ([
            ['code' => 'OUT-BASO', 'name' => 'Baso', 'type' => OperationalUnitType::Outlet],
            ['code' => 'TKT-PANDANSARI', 'name' => 'Loket Tiket', 'type' => OperationalUnitType::Ticketing],
        ] as $legacyData) {
            $legacyUnit = OperationalUnit::create([
                ...$legacyData,
                'scanner_enabled' => true,
                'is_active' => true,
            ]);
            InventoryLocation::create([
                'code' => 'UNIT-'.$legacyUnit->id,
                'name' => $legacyUnit->name,
                'type' => InventoryLocationType::Unit,
                'operational_unit_id' => $legacyUnit->id,
                'is_active' => true,
            ]);
            $adminUser->assignments()->create([
                'role_id' => $adminRole->id,
                'operational_unit_id' => $legacyUnit->id,
                'is_active' => true,
            ]);
        }
        $adminUser->update(['operational_unit_id' => $this->unitId('OUT-BASO')]);

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('operational_units', 4);
        $this->assertDatabaseMissing('operational_units', ['code' => 'OUT-BASO']);
        $this->assertDatabaseMissing('operational_units', ['code' => 'TKT-PANDANSARI']);
        $this->assertSame($this->unitId('OUT-DEMO'), $adminUser->fresh()->operational_unit_id);
    }

    public function test_archives_an_obsolete_parent_when_it_has_transaction_history(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
        $adminUser = User::where('email', 'admin.demo@pandansari.local')->firstOrFail();
        $legacyUnit = OperationalUnit::create([
            'code' => 'OUT-BASO',
            'name' => 'Baso',
            'type' => OperationalUnitType::Outlet,
            'scanner_enabled' => true,
            'is_active' => true,
        ]);
        InventoryLocation::create([
            'code' => 'UNIT-'.$legacyUnit->id,
            'name' => $legacyUnit->name,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $legacyUnit->id,
            'is_active' => true,
        ]);
        Transaction::create([
            'transaction_number' => 'TRX-LEGACY-001',
            'operational_unit_id' => $legacyUnit->id,
            'status' => TransactionStatus::Paid,
            'created_by' => $adminUser->id,
            'paid_by' => $adminUser->id,
            'subtotal' => 0,
            'total' => 0,
            'visitor_count' => 0,
            'paid_at' => now(),
        ]);

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(4, OperationalUnit::active()->count());
        $this->assertDatabaseHas('operational_units', [
            'code' => 'OUT-BASO',
            'scanner_enabled' => false,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('transactions', [
            'transaction_number' => 'TRX-LEGACY-001',
            'operational_unit_id' => $legacyUnit->id,
        ]);
    }

    private function unitId(string $code): int
    {
        return (int) OperationalUnit::where('code', $code)->value('id');
    }
}
