<?php

namespace App\Console\Commands;

use App\Models\HistoricalSale;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('forecast:import-sales {path=database/data/historical-sales.csv}')]
#[Description('Import the cleaned weekly sales history used by stock forecasting')]
class ImportHistoricalSales extends Command
{
    private const array EXPECTED_HEADERS = [
        'week_id',
        'week_start',
        'week_end',
        'outlet',
        'sku',
        'units_sold',
        'unit_price',
        'sales_value',
    ];

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path) || ! is_readable($path)) {
            $this->components->error("Historical sales file is not readable: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
            $this->importRows($rows, basename($path));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Imported %d weekly sales rows covering %d weeks and %d products.',
            count($rows),
            collect($rows)->pluck('week_start')->unique()->count(),
            collect($rows)->pluck('item_id')->unique()->count(),
        ));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open historical sales file: {$path}");
        }

        try {
            $headers = fgetcsv($handle, escape: '');

            if ($headers !== self::EXPECTED_HEADERS) {
                throw new RuntimeException('Historical sales CSV headers do not match the expected canonical format.');
            }

            $catalog = $this->catalogMappings();
            $rows = [];
            $sourceRow = 1;
            $seen = [];

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                $sourceRow++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count(self::EXPECTED_HEADERS)) {
                    throw new RuntimeException("Row {$sourceRow} does not contain the expected number of columns.");
                }

                $row = array_combine(self::EXPECTED_HEADERS, $values);
                $outlet = trim((string) $row['outlet']);
                $sku = trim((string) $row['sku']);
                $mappingKey = $outlet.'|'.$sku;
                $mapping = $catalog[$mappingKey] ?? null;

                if ($mapping === null) {
                    throw new RuntimeException("Row {$sourceRow} cannot map outlet '{$outlet}' and SKU '{$sku}' to the application catalog.");
                }

                $weekStart = $this->parseDate((string) $row['week_start'], $sourceRow, 'week_start');
                $weekEnd = $this->parseDate((string) $row['week_end'], $sourceRow, 'week_end');

                if ($weekStart->dayOfWeek !== CarbonImmutable::MONDAY || $weekEnd->dayOfWeek !== CarbonImmutable::SUNDAY || (int) $weekStart->diffInDays($weekEnd) !== 6) {
                    throw new RuntimeException("Row {$sourceRow} is not a complete Monday-Sunday week.");
                }

                $units = filter_var($row['units_sold'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                $unitPrice = filter_var($row['unit_price'], FILTER_VALIDATE_FLOAT);
                $salesValue = filter_var($row['sales_value'], FILTER_VALIDATE_FLOAT);

                if ($units === false || $unitPrice === false || $salesValue === false || $unitPrice < 0 || $salesValue < 0) {
                    throw new RuntimeException("Row {$sourceRow} contains an invalid quantity or monetary value.");
                }

                if (abs(($units * $unitPrice) - $salesValue) > 0.01) {
                    throw new RuntimeException("Row {$sourceRow} does not satisfy units_sold × unit_price = sales_value.");
                }

                $uniqueKey = $mapping['operational_unit_id'].'|'.$mapping['item_id'].'|'.$weekStart->toDateString();

                if (isset($seen[$uniqueKey])) {
                    throw new RuntimeException("Row {$sourceRow} duplicates an outlet, SKU, and week combination.");
                }

                $seen[$uniqueKey] = true;
                $rows[] = [
                    ...$mapping,
                    'week_id' => trim((string) $row['week_id']),
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'source_outlet' => $outlet,
                    'source_sku' => $sku,
                    'units_sold' => $units,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'sales_value' => number_format($salesValue, 2, '.', ''),
                    'source_row' => $sourceRow,
                ];
            }

            if ($rows === []) {
                throw new RuntimeException('Historical sales CSV contains no data rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return array<string, array{operational_unit_id: int, item_id: int}> */
    private function catalogMappings(): array
    {
        /** @var array<string, string> $outletMap */
        $outletMap = config('forecast.outlet_map', []);
        $units = OperationalUnit::query()->whereIn('name', array_values($outletMap))->get()->keyBy('name');
        $items = Item::query()->get()->keyBy('name');
        $unitItemPairs = UnitItem::query()
            ->whereIn('operational_unit_id', $units->pluck('id'))
            ->get(['operational_unit_id', 'item_id'])
            ->keyBy(fn (UnitItem $unitItem): string => $unitItem->operational_unit_id.'|'.$unitItem->item_id);
        $mappings = [];

        foreach ($outletMap as $sourceOutlet => $unitName) {
            $unit = $units->get($unitName);

            if (! $unit instanceof OperationalUnit) {
                throw new RuntimeException("Operational unit '{$unitName}' must exist before importing historical sales.");
            }

            foreach ($items as $item) {
                if (! $unitItemPairs->has($unit->id.'|'.$item->id)) {
                    continue;
                }

                $mappings[$sourceOutlet.'|'.$item->name] = [
                    'operational_unit_id' => $unit->id,
                    'item_id' => $item->id,
                ];
            }
        }

        return $mappings;
    }

    private function parseDate(string $value, int $row, string $column): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', trim($value));

        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw new RuntimeException("Row {$row} contains an invalid {$column} date.");
        }

        return $date;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function importRows(array $rows, string $sourceFile): void
    {
        $timestamp = now();
        $updates = [
            'week_id',
            'week_end',
            'source_outlet',
            'source_sku',
            'units_sold',
            'unit_price',
            'sales_value',
            'source_file',
            'source_row',
            'updated_at',
        ];

        DB::transaction(function () use ($rows, $sourceFile, $timestamp, $updates): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $values = array_map(fn (array $row): array => [
                    ...$row,
                    'source_file' => $sourceFile,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $chunk);

                HistoricalSale::query()->upsert(
                    $values,
                    ['operational_unit_id', 'item_id', 'week_start'],
                    $updates,
                );
            }
        });
    }
}
