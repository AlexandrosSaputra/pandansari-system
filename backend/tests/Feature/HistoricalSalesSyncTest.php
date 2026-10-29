<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\HistoricalSale;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\HistoricalSalesSyncService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HistoricalSalesSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_paid_transactions_sync_idempotently_and_missing_weeks_are_detected_and_repaired(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
        $this->assertSame(0, Artisan::call('forecast:import-sales'), Artisan::output());

        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        $item = Item::query()->where('name', 'BENG-BENG')->firstOrFail();
        $actor = User::query()->where('username', 'outlet-1')->firstOrFail();
        $this->createTransaction($unit, $item, $actor, TransactionStatus::Paid, 2, '2026-09-02 10:00:00');
        $this->createTransaction($unit, $item, $actor, TransactionStatus::Cart, 99, null);

        $summary = app(HistoricalSalesSyncService::class)->sync(
            CarbonImmutable::parse('2026-09-20', config('forecast.timezone')),
        );
        $this->assertSame(87, $summary['expected_observations']);
        $this->assertSame(87, $summary['rows_synchronized']);
        $this->assertSame(3, $summary['missing_weeks_before']);
        $this->assertSame(87, $summary['missing_observations_before']);
        $this->assertSame(0, $summary['missing_observations_after']);
        $this->assertDatabaseCount('historical_sales', 6119);
        $this->assertDatabaseHas('historical_sales', [
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'week_id' => 'APP-2026-W36',
            'week_start' => '2026-08-31',
            'week_end' => '2026-09-06',
            'units_sold' => 2,
            'unit_price' => '2500.00',
            'sales_value' => '5000.00',
            'source_file' => HistoricalSalesSyncService::SOURCE,
        ]);
        $this->assertDatabaseHas('historical_sales', [
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'week_start' => '2026-09-07',
            'units_sold' => 0,
            'sales_value' => '0.00',
            'source_file' => HistoricalSalesSyncService::SOURCE,
        ]);

        $this->assertSame(0, Artisan::call('forecast:sync-sales', [
            '--through' => '2026-09-20',
        ]), Artisan::output());
        $this->assertDatabaseCount('historical_sales', 6119);

        HistoricalSale::query()
            ->where('operational_unit_id', $unit->id)
            ->where('item_id', $item->id)
            ->whereDate('week_start', '2026-09-07')
            ->delete();
        $check = app(HistoricalSalesSyncService::class)->sync(
            CarbonImmutable::parse('2026-09-20', config('forecast.timezone')),
            true,
        );
        $this->assertSame(1, $check['missing_weeks_after']);
        $this->assertSame(1, $check['missing_observations_after']);
        $this->assertSame(1, Artisan::call('forecast:sync-sales', [
            '--through' => '2026-09-20',
            '--check-only' => true,
        ]));
        $this->assertDatabaseCount('historical_sales', 6118);

        $this->assertSame(0, Artisan::call('forecast:sync-sales', [
            '--through' => '2026-09-20',
        ]), Artisan::output());
        $this->assertDatabaseCount('historical_sales', 6119);

        $this->assertSame(0, Artisan::call('schedule:list'));
        $this->assertStringContainsString('forecast:sync-sales', Artisan::output());
    }

    private function createTransaction(
        OperationalUnit $unit,
        Item $item,
        User $actor,
        TransactionStatus $status,
        int $quantity,
        ?string $paidAt,
    ): void {
        $transaction = Transaction::query()->create([
            'transaction_number' => 'TRX-SYNC-'.$status->value,
            'operational_unit_id' => $unit->id,
            'status' => $status,
            'created_by' => $actor->id,
            'paid_by' => $status === TransactionStatus::Paid ? $actor->id : null,
            'subtotal' => $quantity * 2500,
            'total' => $quantity * 2500,
            'visitor_count' => 0,
            'paid_at' => $paidAt === null
                ? null
                : CarbonImmutable::parse($paidAt, config('forecast.timezone'))->utc(),
        ]);
        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => '2500.00',
            'subtotal' => number_format($quantity * 2500, 2, '.', ''),
            'input_method' => 'MANUAL',
        ]);
    }
}
