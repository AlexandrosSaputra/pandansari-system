<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_and_service_creation_follow_visitor_and_barcode_rules(): void
    {
        $actor = $this->actingAsSystemUser();

        $product = $this->postJson('/api/items', [
            'barcode' => '1234567890', 'name' => 'Air Mineral',
            'type' => 'PRODUCT', 'uom' => 'bottle',
        ])->assertCreated()
            ->assertJsonPath('data.item_code', 'PROD-000001')
            ->assertJsonPath('data.visitor_factor', 0);

        $this->postJson('/api/items', [
            'name' => 'Ticket', 'type' => 'SERVICE', 'visitor_factor' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.item_code', 'SERVICE-000002')
            ->assertJsonPath('data.barcode', null);

        $this->postJson('/api/items', [
            'name' => 'Parkir', 'type' => 'SERVICE', 'visitor_factor' => 0,
        ])->assertCreated();

        $this->assertDatabaseCount('items', 3);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ITEM_CREATED', 'actor_id' => $actor->id,
            'subject_type' => 'Item', 'subject_id' => $product->json('data.id'),
        ]);
    }

    public function test_item_code_is_generated_and_other_item_rules_are_validated(): void
    {
        $this->actingAsSystemUser();
        $this->postJson('/api/items', [
            'barcode' => '123', 'name' => 'Water', 'type' => 'PRODUCT',
        ])->assertCreated();

        $this->postJson('/api/items', [
            'item_code' => 'MANUAL-CODE', 'name' => 'Manual', 'type' => 'PRODUCT',
        ])->assertUnprocessable()->assertJsonValidationErrors('item_code');
        $this->postJson('/api/items', [
            'barcode' => '123', 'name' => 'Duplicate barcode', 'type' => 'PRODUCT',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->postJson('/api/items', [
            'name' => 'Invalid', 'type' => 'STOCK',
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson('/api/items', [
            'name' => 'Invalid factor', 'type' => 'PRODUCT', 'visitor_factor' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('visitor_factor');

        $this->assertDatabaseCount('items', 1);
    }

    public function test_item_update_and_filtered_list_preserve_master_row(): void
    {
        $this->actingAsSystemUser();
        $id = $this->postJson('/api/items', [
            'name' => 'Water', 'type' => 'PRODUCT',
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/items/'.$id, ['name' => 'Air Mineral'])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'ITEM_UPDATED', 'subject_id' => $id]);
        $this->patchJson('/api/items/'.$id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('items', ['id' => $id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ITEM_STATUS_CHANGED', 'subject_id' => $id]);
        $this->getJson('/api/items?search=Mineral&type=PRODUCT&is_active=0&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/items/'.$id)->assertOk()->assertJsonPath('data.name', 'Air Mineral');
        $this->assertSame(1, Item::count());
    }

    public function test_item_api_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/items')->assertUnauthorized();
        $this->postJson('/api/items', [])->assertUnauthorized();

        $this->actingAsSystemUser('warehouse');
        $this->getJson('/api/items')->assertForbidden();
        $this->postJson('/api/items', [])->assertForbidden();

        $this->assertDatabaseCount('items', 0);
    }
}
