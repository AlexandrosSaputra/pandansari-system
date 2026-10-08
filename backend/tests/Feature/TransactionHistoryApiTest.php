<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Enums\TransactionInputMethod;
use App\Enums\TransactionStatus;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UnitItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TransactionHistoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_history_contains_unit_paid_transactions_and_only_its_own_cart(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $adminA = $this->account('admin', $unitA);
        $otherA = $this->account('admin', $unitA);
        $adminB = $this->account('admin', $unitB);
        $paidA = $this->transaction($unitA, $adminA, TransactionStatus::Paid, 'TRX-PAID-A');
        $paidOtherA = $this->transaction($unitA, $otherA, TransactionStatus::Paid, 'TRX-PAID-OTHER-A');
        $cartA = $this->transaction($unitA, $adminA, TransactionStatus::Cart, 'TRX-CART-A');
        $cartOtherA = $this->transaction($unitA, $otherA, TransactionStatus::Cart, 'TRX-CART-OTHER-A');
        $paidB = $this->transaction($unitB, $adminB, TransactionStatus::Paid, 'TRX-PAID-B');

        $response = $this->actingAs($adminA, 'web')->getJson('/api/transactions?per_page=10')
            ->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertEqualsCanonicalizing(
            [$paidA->id, $paidOtherA->id, $cartA->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
        $this->getJson('/api/transactions/'.$paidA->id)->assertOk();
        $this->getJson('/api/transactions/'.$paidOtherA->id)->assertOk();
        $this->getJson('/api/transactions/'.$cartA->id)->assertOk();
        $this->getJson('/api/transactions/'.$cartOtherA->id)->assertForbidden();
        $this->getJson('/api/transactions/'.$paidB->id)->assertForbidden();
        $this->getJson('/api/transactions?operational_unit_id='.$unitB->id)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_superadmin_history_is_global_paginated_and_supports_all_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unitA] = $this->unit('OUT-A');
        [$unitB] = $this->unit('OUT-B');
        $adminA = $this->account('admin', $unitA);
        $adminB = $this->account('admin', $unitB);
        $paidA = $this->transaction($unitA, $adminA, TransactionStatus::Paid, 'TRX-SEARCH-A');
        $this->transaction($unitA, $adminA, TransactionStatus::Cart, 'TRX-CART-A');
        $this->transaction($unitB, $adminB, TransactionStatus::Paid, 'TRX-PAID-B');
        $superadmin = $this->account('superadmin');
        $today = now()->toDateString();

        $this->actingAs($superadmin, 'web')->getJson('/api/transactions?status=PAID&operational_unit_id='.$unitA->id
            .'&created_by='.$adminA->id.'&paid_by='.$adminA->id.'&search=SEARCH&date_from='.$today.'&date_to='.$today.'&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $paidA->id);
        $this->getJson('/api/transactions?per_page=2')
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data');
    }

    public function test_transaction_detail_uses_historical_price_snapshot_not_current_unit_price(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$unit] = $this->unit('OUT-A');
        $admin = $this->account('admin', $unit);
        $item = Item::create(['item_code' => 'WATER', 'name' => 'Water', 'type' => ItemType::Product]);
        $unitItem = UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => '5000.00',
        ]);
        $transaction = $this->transaction($unit, $admin, TransactionStatus::Paid, 'TRX-SNAPSHOT', '10000.00');
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => '5000.00',
            'subtotal' => '10000.00',
            'input_method' => TransactionInputMethod::Manual,
        ]);
        $unitItem->update(['selling_price' => '6000.00']);

        $this->actingAs($admin, 'web')->getJson('/api/transactions/'.$transaction->id)
            ->assertOk()->assertJsonPath('data.transaction_number', 'TRX-SNAPSHOT')
            ->assertJsonPath('data.items.0.unit_price', '5000.00')
            ->assertJsonPath('data.items.0.subtotal', '10000.00')
            ->assertJsonPath('data.total', '10000.00')
            ->assertJsonPath('data.items.0.input_method', 'MANUAL');
    }

    public function test_history_requires_authentication_permission_and_non_warehouse_business_role(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->getJson('/api/transactions/1')->assertUnauthorized();

        $this->seed(DatabaseSeeder::class);
        $role = Role::create(['name' => 'No Transaction', 'slug' => 'no-transaction']);
        $withoutPermission = User::factory()->create();
        $withoutPermission->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($withoutPermission->refresh(), 'web')->getJson('/api/transactions')->assertForbidden();

        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $warehouseRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'transaction.view')->value('id'));
        $warehouse = $this->account('warehouse');
        $this->switchTo($warehouse)->getJson('/api/transactions')->assertForbidden();
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

    private function account(string $roleSlug, ?OperationalUnit $unit = null): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'operational_unit_id' => $unit?->id,
        ])->save();

        return $user->refresh();
    }

    private function transaction(
        OperationalUnit $unit,
        User $creator,
        TransactionStatus $status,
        string $number,
        string $total = '0.00',
    ): Transaction {
        return Transaction::create([
            'transaction_number' => $number,
            'operational_unit_id' => $unit->id,
            'status' => $status,
            'created_by' => $creator->id,
            'paid_by' => $status === TransactionStatus::Paid ? $creator->id : null,
            'subtotal' => $total,
            'total' => $total,
            'visitor_count' => 0,
            'paid_at' => $status === TransactionStatus::Paid ? now() : null,
        ]);
    }

    private function switchTo(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
