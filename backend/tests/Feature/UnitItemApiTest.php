<?php

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UnitItemApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_same_item_can_have_different_prices_and_stock_thresholds_per_unit(): void
    {
        $actor = $this->actingAsSystemUser();
        $unitA = $this->unit('OUT-A');
        $unitB = $this->unit('OUT-B');
        $water = $this->item('WATER', ItemType::Product);

        $a = $this->postJson('/api/operational-units/'.$unitA->id.'/items', [
            'item_id' => $water->id, 'selling_price' => '5000.00', 'minimum_stock' => 10,
        ])->assertCreated()->assertJsonPath('data.selling_price', '5000.00');
        $b = $this->postJson('/api/operational-units/'.$unitB->id.'/items', [
            'item_id' => $water->id, 'selling_price' => '6000.00', 'minimum_stock' => 20,
        ])->assertCreated()->assertJsonPath('data.selling_price', '6000.00');

        $this->assertSame($water->id, $a->json('data.item.id'));
        $this->assertSame($water->id, $b->json('data.item.id'));
        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('unit_items', 2);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'UNIT_ITEM_CREATED', 'actor_id' => $actor->id]);
    }

    public function test_duplicate_pair_service_stock_and_item_replacement_are_rejected(): void
    {
        $this->actingAsSystemUser();
        $unit = $this->unit('TICKET');
        $service = $this->item('TICKET', ItemType::Service);
        $other = $this->item('PARKING', ItemType::Service);
        $url = '/api/operational-units/'.$unit->id.'/items';

        $this->postJson($url, ['item_id' => $service->id, 'selling_price' => '25000.00', 'minimum_stock' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('minimum_stock');

        $id = $this->postJson($url, ['item_id' => $service->id, 'selling_price' => '25000.00'])
            ->assertCreated()->assertJsonPath('data.minimum_stock', 0)->json('data.id');

        $this->postJson($url, ['item_id' => $service->id, 'selling_price' => '30000.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->patchJson($url.'/'.$id, ['item_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->assertDatabaseCount('unit_items', 1);
    }

    public function test_update_deactivates_relation_without_deleting_it_and_nested_route_is_scoped(): void
    {
        $this->actingAsSystemUser();
        $unitA = $this->unit('OUT-A');
        $unitB = $this->unit('OUT-B');
        $item = $this->item('WATER', ItemType::Product);
        $id = $this->postJson('/api/operational-units/'.$unitA->id.'/items', [
            'item_id' => $item->id, 'selling_price' => 5000,
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/operational-units/'.$unitA->id.'/items/'.$id, [
            'selling_price' => '5500.00',
        ])->assertOk()->assertJsonPath('data.selling_price', '5500.00');
        $this->assertDatabaseHas('activity_logs', ['action' => 'UNIT_ITEM_UPDATED', 'subject_id' => $id]);
        $this->patchJson('/api/operational-units/'.$unitA->id.'/items/'.$id, [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.selling_price', '5500.00')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('unit_items', ['id' => $id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'UNIT_ITEM_STATUS_CHANGED', 'subject_id' => $id]);
        $this->getJson('/api/operational-units/'.$unitA->id.'/items?search=Water&is_active=0&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->patchJson('/api/operational-units/'.$unitB->id.'/items/'.$id, ['selling_price' => 1])->assertNotFound();
        $this->assertSame(1, UnitItem::count());
    }

    public function test_index_filters_unit_items_by_item_type(): void
    {
        $this->actingAsSystemUser();
        $unit = $this->unit('OUT-A');
        $product = $this->item('WATER', ItemType::Product);
        $service = $this->item('TICKET', ItemType::Service);

        UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $product->id,
            'selling_price' => 5000,
        ]);
        UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $service->id,
            'selling_price' => 15000,
        ]);

        $url = '/api/operational-units/'.$unit->id.'/items';

        $this->getJson($url.'?type=PRODUCT')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.id', $product->id);
        $this->getJson($url.'?type=SERVICE')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.id', $service->id);
        $this->getJson($url.'?type=UNKNOWN')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_unit_item_api_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/operational-units/1/items')->assertUnauthorized();
        $this->postJson('/api/operational-units/1/items', [])->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $unit = $this->unit('OUT-A');
        $this->getJson('/api/operational-units/'.$unit->id.'/items')->assertForbidden();
        $this->postJson('/api/operational-units/'.$unit->id.'/items', [])->assertForbidden();
    }

    private function unit(string $code): OperationalUnit
    {
        return OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => OperationalUnitType::Outlet]);
    }

    private function item(string $code, ItemType $type): Item
    {
        return Item::create(['item_code' => $code, 'name' => $code, 'type' => $type]);
    }
}
