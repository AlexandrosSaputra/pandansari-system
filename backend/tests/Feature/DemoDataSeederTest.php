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

        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('operational_units', 4);
        $this->assertDatabaseCount('inventory_locations', 5);
        $this->assertDatabaseCount('items', 30);
        $this->assertDatabaseCount('unit_items', 30);
        $this->assertDatabaseCount('user_role_assignments', 6);
        $this->assertDatabaseCount('inventory_balances', 38);
        $this->assertDatabaseCount('stock_movements', 43);
        $this->assertDatabaseCount('stock_transfers', 1);
        $this->assertDatabaseCount('stock_transfer_items', 7);

        $warehouseRoleId = Role::where('slug', 'warehouse')->value('id');
        $adminRoleId = Role::where('slug', 'admin')->value('id');
        $outletIds = [
            $this->unitId('Outlet 1'),
            $this->unitId('Outlet 2'),
            $this->unitId('Outlet 3'),
        ];
        $ticketingId = $this->unitId('Loket Ticket');
        $warehouseUser = User::where('username', 'gudang')->firstOrFail();
        $ticketingUser = User::where('username', 'loket')->firstOrFail();
        $outletUsers = collect(['outlet-1', 'outlet-2', 'outlet-3'])
            ->map(fn (string $username): User => User::where('username', $username)->firstOrFail());

        $this->assertSame('Petugas Gudang', $warehouseUser->name);
        $this->assertSame('Petugas Loket', $ticketingUser->name);
        $this->assertSame(
            ['Loket Ticket', 'Outlet 1', 'Outlet 2', 'Outlet 3'],
            OperationalUnit::query()->orderBy('name')->pluck('name')->all(),
        );
        foreach (OperationalUnit::pluck('code') as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}\d{4}$/', $code);
        }
        $this->assertTrue(Hash::check('DemoPassword123!', $warehouseUser->password));
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $warehouseUser->id,
            'role_id' => $warehouseRoleId,
            'operational_unit_id' => null,
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('DemoPassword123!', $ticketingUser->password));
        $this->assertSame($ticketingId, $ticketingUser->operational_unit_id);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $ticketingUser->id,
            'role_id' => $adminRoleId,
            'operational_unit_id' => $ticketingId,
            'is_active' => true,
        ]);

        foreach ($outletUsers as $index => $outletUser) {
            $this->assertSame('Petugas Outlet '.($index + 1), $outletUser->name);
            $this->assertTrue(Hash::check('DemoPassword123!', $outletUser->password));
            $this->assertSame($outletIds[$index], $outletUser->operational_unit_id);
            $this->assertCount(1, $outletUser->assignments);
            $this->assertDatabaseHas('user_role_assignments', [
                'user_id' => $outletUser->id,
                'role_id' => $adminRoleId,
                'operational_unit_id' => $outletIds[$index],
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
        $outletOneLocationId = InventoryLocation::where('operational_unit_id', $outletIds[0])->value('id');
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
            'quantity' => 36,
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

        foreach ([
            'PROD-AQUA-15-L' => 30,
            'PROD-AQUA-600ML' => 6,
            'PROD-BASO' => 4,
            'PROD-BENG-BENG' => 0,
            'PROD-BETTER' => 25,
            'PROD-CHITATO' => 6,
            'PROD-FRESH-TEA-300ML' => 3,
            'PROD-PIATOS' => 0,
            'PROD-TEH-PUCUK-HARUM' => 20,
        ] as $itemCode => $quantity) {
            $this->assertDatabaseHas('inventory_balances', [
                'inventory_location_id' => $outletOneLocationId,
                'item_id' => Item::where('item_code', $itemCode)->value('id'),
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => 0,
            ]);
        }
    }

    public function test_each_demo_account_logs_in_directly_to_its_own_workspace(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);

        $accounts = [
            ['admin', 'Password123!', 'superadmin', null],
            ['loket', 'DemoPassword123!', 'admin', 'Loket Ticket'],
            ['outlet-1', 'DemoPassword123!', 'admin', 'Outlet 1'],
            ['outlet-2', 'DemoPassword123!', 'admin', 'Outlet 2'],
            ['outlet-3', 'DemoPassword123!', 'admin', 'Outlet 3'],
            ['gudang', 'DemoPassword123!', 'warehouse', null],
        ];

        foreach ($accounts as [$username, $password, $role, $unitName]) {
            $response = $this->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ])->assertOk()
                ->assertJsonPath('data.role.slug', $role)
                ->assertJsonCount(1, 'data.assignments');

            if ($unitName === null) {
                $response->assertJsonPath('data.operational_unit', null);
            } else {
                $response->assertJsonPath('data.operational_unit.name', $unitName);
            }

            $this->app['auth']->guard('web')->logout();
            $this->app['auth']->forgetGuards();
            $this->flushSession();
        }

        $outletOne = User::where('username', 'outlet-1')->firstOrFail();
        $outletTwo = OperationalUnit::where('name', 'Outlet 2')->firstOrFail();
        $foreignAssignment = User::where('username', 'outlet-2')
            ->firstOrFail()
            ->assignments()
            ->sole();

        $this->actingAs($outletOne, 'web')
            ->getJson('/api/operational-units/'.$outletTwo->id.'/inventory')
            ->assertForbidden();
        $this->postJson('/api/auth/assignment', ['assignment_id' => $foreignAssignment->id])
            ->assertUnprocessable();
    }

    public function test_removes_empty_duplicate_parents_created_by_the_previous_demo_seeder(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
        $adminUser = User::where('username', 'outlet-1')->firstOrFail();
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
        $adminUser->update(['operational_unit_id' => $this->unitId('Baso')]);

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('operational_units', 4);
        $this->assertDatabaseMissing('operational_units', ['code' => 'OUT-BASO']);
        $this->assertDatabaseMissing('operational_units', ['code' => 'TKT-PANDANSARI']);
        $this->assertSame($this->unitId('Outlet 1'), $adminUser->fresh()->operational_unit_id);
    }

    public function test_archives_an_obsolete_parent_when_it_has_transaction_history(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
        $adminUser = User::where('username', 'outlet-1')->firstOrFail();
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

    private function unitId(string $name): int
    {
        return (int) OperationalUnit::where('name', $name)->value('id');
    }
}
