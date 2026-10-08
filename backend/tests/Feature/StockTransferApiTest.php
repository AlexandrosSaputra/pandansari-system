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
use App\Models\StockTransfer;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class StockTransferApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_transfer_reserves_stock_without_reducing_on_hand_or_writing_movements(): void
    {
        $creator = $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 100);

        $response = $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $product->id, 'quantity' => 60]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.creator.id', $creator->id)
            ->assertJsonPath('data.destination.operational_unit.id', $unit->id)
            ->assertJsonPath('data.items.0.item.id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 60)
            ->assertJsonPath('data.responder', null)
            ->assertJsonPath('data.rejection_reason', null);

        $transferNumber = $response->json('data.transfer_number');
        $this->assertMatchesRegularExpression('/^TRF-\d{8}-\d{6,}$/', $transferNumber);
        $warehouse = $this->warehouse();
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 60,
        ]);
        $this->assertDatabaseHas('stock_transfers', [
            'transfer_number' => $transferNumber,
            'status' => 'PENDING',
            'responded_by' => null,
            'responded_at' => null,
            'rejection_reason' => null,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'TRANSFER_CREATED', 'actor_id' => $creator->id]);
    }

    public function test_create_transfer_validates_destination_items_duplicates_and_available_stock(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        [$inactiveUnit] = $this->unit('OUT-INACTIVE', false);
        $product = $this->registeredProduct($unit, 'WATER', 10);
        $unregistered = Item::create(['item_code' => 'SNACK', 'name' => 'Snack', 'type' => ItemType::Product]);
        $inactive = Item::create(['item_code' => 'OLD', 'name' => 'Old', 'type' => ItemType::Product, 'is_active' => false]);
        $service = Item::create(['item_code' => 'SERVICE', 'name' => 'Service', 'type' => ItemType::Service]);
        $url = '/api/stock-transfers';

        $this->postJson($url, ['destination_operational_unit_id' => $unit->id, 'items' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson($url, [
            'destination_operational_unit_id' => $unit->id,
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
                ['item_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.item_id');
        $this->postJson($url, ['destination_operational_unit_id' => $unit->id, 'items' => [['item_id' => $service->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_id');
        $this->postJson($url, ['destination_operational_unit_id' => $unit->id, 'items' => [['item_id' => $inactive->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_id');
        $this->postJson($url, ['destination_operational_unit_id' => $unit->id, 'items' => [['item_id' => $unregistered->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_id');
        $this->postJson($url, ['destination_operational_unit_id' => $inactiveUnit->id, 'items' => [['item_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('destination_operational_unit_id');
        $this->postJson($url, ['destination_operational_unit_id' => $unit->id, 'items' => [['item_id' => $product->id, 'quantity' => 11]]])
            ->assertConflict();

        $this->assertDatabaseCount('stock_transfers', 0);
        $this->assertSame(0, InventoryBalance::where('item_id', $product->id)->value('quantity_reserved'));
    }

    public function test_multi_item_transfer_is_atomic_and_pending_reservations_reduce_future_availability(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        $water = $this->registeredProduct($unit, 'WATER', 100);
        $snack = $this->registeredProduct($unit, 'SNACK', 10);

        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [
                ['item_id' => $water->id, 'quantity' => 50],
                ['item_id' => $snack->id, 'quantity' => 20],
            ],
        ])->assertConflict();
        $this->assertDatabaseCount('stock_transfers', 0);
        $this->assertSame(0, InventoryBalance::where('item_id', $water->id)->value('quantity_reserved'));
        $this->assertSame(0, InventoryBalance::where('item_id', $snack->id)->value('quantity_reserved'));

        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $water->id, 'quantity' => 80]],
        ])->assertCreated();
        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $water->id, 'quantity' => 50]],
        ])->assertConflict();

        $balance = InventoryBalance::where('item_id', $water->id)->firstOrFail();
        $this->assertSame(100, $balance->quantity_on_hand);
        $this->assertSame(80, $balance->quantity_reserved);
        $this->assertSame(20, $balance->available_quantity);
        $this->assertDatabaseCount('stock_transfers', 1);
    }

    public function test_accept_transfer_moves_reserved_stock_atomically_and_records_both_ledger_sides(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit, $destination] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 100);
        InventoryBalance::create([
            'inventory_location_id' => $destination->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $transferId = $this->createTransfer($unit, $product, 80);
        $admin = $this->account('admin', $unit);

        $this->switchTo($admin)->postJson('/api/stock-transfers/'.$transferId.'/accept')
            ->assertOk()->assertJsonPath('data.status', 'ACCEPTED')
            ->assertJsonPath('data.responder.id', $admin->id)
            ->assertJsonPath('data.rejection_reason', null);

        $warehouse = $this->warehouse();
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $warehouse->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $destination->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 90,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_location_id' => $warehouse->id,
            'movement_type' => 'TRANSFER_OUT',
            'direction' => 'OUT',
            'quantity' => 80,
            'reference_type' => StockTransfer::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $transferId,
            'performed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_location_id' => $destination->id,
            'movement_type' => 'TRANSFER_IN',
            'direction' => 'IN',
            'quantity' => 80,
            'reference_type' => StockTransfer::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $transferId,
            'performed_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transferId, 'status' => 'ACCEPTED', 'responded_by' => $admin->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'TRANSFER_ACCEPTED', 'actor_id' => $admin->id]);
    }

    public function test_reject_transfer_only_releases_reservation_and_requires_a_reason(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit, $destination] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 100);
        $transferId = $this->createTransfer($unit, $product, 80);
        $admin = $this->account('admin', $unit);

        $this->switchTo($admin)->postJson('/api/stock-transfers/'.$transferId.'/reject', ['rejection_reason' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->postJson('/api/stock-transfers/'.$transferId.'/reject', ['rejection_reason' => '  Quantity does not match  '])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.rejection_reason', 'Quantity does not match')
            ->assertJsonPath('data.responder.id', $admin->id);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $this->warehouse()->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseMissing('inventory_balances', ['inventory_location_id' => $destination->id, 'item_id' => $product->id]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'TRANSFER_REJECTED', 'actor_id' => $admin->id]);
    }

    public function test_transfer_list_detail_and_response_are_scoped_to_the_destination_unit(): void
    {
        $warehouseUser = $this->actingAsSystemUser('warehouse');
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $product = $this->registeredProduct($unitA, 'WATER', 100);
        UnitItem::create(['operational_unit_id' => $unitB->id, 'item_id' => $product->id, 'selling_price' => 6000]);
        $transferA = $this->createTransfer($unitA, $product, 10);
        $transferB = $this->createTransfer($unitB, $product, 10);

        $adminA = $this->account('admin', $unitA);
        $today = now()->toDateString();
        $this->switchTo($adminA)->getJson('/api/stock-transfers?status=PENDING&destination_operational_unit_id='.$unitA->id.'&created_by='.$warehouseUser->id.'&search=TRF-&date_from='.$today.'&date_to='.$today.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $transferA);
        $this->getJson('/api/stock-transfers/'.$transferA)->assertOk()->assertJsonPath('data.items.0.item.id', $product->id);
        $this->getJson('/api/stock-transfers/'.$transferB)->assertForbidden();
        $this->postJson('/api/stock-transfers/'.$transferB.'/accept')->assertForbidden();
        $this->postJson('/api/stock-transfers/'.$transferB.'/reject', ['rejection_reason' => 'No'])->assertForbidden();
        $this->postJson('/api/stock-transfers', [])->assertForbidden();

        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $warehouseRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'transfer.respond')->value('id'));
        $this->switchTo($warehouseUser)->getJson('/api/stock-transfers')->assertOk()->assertJsonPath('meta.total', 2);
        $this->postJson('/api/stock-transfers/'.$transferA.'/accept')->assertForbidden();

        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)->getJson('/api/stock-transfers')->assertOk()->assertJsonPath('meta.total', 2);
        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unitA->id,
            'items' => [['item_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    public function test_superadmin_can_respond_but_accept_rejects_an_inactive_destination(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 30);
        $acceptedId = $this->createTransfer($unit, $product, 5);
        $rejectedId = $this->createTransfer($unit, $product, 5);
        $superadmin = $this->account('superadmin');

        $this->switchTo($superadmin)->postJson('/api/stock-transfers/'.$acceptedId.'/accept')
            ->assertOk()->assertJsonPath('data.responder.id', $superadmin->id);
        $unit->update(['is_active' => false]);
        $this->postJson('/api/stock-transfers/'.$rejectedId.'/accept')->assertConflict();
        $this->postJson('/api/stock-transfers/'.$rejectedId.'/reject', ['rejection_reason' => 'Destination closed'])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED');

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $this->warehouse()->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_all_repeated_or_crossed_responses_return_conflict_without_double_mutation(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit, $destination] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 100);
        $acceptTwice = $this->createTransfer($unit, $product, 10);
        $rejectTwice = $this->createTransfer($unit, $product, 10);
        $acceptThenReject = $this->createTransfer($unit, $product, 10);
        $rejectThenAccept = $this->createTransfer($unit, $product, 10);
        $admin = $this->account('admin', $unit);
        $this->switchTo($admin);

        $this->postJson('/api/stock-transfers/'.$acceptTwice.'/accept')->assertOk();
        $this->postJson('/api/stock-transfers/'.$acceptTwice.'/accept')->assertConflict();
        $this->postJson('/api/stock-transfers/'.$rejectTwice.'/reject', ['rejection_reason' => 'Reject'])->assertOk();
        $this->postJson('/api/stock-transfers/'.$rejectTwice.'/reject', ['rejection_reason' => 'Again'])->assertConflict();
        $this->postJson('/api/stock-transfers/'.$acceptThenReject.'/accept')->assertOk();
        $this->postJson('/api/stock-transfers/'.$acceptThenReject.'/reject', ['rejection_reason' => 'Too late'])->assertConflict();
        $this->postJson('/api/stock-transfers/'.$rejectThenAccept.'/reject', ['rejection_reason' => 'Reject'])->assertOk();
        $this->postJson('/api/stock-transfers/'.$rejectThenAccept.'/accept')->assertConflict();

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $this->warehouse()->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 80,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_location_id' => $destination->id,
            'item_id' => $product->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_accept_invariant_failure_rolls_back_every_item_and_all_movements(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        $water = $this->registeredProduct($unit, 'WATER', 50);
        $snack = $this->registeredProduct($unit, 'SNACK', 50);
        $response = $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [
                ['item_id' => $water->id, 'quantity' => 10],
                ['item_id' => $snack->id, 'quantity' => 10],
            ],
        ])->assertCreated();
        $transferId = $response->json('data.id');
        InventoryBalance::where('item_id', $snack->id)->update(['quantity_reserved' => 0]);
        $admin = $this->account('admin', $unit);

        $this->switchTo($admin)->postJson('/api/stock-transfers/'.$transferId.'/accept')->assertConflict();

        $this->assertDatabaseHas('inventory_balances', ['item_id' => $water->id, 'quantity_on_hand' => 50, 'quantity_reserved' => 10]);
        $this->assertDatabaseHas('inventory_balances', ['item_id' => $snack->id, 'quantity_on_hand' => 50, 'quantity_reserved' => 0]);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transferId, 'status' => 'PENDING']);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_create_transfer_rolls_back_reservations_when_activity_logging_fails(): void
    {
        $this->actingAsSystemUser('warehouse');
        [$unit] = $this->unit('OUT-A');
        $product = $this->registeredProduct($unit, 'WATER', 50);
        $this->mock(ActivityLogger::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $product->id, 'quantity' => 20]],
        ])->assertInternalServerError();

        $this->assertDatabaseCount('stock_transfers', 0);
        $this->assertDatabaseCount('stock_transfer_items', 0);
        $this->assertSame(0, InventoryBalance::where('item_id', $product->id)->value('quantity_reserved'));
    }

    public function test_transfer_endpoints_require_authentication(): void
    {
        $this->getJson('/api/stock-transfers')->assertUnauthorized();
        $this->postJson('/api/stock-transfers', [])->assertUnauthorized();
        $this->getJson('/api/stock-transfers/1')->assertUnauthorized();
        $this->postJson('/api/stock-transfers/1/accept')->assertUnauthorized();
        $this->postJson('/api/stock-transfers/1/reject', [])->assertUnauthorized();
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function unit(string $code, bool $active = true): array
    {
        $unit = OperationalUnit::create([
            'code' => $code,
            'name' => $code,
            'type' => OperationalUnitType::Outlet,
            'is_active' => $active,
        ]);
        $location = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => $code,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
            'is_active' => $active,
        ]);

        return [$unit, $location];
    }

    private function registeredProduct(OperationalUnit $unit, string $code, int $warehouseQuantity): Item
    {
        $item = Item::create(['item_code' => $code, 'name' => $code, 'type' => ItemType::Product]);
        UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => 5000,
            'minimum_stock' => 5,
        ]);
        InventoryBalance::create([
            'inventory_location_id' => $this->warehouse()->id,
            'item_id' => $item->id,
            'quantity_on_hand' => $warehouseQuantity,
            'quantity_reserved' => 0,
        ]);

        return $item;
    }

    private function createTransfer(OperationalUnit $unit, Item $item, int $quantity): int
    {
        return $this->postJson('/api/stock-transfers', [
            'destination_operational_unit_id' => $unit->id,
            'items' => [['item_id' => $item->id, 'quantity' => $quantity]],
        ])->assertCreated()->json('data.id');
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

    private function warehouse(): InventoryLocation
    {
        return InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
    }

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
