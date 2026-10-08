<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_movement_history_is_paginated_filterable_and_scoped_by_actor_domain(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA, $locationA] = $this->unit('OUT-A');
        [$unitB, $locationB] = $this->unit('OUT-B');
        $warehouse = InventoryLocation::where('code', 'WH-MAIN')->firstOrFail();
        $item = Item::create(['item_code' => 'WATER', 'name' => 'Water', 'type' => ItemType::Product]);
        $performer = $this->account('superadmin');

        $warehouseMovement = $this->movement($warehouse, $item, $performer, StockMovementType::StockIn, StockDirection::In);
        $unitAMovement = $this->movement($locationA, $item, $performer, StockMovementType::StockIn, StockDirection::In);
        $this->movement($locationB, $item, $performer, StockMovementType::StockOut, StockDirection::Out);
        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-SCOPE-1',
            'source_location_id' => $warehouse->id,
            'destination_location_id' => $locationB->id,
            'status' => StockTransferStatus::Accepted,
            'created_by' => $performer->id,
            'responded_by' => $performer->id,
            'responded_at' => now(),
        ]);
        $transferIn = StockMovement::create([
            'inventory_location_id' => $locationB->id,
            'item_id' => $item->id,
            'direction' => StockDirection::In,
            'movement_type' => StockMovementType::TransferIn,
            'quantity' => 3,
            'reference_type' => StockTransfer::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $transfer->id,
            'performed_by' => $performer->id,
        ]);

        $adminA = $this->account('admin', $unitA);
        $today = now()->toDateString();
        $this->switchTo($adminA)->getJson('/api/inventory/movements?inventory_location_id='.$locationA->id
            .'&item_id='.$item->id.'&movement_type=STOCK_IN&direction=IN&performed_by='.$performer->id
            .'&date_from='.$today.'&date_to='.$today.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $unitAMovement->id)
            ->assertJsonPath('data.0.location.id', $locationA->id)
            ->assertJsonPath('data.0.performer.id', $performer->id);
        $this->getJson('/api/inventory/movements?inventory_location_id='.$locationB->id)
            ->assertOk()->assertJsonPath('meta.total', 0);

        $warehouseUser = $this->account('warehouse');
        $warehouseResponse = $this->switchTo($warehouseUser)->getJson('/api/inventory/movements?per_page=10')
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertEqualsCanonicalizing([$warehouseMovement->id, $transferIn->id], collect($warehouseResponse->json('data'))->pluck('id')->all());

        $superadmin = $this->account('superadmin');
        $this->switchTo($superadmin)->getJson('/api/inventory/movements?per_page=2')
            ->assertOk()->assertJsonPath('meta.total', 4)->assertJsonCount(2, 'data');

        $this->assertNotSame($unitA->id, $unitB->id);
    }

    public function test_movement_history_requires_authentication_and_inventory_permission(): void
    {
        $this->getJson('/api/inventory/movements')->assertUnauthorized();

        $this->seed(DatabaseSeeder::class);
        $role = Role::create(['name' => 'No Inventory', 'slug' => 'no-inventory']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($user->refresh(), 'web')->getJson('/api/inventory/movements')->assertForbidden();
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function unit(string $code): array
    {
        $unit = OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => OperationalUnitType::Outlet]);
        $location = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => $code,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);

        return [$unit, $location];
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

    private function movement(
        InventoryLocation $location,
        Item $item,
        User $performer,
        StockMovementType $type,
        StockDirection $direction,
    ): StockMovement {
        return StockMovement::create([
            'inventory_location_id' => $location->id,
            'item_id' => $item->id,
            'direction' => $direction,
            'movement_type' => $type,
            'quantity' => 5,
            'performed_by' => $performer->id,
        ]);
    }

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
