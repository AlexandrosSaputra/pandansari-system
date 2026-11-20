<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminDashboardSalesApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_use_paid_time_in_wib_and_exclude_other_units_carts_and_future_payments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $admin = $this->admin();
        $first = $this->sale($admin, '10.25', '2026-11-13 17:00:00');
        $first->forceFill(['created_at' => '2026-10-01 00:00:00'])->save();
        $second = $this->sale($admin, '20.50', '2026-11-19 17:10:00');
        $this->sale($admin, '0.25', '2026-11-20 12:00:00');
        $this->sale($admin, '999.00', '2026-11-13 16:59:59');
        $this->sale($admin, '999.00', '2026-11-20 12:00:01');
        $this->sale($admin, '999.00', null);
        $other = $this->admin('OTHER');
        $foreignSale = $this->sale($other, '999.00', '2026-11-20 10:00:00');
        $item = Item::create(['item_code' => 'WATER', 'name' => 'Air mineral', 'type' => 'PRODUCT', 'uom' => 'botol']);
        foreach ([$first, $second] as $transaction) {
            TransactionItem::create(['transaction_id' => $transaction->id, 'item_id' => $item->id, 'quantity' => 2, 'unit_price' => '5.00', 'subtotal' => $transaction->total, 'input_method' => 'MANUAL']);
        }
        TransactionItem::create(['transaction_id' => $foreignSale->id, 'item_id' => $item->id, 'quantity' => 99, 'unit_price' => '5.00', 'subtotal' => '999.00', 'input_method' => 'MANUAL']);

        $this->actingAs($admin, 'web')->getJson('/api/dashboard/admin/sales?days=7&operational_unit_id='.$other->operational_unit_id)
            ->assertOk()->assertJsonPath('data.revenue', '31.00')
            ->assertJsonPath('data.transactions', 3)
            ->assertJsonPath('data.average_transaction', '10.33')
            ->assertJsonPath('data.items_sold', 4)
            ->assertJsonPath('data.visitors', 6)
            ->assertJsonPath('data.period.from', '2026-11-14')
            ->assertJsonPath('data.period.to', '2026-11-20')
            ->assertJsonCount(7, 'data.daily')
            ->assertJsonPath('data.daily.0.revenue', '10.25')
            ->assertJsonPath('data.daily.1.revenue', '0.00')
            ->assertJsonPath('data.daily.6.revenue', '20.75')
            ->assertJsonPath('data.top_items.0.name', 'Air mineral')
            ->assertJsonPath('data.top_items.0.quantity', 4)
            ->assertJsonPath('data.top_items.0.revenue', '30.75')
            ->assertJsonCount(3, 'data.recent_transactions')
            ->assertJsonPath('data.recent_transactions.2.id', $first->id);
        $this->assertDatabaseCount('transactions', 7);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_empty_ticketing_dashboard_has_thirty_zero_days_and_validates_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC'));
        $admin = $this->admin();
        $admin->activeOperationalUnit()->update(['type' => 'TICKETING']);

        $this->actingAs($admin, 'web')->getJson('/api/dashboard/admin/sales?days=30')
            ->assertOk()->assertJsonPath('data.revenue', '0.00')
            ->assertJsonPath('data.transactions', 0)->assertJsonPath('data.average_transaction', '0.00')
            ->assertJsonPath('data.visitors', 0)->assertJsonPath('data.items_sold', 0)
            ->assertJsonPath('data.period.from', '2026-10-22')
            ->assertJsonCount(30, 'data.daily')->assertJsonCount(0, 'data.top_items')
            ->assertJsonCount(0, 'data.recent_transactions');
        $this->getJson('/api/dashboard/admin/sales?days=8')->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->getJson('/api/dashboard/admin/sales?days=seven')->assertUnprocessable()->assertJsonValidationErrors('days');
    }

    public function test_aggregates_are_not_limited_to_a_page_and_recent_sales_are_limited_to_five(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        for ($index = 0; $index < 105; $index++) {
            $this->sale($admin, '10.00', now()->toDateTimeString());
        }

        $this->actingAs($admin, 'web')->getJson('/api/dashboard/admin/sales')
            ->assertOk()->assertJsonPath('data.transactions', 105)
            ->assertJsonPath('data.revenue', '1050.00')->assertJsonCount(5, 'data.recent_transactions');
    }

    public function test_requires_authentication_both_permissions_and_active_admin_unit(): void
    {
        $this->getJson('/api/dashboard/admin/sales')->assertUnauthorized();
        $admin = $this->admin();
        foreach (['dashboard.view', 'transaction.view'] as $permission) {
            $id = Permission::where('slug', $permission)->value('id');
            $admin->role->permissions()->detach($id);
            $this->app['auth']->forgetGuards();
            $this->actingAs($admin->fresh(), 'web')->getJson('/api/dashboard/admin/sales')->assertForbidden();
            $admin->role->permissions()->attach($id);
        }
        foreach (['superadmin', 'warehouse'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', ['dashboard.view', 'transaction.view'])->pluck('id'));
            $admin->forceFill(['role_id' => $role->id])->save();
            $this->app['auth']->forgetGuards();
            $this->actingAs($admin->fresh(), 'web')->getJson('/api/dashboard/admin/sales')->assertForbidden();
        }
        $admin->forceFill(['role_id' => Role::where('slug', 'admin')->value('id')])->save();
        $admin->activeOperationalUnit()->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin->fresh(), 'web')->getJson('/api/dashboard/admin/sales')->assertForbidden();
    }

    public function test_best_sellers_are_ranked_by_quantity_and_limited_to_five(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $sale = $this->sale($admin, '210.00', now()->toDateTimeString());
        for ($quantity = 1; $quantity <= 6; $quantity++) {
            $item = Item::create(['item_code' => 'SKU-'.$quantity, 'name' => 'Produk '.$quantity, 'type' => 'PRODUCT']);
            TransactionItem::create(['transaction_id' => $sale->id, 'item_id' => $item->id, 'quantity' => $quantity, 'unit_price' => '10.00', 'subtotal' => $quantity * 10, 'input_method' => 'MANUAL']);
        }

        $this->actingAs($admin, 'web')->getJson('/api/dashboard/admin/sales')
            ->assertOk()->assertJsonCount(5, 'data.top_items')
            ->assertJsonPath('data.items_sold', 21)
            ->assertJsonPath('data.top_items.0.name', 'Produk 6')
            ->assertJsonPath('data.top_items.0.quantity', 6)
            ->assertJsonPath('data.top_items.4.name', 'Produk 2');
    }

    private function admin(string $code = 'OUT'): User
    {
        if (! Role::where('slug', 'admin')->exists()) {
            $this->seed(DatabaseSeeder::class);
        }
        $unit = OperationalUnit::create(['code' => $code, 'name' => $code, 'type' => 'OUTLET']);
        $admin = User::factory()->create();
        $admin->forceFill(['role_id' => Role::where('slug', 'admin')->value('id'), 'operational_unit_id' => $unit->id])->save();

        return $admin->refresh();
    }

    private function sale(User $user, string $total, ?string $paidAt): Transaction
    {
        return Transaction::create([
            'transaction_number' => 'TRX-'.(Transaction::count() + 1),
            'operational_unit_id' => $user->operational_unit_id,
            'created_by' => $user->id, 'paid_by' => $paidAt ? $user->id : null,
            'status' => $paidAt ? 'PAID' : 'CART', 'paid_at' => $paidAt,
            'subtotal' => $total, 'total' => $total, 'visitor_count' => 2,
        ]);
    }
}
