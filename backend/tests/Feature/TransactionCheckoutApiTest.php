<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\ActivityLog;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class TransactionCheckoutApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_checkout_uses_available_unit_stock_and_records_sale_atomically(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        $unitBalance = InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
        ]);
        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        InventoryBalance::create([
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);
        $admin = $this->account('admin', $unit);
        $cartId = $this->cartWithItem($admin, $unit, $product, 8);
        UnitItem::where('operational_unit_id', $unit->id)->where('item_id', $product->id)->update(['selling_price' => '6000.00']);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')
            ->assertOk()->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.payer.id', $admin->id)
            ->assertJsonPath('data.total', '40000.00')
            ->assertJsonPath('data.items.0.unit_price', '5000.00')
            ->assertJsonPath('data.visitor_count', 0);

        $this->assertDatabaseHas('inventory_balances', [
            'id' => $unitBalance->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 2,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'direction' => 'OUT',
            'movement_type' => 'SALE',
            'quantity' => 8,
            'reference_type' => Transaction::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $cartId,
            'performed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('transactions', ['id' => $cartId, 'status' => 'PAID', 'paid_by' => $admin->id]);
        $this->assertNotNull(Transaction::findOrFail($cartId)->paid_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'TRANSACTION_PAID', 'actor_id' => $admin->id, 'subject_id' => $cartId]);
    }

    public function test_insufficient_available_stock_leaves_the_entire_checkout_unchanged(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 3,
        ]);
        $admin = $this->account('admin', $unit);
        $cartId = $this->cartWithItem($admin, $unit, $product, 8);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();

        $this->assertDatabaseHas('transactions', ['id' => $cartId, 'status' => 'CART', 'paid_by' => null, 'paid_at' => null]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 3,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'TRANSACTION_PAID']);
    }

    public function test_ticketing_service_checkout_needs_no_inventory_and_calculates_visitors(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('TICKET', OperationalUnitType::Ticketing);
        $ticket = $this->catalogItem($unit, 'TICKET', ItemType::Service, '25000.00', 1);
        $parking = $this->catalogItem($unit, 'PARKING', ItemType::Service, '5000.00');
        $admin = $this->account('admin', $unit);
        $cartId = $this->resolveCart($admin, $unit);
        $this->add($cartId, $ticket, 4);
        $this->add($cartId, $parking, 1);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')
            ->assertOk()->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.total', '105000.00')
            ->assertJsonPath('data.visitor_count', 4);

        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_mixed_product_and_service_checkout_applies_stock_by_item_type_not_unit_type(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('MIXED');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        $service = $this->catalogItem($unit, 'ENTRY', ItemType::Service, '10000.00', 2);
        InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);
        $admin = $this->account('admin', $unit);
        $cartId = $this->resolveCart($admin, $unit);
        $this->add($cartId, $product, 2);
        $this->add($cartId, $service, 1);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')
            ->assertOk()->assertJsonPath('data.total', '20000.00')
            ->assertJsonPath('data.visitor_count', 2);

        $this->assertDatabaseHas('inventory_balances', ['item_id' => $product->id, 'quantity_on_hand' => 3]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseMissing('stock_movements', ['item_id' => $service->id]);
    }

    public function test_multi_product_checkout_rolls_back_when_any_product_is_short(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $itemA = $this->catalogItem($unit, 'A', ItemType::Product, '1000.00');
        $itemB = $this->catalogItem($unit, 'B', ItemType::Product, '2000.00');
        foreach ([[$itemA, 5], [$itemB, 4]] as [$item, $stock]) {
            InventoryBalance::create([
                'inventory_location_id' => $location->id,
                'item_id' => $item->id,
                'quantity_on_hand' => $stock,
                'quantity_reserved' => 0,
            ]);
        }
        $admin = $this->account('admin', $unit);
        $cartId = $this->resolveCart($admin, $unit);
        $this->add($cartId, $itemA, 5);
        $this->add($cartId, $itemB, 5);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();

        $this->assertDatabaseHas('inventory_balances', ['item_id' => $itemA->id, 'quantity_on_hand' => 5]);
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $itemB->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('transactions', ['id' => $cartId, 'status' => 'CART']);
    }

    public function test_paid_transaction_is_immutable_and_double_checkout_has_no_duplicate_sale(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $admin = $this->account('admin', $unit);
        $cartId = $this->cartWithItem($admin, $unit, $product, 2);
        $lineId = Transaction::findOrFail($cartId)->transactionItems()->value('id');
        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertOk();

        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL', 'item_id' => $product->id,
        ])->assertConflict();
        $this->patchJson('/api/transactions/'.$cartId.'/items/'.$lineId, ['quantity' => 3])->assertConflict();
        $this->deleteJson('/api/transactions/'.$cartId.'/items')->assertConflict();
        $this->deleteJson('/api/transactions/'.$cartId.'/items/'.$lineId)->assertConflict();
        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();

        $this->assertDatabaseHas('transaction_items', ['id' => $lineId, 'quantity' => 2, 'unit_price' => '5000.00']);
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $product->id, 'quantity_on_hand' => 8]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(1, ActivityLog::where('action', 'TRANSACTION_PAID')->count());
    }

    public function test_two_carts_competing_for_stock_cannot_make_inventory_negative(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $adminA = $this->account('admin', $unit);
        $adminB = $this->account('admin', $unit);
        $cartA = $this->cartWithItem($adminA, $unit, $product, 7);
        $cartB = $this->cartWithItem($adminB, $unit, $product, 7);

        $this->switchTo($adminA)->postJson('/api/transactions/'.$cartA.'/checkout')->assertOk();
        $this->switchTo($adminB)->postJson('/api/transactions/'.$cartB.'/checkout')->assertConflict();

        $this->assertDatabaseHas('inventory_balances', ['item_id' => $product->id, 'quantity_on_hand' => 3, 'quantity_reserved' => 0]);
        $this->assertDatabaseHas('transactions', ['id' => $cartA, 'status' => 'PAID']);
        $this->assertDatabaseHas('transactions', ['id' => $cartB, 'status' => 'CART']);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_checkout_rolls_back_stock_movement_and_status_when_activity_log_fails(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit, $location] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        InventoryBalance::create([
            'inventory_location_id' => $location->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $admin = $this->account('admin', $unit);
        $cartId = $this->cartWithItem($admin, $unit, $product, 2);
        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertInternalServerError();

        $this->assertDatabaseHas('transactions', ['id' => $cartId, 'status' => 'CART', 'paid_by' => null]);
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $product->id, 'quantity_on_hand' => 10]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_checkout_revalidates_nonempty_cart_active_items_relations_and_unit(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $product = $this->catalogItem($unit, 'WATER', ItemType::Product, '5000.00');
        $admin = $this->account('admin', $unit);
        $cartId = $this->resolveCart($admin, $unit);

        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();
        $this->add($cartId, $product, 1);
        $product->update(['is_active' => false]);
        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();
        $product->update(['is_active' => true]);
        UnitItem::where('operational_unit_id', $unit->id)->where('item_id', $product->id)->update(['is_active' => false]);
        $this->postJson('/api/transactions/'.$cartId.'/checkout')->assertConflict();
        $this->assertDatabaseHas('transactions', ['id' => $cartId, 'status' => 'CART']);

        [$superUnit] = $this->unit('OUT-SUPER');
        $service = $this->catalogItem($superUnit, 'SERVICE', ItemType::Service, '1000.00');
        $superadmin = $this->account('superadmin');
        $superCartId = $this->resolveCart($superadmin, $superUnit);
        $this->add($superCartId, $service, 1);
        $this->postJson('/api/transactions/'.$superCartId.'/checkout')
            ->assertOk()->assertJsonPath('data.payer.id', $superadmin->id);
        $inactiveUnitCartId = $this->resolveCart($superadmin, $superUnit);
        $this->add($inactiveUnitCartId, $service, 1);
        $superUnit->update(['is_active' => false]);
        $this->postJson('/api/transactions/'.$inactiveUnitCartId.'/checkout')->assertConflict();
        $this->assertDatabaseHas('transactions', ['id' => $inactiveUnitCartId, 'status' => 'CART']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'TRANSACTION_PAID', 'actor_id' => $superadmin->id]);
        $this->assertDatabaseCount('stock_movements', 0);
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

    private function catalogItem(
        OperationalUnit $unit,
        string $code,
        ItemType $type,
        string $price,
        int $visitorFactor = 0,
    ): Item {
        $item = Item::create([
            'item_code' => $code,
            'name' => $code,
            'type' => $type,
            'visitor_factor' => $visitorFactor,
        ]);
        UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => $price,
        ]);

        return $item;
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

    private function resolveCart(User $user, OperationalUnit $unit): int
    {
        $this->switchTo($user);
        $payload = $user->role->slug === 'superadmin' ? ['operational_unit_id' => $unit->id] : [];

        return $this->postJson('/api/transactions/cart', $payload)->assertCreated()->json('data.id');
    }

    private function add(int $cartId, Item $item, int $quantity): void
    {
        $this->postJson('/api/transactions/'.$cartId.'/items', [
            'input_method' => 'MANUAL',
            'item_id' => $item->id,
            'quantity' => $quantity,
        ])->assertOk();
    }

    private function cartWithItem(User $user, OperationalUnit $unit, Item $item, int $quantity): int
    {
        $cartId = $this->resolveCart($user, $unit);
        $this->add($cartId, $item, $quantity);

        return $cartId;
    }

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
