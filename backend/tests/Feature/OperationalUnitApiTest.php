<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryLocation;
use App\Models\OperationalUnit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalUnitApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creation_makes_one_unit_inventory_location_atomically(): void
    {
        $actor = $this->actingAsSystemUser();

        $response = $this->postJson('/api/operational-units', [
            'code' => 'OUT-A', 'name' => 'Toko A', 'type' => 'OUTLET', 'scanner_enabled' => true,
        ])->assertCreated()->assertJsonPath('data.type', 'OUTLET')
            ->assertJsonPath('data.inventory_location.type', 'UNIT');

        $unitId = $response->json('data.id');
        $this->assertDatabaseHas('inventory_locations', [
            'code' => 'UNIT-'.$unitId,
            'operational_unit_id' => $unitId,
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'OPERATIONAL_UNIT_CREATED', 'actor_id' => $actor->id,
            'subject_type' => 'OperationalUnit', 'subject_id' => $unitId,
        ]);
    }

    public function test_ticketing_scanner_defaults_off_and_invalid_input_is_rejected(): void
    {
        $this->actingAsSystemUser();

        $this->postJson('/api/operational-units', [
            'code' => 'TICKET', 'name' => 'Ticketing', 'type' => 'TICKETING',
        ])->assertCreated()->assertJsonPath('data.scanner_enabled', false);

        $this->postJson('/api/operational-units', [
            'code' => 'TICKET2', 'name' => 'Ticketing 2', 'type' => 'TICKETING', 'scanner_enabled' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('scanner_enabled');

        $this->postJson('/api/operational-units', [
            'code' => 'INVALID', 'name' => 'Invalid', 'type' => 'WAREHOUSE',
        ])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->postJson('/api/operational-units', [
            'code' => 'TICKET', 'name' => 'Duplicate', 'type' => 'OUTLET',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_deactivation_preserves_location_and_list_filters_work(): void
    {
        $this->actingAsSystemUser();
        $created = $this->postJson('/api/operational-units', [
            'code' => 'OUT-A', 'name' => 'Toko A', 'type' => 'OUTLET',
        ])->assertCreated();
        $id = $created->json('data.id');
        $locationId = InventoryLocation::where('operational_unit_id', $id)->value('id');

        $this->patchJson('/api/operational-units/'.$id, ['name' => 'Toko A Baru'])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'OPERATIONAL_UNIT_UPDATED', 'subject_id' => $id]);
        $this->patchJson('/api/operational-units/'.$id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.inventory_location.is_active', false);

        $this->assertDatabaseHas('inventory_locations', ['id' => $locationId, 'name' => 'Toko A Baru']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'OPERATIONAL_UNIT_STATUS_CHANGED', 'subject_id' => $id]);
        $this->getJson('/api/operational-units?search=Baru&type=OUTLET&is_active=0&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/operational-units/'.$id)->assertOk()->assertJsonPath('data.code', 'OUT-A');
        $this->assertSame(1, OperationalUnit::count());
    }

    public function test_list_orders_operational_units_alphabetically_by_name(): void
    {
        $this->actingAsSystemUser();

        foreach (['Outlet 3', 'Outlet 1', 'Loket Ticket', 'Outlet 2'] as $index => $name) {
            OperationalUnit::create([
                'code' => 'UNIT-'.($index + 1),
                'name' => $name,
                'type' => $name === 'Loket Ticket' ? OperationalUnitType::Ticketing : OperationalUnitType::Outlet,
            ]);
        }

        $this->getJson('/api/operational-units?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Loket Ticket')
            ->assertJsonPath('data.1.name', 'Outlet 1')
            ->assertJsonPath('data.2.name', 'Outlet 2')
            ->assertJsonPath('data.3.name', 'Outlet 3');
    }

    public function test_unit_api_requires_authentication_and_warehouse_can_list_transfer_destinations(): void
    {
        $this->getJson('/api/operational-units')->assertUnauthorized();
        $this->postJson('/api/operational-units', [])->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $this->getJson('/api/operational-units')->assertOk();
        $this->postJson('/api/operational-units', [])->assertForbidden();

        $this->assertDatabaseCount('operational_units', 0);
    }

    public function test_inventory_location_code_collision_returns_conflict_and_rolls_back_unit(): void
    {
        $this->actingAsSystemUser();
        $probe = OperationalUnit::create([
            'code' => 'AUTO-INCREMENT-PROBE',
            'name' => 'Auto Increment Probe',
            'type' => OperationalUnitType::Outlet,
        ]);
        $collisionCode = 'UNIT-'.($probe->id + 1);
        $probe->delete();
        InventoryLocation::create(['code' => $collisionCode, 'name' => 'Reserved Code', 'type' => InventoryLocationType::Warehouse]);

        $this->postJson('/api/operational-units', [
            'code' => 'OUT-A', 'name' => 'Toko A', 'type' => 'OUTLET',
        ])->assertConflict()->assertExactJson(['message' => 'A record with these values already exists.']);

        $this->assertDatabaseCount('operational_units', 0);
        $this->assertDatabaseCount('inventory_locations', 2);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'OPERATIONAL_UNIT_CREATED']);
    }
}
