<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\UnitItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TransactionCartApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_and_superadmin_resolve_one_cart_per_creator_and_unit(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $admin = $this->account('admin', $unitA);

        $created = $this->actingAs($admin, 'web')->postJson('/api/transactions/cart')
            ->assertCreated()->assertJsonPath('data.status', 'CART')
            ->assertJsonPath('data.operational_unit.id', $unitA->id)
            ->assertJsonPath('data.creator.id', $admin->id)
            ->assertJsonPath('data.total', '0.00');
        $cartId = $created->json('data.id');
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{6,}$/', $created->json('data.transaction_number'));
        $this->postJson('/api/transactions/cart')->assertOk()->assertJsonPath('data.id', $cartId);
        $this->postJson('/api/transactions/cart', ['operational_unit_id' => $unitB->id])
            ->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');

        $superadmin = $this->account('superadmin');
        $second = $this->switchTo($superadmin)->postJson('/api/transactions/cart', ['operational_unit_id' => $unitB->id])
            ->assertCreated()->assertJsonPath('data.operational_unit.id', $unitB->id);
        $this->postJson('/api/transactions/cart', ['operational_unit_id' => $unitB->id])
            ->assertOk()->assertJsonPath('data.id', $second->json('data.id'));

        $this->assertNotSame($created->json('data.transaction_number'), $second->json('data.transaction_number'));
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_manual_add_increments_one_line_and_preserves_decimal_price_snapshot_without_reserving_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $product = $this->item('WATER', ItemType::Product, '111');
        $unitItem = $this->unitItem($unit, $product, '5000.10');
        $admin = $this->account('admin', $unit);
        $cartId = $this->actingAs($admin, 'web')->postJson('/api/transactions/cart')->assertCreated()->json('data.id');

        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL', 'item_id' => $product->id, 'quantity' => 1,
        ])->assertOk()->assertJsonPath('data.items.0.unit_price', '5000.10');
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL', 'item_id' => $product->id, 'quantity' => 2,
        ])->assertOk()->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.subtotal', '15000.30')
            ->assertJsonPath('data.subtotal', '15000.30')
            ->assertJsonPath('data.total', '15000.30');

        $unitItem->update(['selling_price' => '6000.00']);
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL', 'item_id' => $product->id,
        ])->assertOk()->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.items.0.unit_price', '5000.10')
            ->assertJsonPath('data.items.0.subtotal', '20000.40');

        $this->assertDatabaseCount('transaction_items', 1);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_manual_add_rejects_items_outside_the_active_unit_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $otherUnitItem = $this->item('OTHER', ItemType::Product);
        $inactiveChild = $this->item('HIDDEN', ItemType::Product);
        $inactiveItem = $this->item('OLD', ItemType::Product, active: false);
        $this->unitItem($unitB, $otherUnitItem, '1000.00');
        $this->unitItem($unitA, $inactiveChild, '1000.00', false);
        $this->unitItem($unitA, $inactiveItem, '1000.00');
        $admin = $this->account('admin', $unitA);
        $cartId = $this->actingAs($admin, 'web')->postJson('/api/transactions/cart')->assertCreated()->json('data.id');

        foreach ([$otherUnitItem, $inactiveChild, $inactiveItem] as $item) {
            $this->postJson('/api/transactions/'.$cartId.'/items', [
                'input_method' => 'MANUAL', 'item_id' => $item->id,
            ])->assertUnprocessable()->assertJsonValidationErrors('item_id');
        }
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scanner_flow_resolves_barcode_in_the_same_reusable_cart_service(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $water = $this->item('WATER', ItemType::Product, 'SCAN-111');
        $other = $this->item('OTHER', ItemType::Product, 'SCAN-222');
        $this->unitItem($unitA, $water, '5000.00');
        $this->unitItem($unitB, $other, '6000.00');
        $admin = $this->account('admin', $unitA);
        $cartId = $this->actingAs($admin, 'web')->postJson('/api/transactions/cart')->assertCreated()->json('data.id');

        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'SCANNER', 'barcode' => '  SCAN-111  ',
        ])->assertOk()->assertJsonPath('data.items.0.item.id', $water->id)
            ->assertJsonPath('data.items.0.input_method', 'SCANNER')
            ->assertJsonPath('data.items.0.quantity', 1);
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'SCANNER', 'barcode' => 'SCAN-111',
        ])->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'SCANNER', 'barcode' => 'MISSING',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'SCANNER', 'barcode' => 'SCAN-222',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL', 'barcode' => 'SCAN-111',
        ])->assertUnprocessable()->assertJsonValidationErrors(['item_id', 'barcode']);
        $this->assertDatabaseCount('transaction_items', 1);
    }

    public function test_update_and_delete_recalculate_money_and_visitor_count_while_cart_can_remain_empty(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('TICKET', OperationalUnitType::Ticketing);
        $ticket = $this->item('TICKET', ItemType::Service, visitorFactor: 1);
        $parking = $this->item('PARKING', ItemType::Service);
        $product = $this->item('WATER', ItemType::Product);
        $this->unitItem($unit, $ticket, '25000.00');
        $this->unitItem($unit, $parking, '5000.00');
        $this->unitItem($unit, $product, '1000.00');
        $admin = $this->account('admin', $unit);
        $cartId = $this->actingAs($admin, 'web')->postJson('/api/transactions/cart')->assertCreated()->json('data.id');
        $ticketLineId = $this->add($cartId, $ticket, 4)->json('data.items.0.id');
        $this->add($cartId, $parking, 1);
        $this->add($cartId, $product, 2)
            ->assertJsonPath('data.visitor_count', 4)
            ->assertJsonPath('data.total', '107000.00');

        $this->patchJson('/api/transactions/'.$cartId.'/items/'.$ticketLineId, ['quantity' => 5])
            ->assertOk()->assertJsonPath('data.visitor_count', 5)
            ->assertJsonPath('data.total', '132000.00')
            ->assertJsonPath('data.items.0.subtotal', '125000.00');
        $this->patchJson('/api/transactions/'.$cartId.'/items/'.$ticketLineId, ['quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $lineIds = Transaction::findOrFail($cartId)->transactionItems()->pluck('id');
        foreach ($lineIds as $lineId) {
            $this->deleteJson('/api/transactions/'.$cartId.'/items/'.$lineId)->assertOk();
        }
        $this->getJson('/api/transactions/'.$cartId)
            ->assertOk()->assertJsonPath('data.status', 'CART')
            ->assertJsonPath('data.total', '0.00')
            ->assertJsonPath('data.visitor_count', 0)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_cart_mutation_is_creator_only_even_for_same_unit_or_superadmin(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $product = $this->item('WATER', ItemType::Product);
        $this->unitItem($unit, $product, '5000.00');
        $owner = $this->account('admin', $unit);
        $otherAdmin = $this->account('admin', $unit);
        $superadmin = $this->account('superadmin');
        $cartId = $this->actingAs($owner, 'web')->postJson('/api/transactions/cart')->assertCreated()->json('data.id');
        $lineId = $this->add($cartId, $product, 1)->json('data.items.0.id');

        foreach ([$otherAdmin, $superadmin] as $user) {
            $this->switchTo($user)->postJson('/api/transactions/'.$cartId.'/items', [
                'input_method' => 'MANUAL', 'item_id' => $product->id,
            ])->assertForbidden();
            $this->patchJson('/api/transactions/'.$cartId.'/items/'.$lineId, ['quantity' => 2])->assertForbidden();
            $this->deleteJson('/api/transactions/'.$cartId.'/items/'.$lineId)->assertForbidden();
        }
        $this->assertDatabaseHas('transaction_items', ['id' => $lineId, 'quantity' => 1]);
    }

    public function test_cart_endpoints_reject_guests_and_warehouse_even_if_permission_is_misconfigured(): void
    {
        $this->postJson('/api/transactions/cart')->assertUnauthorized();

        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $warehouseRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'transaction.create')->value('id'));
        $warehouse = $this->account('warehouse');

        $this->actingAs($warehouse, 'web')->postJson('/api/transactions/cart', ['operational_unit_id' => $unit->id])->assertForbidden();
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function unit(string $code, OperationalUnitType $type = OperationalUnitType::Outlet): array
    {
        $unit = OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => $type]);
        $location = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => $unit->name,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);

        return [$unit, $location];
    }

    private function item(
        string $code,
        ItemType $type,
        ?string $barcode = null,
        bool $active = true,
        int $visitorFactor = 0,
    ): Item {
        return Item::create([
            'item_code' => $code,
            'barcode' => $barcode,
            'name' => $code,
            'type' => $type,
            'visitor_factor' => $visitorFactor,
            'is_active' => $active,
        ]);
    }

    private function unitItem(OperationalUnit $unit, Item $item, string $price, bool $active = true): UnitItem
    {
        return UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => $price,
            'is_active' => $active,
        ]);
    }

    private function account(string $roleSlug, ?OperationalUnit $unit = null): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'operational_unit_id' => $unit?->id,
        ])->save();

        return $user->refresh();
    }

    private function add(int $cartId, Item $item, int $quantity): TestResponse
    {
        return $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL',
            'item_id' => $item->id,
            'quantity' => $quantity,
        ])->assertOk();
    }

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
