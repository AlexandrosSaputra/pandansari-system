<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\UnitItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CoreWorkflowApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_warehouse_to_transfer_to_pos_checkout_flow_keeps_balances_and_ledger_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $unit = OperationalUnit::create([
            'code' => 'OUT-DEMO',
            'name' => 'Outlet Demo',
            'type' => OperationalUnitType::Outlet,
        ]);
        $unitLocation = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => 'Inventori Outlet Demo',
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);
        $product = Item::create([
            'item_code' => 'DRINK-DEMO',
            'barcode' => '8990000000001',
            'name' => 'Minuman Demo',
            'type' => ItemType::Product,
        ]);
        UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $product->id,
            'selling_price' => '7500.00',
            'minimum_stock' => 2,
        ]);

        $warehouseUser = $this->account('warehouse');
        $this->actingAs($warehouseUser, 'web');

        $this->postJson('/api/inventory/warehouse/stock-in', [
            'item_id' => $product->id,
            'quantity' => 20,
            'note' => 'Persediaan awal demo',
        ])->assertCreated()
            ->assertJsonPath('data.movement_type', 'STOCK_IN');

        $transferId = $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $product->id, 'quantity' => 8]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')
            ->json('data.id');

        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 8,
        ]);

        $admin = $this->account('admin', $unit);
        $this->switchTo($admin);
        $this->postJson('/api/inventory/warehouse/stock-in', [
            'item_id' => $product->id,
            'quantity' => 1,
        ])->assertForbidden();
        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();

        $this->postJson("/api/stock-transfers/{$transferId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACCEPTED');

        $cartId = $this->postJson('/api/transactions/cart')
            ->assertCreated()
            ->assertJsonPath('data.status', 'CART')
            ->json('data.id');

        $this->postJson("/api/transactions/{$cartId}/items", [
            'input_method' => 'SCANNER',
            'barcode' => $product->barcode,
            'quantity' => 3,
        ])->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.total', '22500.00');

        $this->postJson("/api/transactions/{$cartId}/checkout")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.total', '22500.00');

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $unitLocation->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $this->assertSame(12, $this->ledgerQuantity($warehouse, $product));
        $this->assertSame(5, $this->ledgerQuantity($unitLocation, $product));
        $this->assertDatabaseHas('stock_movements', [
            'inventory_location_id' => $unitLocation->id,
            'item_id' => $product->id,
            'movement_type' => 'SALE',
            'quantity' => 3,
            'reference_id' => $cartId,
        ]);

        $this->getJson('/api/transactions?status=PAID')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $cartId)
            ->assertJsonPath('data.0.total', '22500.00');
        $this->getJson("/api/transactions/{$cartId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.item.id', $product->id);

        $this->switchTo($warehouseUser)
            ->postJson('/api/transactions/cart')
            ->assertForbidden();

        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)
            ->getJson('/api/transactions?status=PAID')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $cartId);
    }

    private function ledgerQuantity(InventoryLocation $location, Item $item): int
    {
        return StockMovement::query()
            ->where('inventory_location_id', $location->id)
            ->where('item_id', $item->id)
            ->get()
            ->sum(fn (StockMovement $movement): int => $movement->direction->value === 'IN'
                ? $movement->quantity
                : -$movement->quantity);
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

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
