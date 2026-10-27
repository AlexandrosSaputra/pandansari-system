<?php

namespace Tests\Feature;

use App\Models\HistoricalSale;
use App\Models\OperationalUnit;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HistoricalSalesImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_canonical_capstone_dataset_imports_idempotently_into_the_application_catalog(): void
    {
        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(0, Artisan::call('forecast:import-sales'), Artisan::output());
        $this->assertSame(0, Artisan::call('forecast:import-sales'), Artisan::output());

        $this->assertDatabaseCount('historical_sales', 6032);
        $this->assertSame(208, HistoricalSale::query()->distinct()->count('week_start'));
        $this->assertSame(29, HistoricalSale::query()->distinct()->count('item_id'));
        $this->assertSame('2022-09-05', HistoricalSale::query()->min('week_start'));
        $this->assertSame('2026-08-30', HistoricalSale::query()->max('week_end'));

        $expectedRows = [
            'Outlet 1' => 1872,
            'Outlet 2' => 2080,
            'Outlet 3' => 2080,
        ];

        foreach ($expectedRows as $unitName => $rowCount) {
            $unit = OperationalUnit::query()->where('name', $unitName)->firstOrFail();
            $this->assertSame(
                $rowCount,
                HistoricalSale::query()->where('operational_unit_id', $unit->id)->count(),
            );
        }

        $this->assertDatabaseHas('historical_sales', [
            'source_outlet' => 'Baso',
            'source_sku' => 'BASO',
            'week_id' => 'W001',
            'week_start' => '2022-09-05',
            'units_sold' => 14,
            'unit_price' => '10000.00',
            'sales_value' => '140000.00',
        ]);
    }
}
