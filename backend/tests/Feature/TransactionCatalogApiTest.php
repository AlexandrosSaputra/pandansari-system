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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TransactionCatalogApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_catalog_is_scoped_searchable_and_does_not_create_balances(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA, $locationA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $water = $this->item('WATER', ItemType::Product, '111');
        $service = $this->item('PARKING', ItemType::Service);
        $inactiveItem = $this->item('OLD', ItemType::Product, null, false);
        $inactiveChild = $this->item('HIDDEN', ItemType::Product);
        $this->unitItem($unitA, $water, '5000.00', 10);
        $this->unitItem($unitB, $water, '6000.00', 20);
        $this->unitItem($unitA, $service, '3000.00');
        $this->unitItem($unitA, $inactiveItem, '1000.00');
        $this->unitItem($unitA, $inactiveChild, '1000.00', active: false);
        InventoryBalance::create([
            'inventory_location_id' => $locationA->id,
            'item_id' => $water->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 3,
        ]);
        $admin = $this->account('admin', $unitA);

        $this->actingAs($admin, 'web')->getJson('/api/transaction-catalog?search=water&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.id', $water->id)
            ->assertJsonPath('data.0.selling_price', '5000.00')
            ->assertJsonPath('data.0.inventory.quantity_on_hand', 12)
            ->assertJsonPath('data.0.inventory.quantity_reserved', 3)
            ->assertJsonPath('data.0.inventory.available_quantity', 9)
            ->assertJsonPath('data.0.inventory.low_stock', false);
        $this->getJson('/api/transaction-catalog?search=parking')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item.type', 'SERVICE')
            ->assertJsonPath('data.0.inventory', null);
        $this->getJson('/api/transaction-catalog?operational_unit_id='.$unitB->id)
            ->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');
        $this->getJson('/api/transaction-catalog')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertDatabaseCount('inventory_balances', 1);
    }

    public function test_superadmin_selects_an_active_unit_and_sees_its_current_price(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $water = $this->item('WATER', ItemType::Product, '111');
        $this->unitItem($unitA, $water, '5000.00');
        $this->unitItem($unitB, $water, '6000.00');
        $superadmin = $this->account('superadmin');

        $this->actingAs($superadmin, 'web')->getJson('/api/transaction-catalog')
            ->assertUnprocessable()->assertJsonValidationErrors('operational_unit_id');
        $this->getJson('/api/transaction-catalog?operational_unit_id='.$unitB->id)
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.operational_unit.id', $unitB->id)
            ->assertJsonPath('data.0.selling_price', '6000.00')
            ->assertJsonPath('data.0.inventory.quantity_on_hand', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_catalog_requires_authentication_permission_and_transaction_business_role(): void
    {
        $this->getJson('/api/transaction-catalog')->assertUnauthorized();

        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $noPermissionRole = Role::create(['name' => 'No Transaction', 'slug' => 'no-transaction']);
        $noPermission = User::factory()->create();
        $noPermission->forceFill(['role_id' => $noPermissionRole->id])->save();
        $this->actingAs($noPermission->refresh(), 'web')->getJson('/api/transaction-catalog')->assertForbidden();

        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $warehouseRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'transaction.create')->value('id'));
        $warehouse = $this->account('warehouse');
        $this->switchTo($warehouse)->getJson('/api/transaction-catalog?operational_unit_id='.$unit->id)->assertForbidden();
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function unit(string $code): array
    {
        $unit = OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => OperationalUnitType::Outlet]);
        $location = InventoryLocation::create([
            'code' => 'UNIT-'.$unit->id,
            'name' => $unit->name,
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);

        return [$unit, $location];
    }

    private function item(string $code, ItemType $type, ?string $barcode = null, bool $active = true): Item
    {
        return Item::create([
            'item_code' => $code,
            'barcode' => $barcode,
            'name' => ucfirst(strtolower($code)),
            'type' => $type,
            'is_active' => $active,
        ]);
    }

    private function unitItem(OperationalUnit $unit, Item $item, string $price, int $minimumStock = 0, bool $active = true): UnitItem
    {
        return UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => $price,
            'minimum_stock' => $minimumStock,
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

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
