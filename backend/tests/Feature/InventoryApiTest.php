<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stock_in_enforces_access_product_rules_and_records_an_atomic_ledger_entry(): void
    {
        $this->getJson('/api/inventory/warehouse')->assertUnauthorized();
        $this->postJson('/api/inventory/warehouse/stock-in', [])->assertUnauthorized();

        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'inventory.stock-in')->value('id'));
        $admin = $this->account('admin', $unit);
        $product = $this->item('WATER', ItemType::Product);
        $service = $this->item('SERVICE', ItemType::Service);

        $this->actingAs($admin, 'web')
            ->postJson('/api/inventory/warehouse/stock-in', ['item_id' => $product->id, 'quantity' => 1])
            ->assertForbidden();

        $warehouseUser = $this->account('warehouse');
        $this->switchTo($warehouseUser);
        $this->postJson('/api/inventory/warehouse/stock-in', ['item_id' => $service->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->postJson('/api/inventory/warehouse/stock-in', ['item_id' => $product->id, 'quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $response = $this->postJson('/api/inventory/warehouse/stock-in', [
            'item_id' => $product->id,
            'quantity' => 25,
        ])->assertCreated()
            ->assertJsonPath('data.direction', 'IN')
            ->assertJsonPath('data.movement_type', 'STOCK_IN')
            ->assertJsonPath('data.performer.id', $warehouseUser->id)
            ->assertJsonPath('data.note', null);

        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $response->json('data.id'),
            'direction' => 'IN',
            'movement_type' => 'STOCK_IN',
            'performed_by' => $warehouseUser->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'WAREHOUSE_STOCK_IN',
            'actor_id' => $warehouseUser->id,
        ]);

        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)
            ->postJson('/api/inventory/warehouse/stock-in', ['item_id' => $product->id, 'quantity' => 5, 'note' => '  Correction  '])
            ->assertCreated()->assertJsonPath('data.note', 'Correction');
        $this->assertSame(30, InventoryBalance::where('item_id', $product->id)->value('quantity_on_hand'));
    }

    public function test_stock_out_uses_available_quantity_and_never_consumes_reserved_stock(): void
    {
        $actor = $this->actingAsSystemUser('warehouse');
        $product = $this->item('WATER', ItemType::Product);
        $service = $this->item('SERVICE', ItemType::Service);
        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        InventoryBalance::create([
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 30,
        ]);

        $this->postJson('/api/inventory/warehouse/stock-out', [
            'item_id' => $product->id,
            'quantity' => 70,
            'note' => 'Physical correction',
        ])->assertCreated()
            ->assertJsonPath('data.direction', 'OUT')
            ->assertJsonPath('data.movement_type', 'STOCK_OUT')
            ->assertJsonPath('data.performer.id', $actor->id);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 30,
        ]);
        $this->postJson('/api/inventory/warehouse/stock-out', ['item_id' => $product->id, 'quantity' => 1])
            ->assertConflict();
        $this->postJson('/api/inventory/warehouse/stock-out', ['item_id' => $service->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'WAREHOUSE_STOCK_OUT', 'actor_id' => $actor->id]);
    }

    public function test_warehouse_inventory_reads_balances_without_creating_missing_rows(): void
    {
        $this->actingAsSystemUser('warehouse');
        $water = $this->item('WATER', ItemType::Product);
        $inactive = $this->item('OLD', ItemType::Product, false);
        $this->item('SERVICE', ItemType::Service);
        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        InventoryBalance::create([
            'inventory_location_id' => $warehouse->id,
            'item_id' => $water->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 4,
        ]);

        $this->getJson('/api/inventory/warehouse?search=Water&is_active=1&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.id', $water->id)
            ->assertJsonPath('data.0.quantity_on_hand', 15)
            ->assertJsonPath('data.0.quantity_reserved', 4)
            ->assertJsonPath('data.0.available_quantity', 11);
        $this->getJson('/api/inventory/warehouse?is_active=0')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.id', $inactive->id)
            ->assertJsonPath('data.0.quantity_on_hand', 0)
            ->assertJsonPath('data.0.updated_at', null);
        $this->assertDatabaseCount('inventory_balances', 1);

        $unit = $this->unit('OUT-A');
        $admin = $this->account('admin', $unit);
        $this->switchTo($admin)->getJson('/api/inventory/warehouse')->assertForbidden();
        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)->getJson('/api/inventory/warehouse')->assertOk();
    }

    public function test_unit_inventory_is_scoped_and_computes_missing_and_low_stock_without_side_effects(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA, $locationA] = $this->unitWithLocation('OUT-A');
        [$unitB, $locationB] = $this->unitWithLocation('OUT-B');
        $water = $this->item('WATER', ItemType::Product);
        $snack = $this->item('SNACK', ItemType::Product);
        $service = $this->item('SERVICE', ItemType::Service);
        UnitItem::create(['operational_unit_id' => $unitA->id, 'item_id' => $water->id, 'selling_price' => 5000, 'minimum_stock' => 10]);
        UnitItem::create(['operational_unit_id' => $unitA->id, 'item_id' => $snack->id, 'selling_price' => 7000, 'minimum_stock' => 0]);
        UnitItem::create(['operational_unit_id' => $unitA->id, 'item_id' => $service->id, 'selling_price' => 1000, 'minimum_stock' => 0]);
        UnitItem::create(['operational_unit_id' => $unitB->id, 'item_id' => $water->id, 'selling_price' => 5500, 'minimum_stock' => 2]);
        InventoryBalance::create([
            'inventory_location_id' => $locationA->id,
            'item_id' => $water->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
        ]);

        $adminA = $this->account('admin', $unitA);
        $this->actingAs($adminA, 'web')
            ->getJson('/api/operational-units/'.$unitA->id.'/inventory')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.item.id', $water->id)
            ->assertJsonPath('data.0.quantity_on_hand', 10)
            ->assertJsonPath('data.0.available_quantity', 8)
            ->assertJsonPath('data.0.low_stock', true)
            ->assertJsonPath('data.1.item.id', $snack->id)
            ->assertJsonPath('data.1.quantity_on_hand', 0)
            ->assertJsonPath('data.1.low_stock', true);
        $this->getJson('/api/operational-units/'.$unitB->id.'/inventory')->assertForbidden();
        $this->assertDatabaseCount('inventory_balances', 1);

        $warehouseUser = $this->account('warehouse');
        $this->switchTo($warehouseUser)->getJson('/api/operational-units/'.$unitB->id.'/inventory')->assertOk();
        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)->getJson('/api/operational-units/'.$unitB->id.'/inventory')->assertOk();
        $this->assertNotNull($locationB);
    }

    public function test_stock_mutation_rolls_back_when_activity_logging_fails(): void
    {
        $this->actingAsSystemUser('warehouse');
        $product = $this->item('WATER', ItemType::Product);
        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        $this->postJson('/api/inventory/warehouse/stock-in', ['item_id' => $product->id, 'quantity' => 10])
            ->assertInternalServerError();

        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('stock_movements', 0);
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

    private function unit(string $code): OperationalUnit
    {
        return OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => OperationalUnitType::Outlet]);
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function unitWithLocation(string $code): array
    {
        $unit = $this->unit($code);
        $location = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => $unit->name,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);

        return [$unit, $location];
    }

    private function item(string $code, ItemType $type, bool $active = true): Item
    {
        return Item::create([
            'item_code' => $code,
            'name' => ucfirst(strtolower($code)),
            'type' => $type,
            'is_active' => $active,
        ]);
    }
}
