<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\HistoricalSale;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UnitItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HistoricalSalesSyncService
{
    public const string SOURCE = 'application-transactions';

    /**
     * @return array{start_week: string|null, through_week: string, weeks: int, series: int, expected_observations: int, rows_synchronized: int, missing_weeks_before: int, missing_observations_before: int, missing_weeks_after: int, missing_observations_after: int, check_only: bool}
     */
    public function sync(?CarbonInterface $through = null, bool $checkOnly = false): array
    {
        $timezone = (string) config('forecast.timezone', 'Asia/Jakarta');
        $throughWeekEnd = $through === null
            ? CarbonImmutable::now($timezone)->startOfWeek(CarbonImmutable::MONDAY)->subDay()
            : CarbonImmutable::parse($through->toDateString(), $timezone)->endOfWeek(CarbonImmutable::SUNDAY);
        $catalog = $this->forecastCatalog();
        $startWeek = $this->resolveStartWeek($timezone);

        if ($catalog->isEmpty() || $startWeek === null || $startWeek->greaterThan($throughWeekEnd)) {
            return $this->emptySummary($throughWeekEnd, $checkOnly, $catalog->count());
        }

        $weeks = $this->weeks($startWeek, $throughWeekEnd);
        $coverageBefore = $this->coverage($catalog, $weeks);
        $rowsSynchronized = 0;

        if (! $checkOnly) {
            $rows = $this->buildRows($catalog, $weeks, $timezone);
            $this->storeRows($rows, $startWeek, $throughWeekEnd);
            $rowsSynchronized = count($rows);
        }

        $coverageAfter = $checkOnly ? $coverageBefore : $this->coverage($catalog, $weeks);

        return [
            'start_week' => $startWeek->toDateString(),
            'through_week' => $throughWeekEnd->toDateString(),
            'weeks' => count($weeks),
            'series' => $catalog->count(),
            'expected_observations' => count($weeks) * $catalog->count(),
            'rows_synchronized' => $rowsSynchronized,
            'missing_weeks_before' => $coverageBefore['weeks'],
            'missing_observations_before' => $coverageBefore['observations'],
            'missing_weeks_after' => $coverageAfter['weeks'],
            'missing_observations_after' => $coverageAfter['observations'],
            'check_only' => $checkOnly,
        ];
    }

    /** @return Collection<int, UnitItem> */
    private function forecastCatalog(): Collection
    {
        $historicalPairs = HistoricalSale::query()
            ->distinct()
            ->get(['operational_unit_id', 'item_id'])
            ->mapWithKeys(fn (HistoricalSale $sale): array => [
                $this->pairKey($sale->operational_unit_id, $sale->item_id) => true,
            ]);

        return UnitItem::query()
            ->active()
            ->whereHas('operationalUnit', fn ($query) => $query->active())
            ->whereHas('item', fn ($query) => $query->products())
            ->with(['operationalUnit', 'item'])
            ->get()
            ->filter(fn (UnitItem $unitItem): bool => $historicalPairs->has(
                $this->pairKey($unitItem->operational_unit_id, $unitItem->item_id),
            ))
            ->values();
    }

    private function resolveStartWeek(string $timezone): ?CarbonImmutable
    {
        $firstApplicationWeek = HistoricalSale::query()
            ->where('source_file', self::SOURCE)
            ->min('week_start');

        if ($firstApplicationWeek !== null) {
            return CarbonImmutable::parse($firstApplicationWeek, $timezone)
                ->startOfWeek(CarbonImmutable::MONDAY);
        }

        $lastImportedWeek = HistoricalSale::query()
            ->where('source_file', '!=', self::SOURCE)
            ->max('week_start');

        if ($lastImportedWeek !== null) {
            return CarbonImmutable::parse($lastImportedWeek, $timezone)
                ->startOfWeek(CarbonImmutable::MONDAY)
                ->addWeek();
        }

        $firstPaidAt = Transaction::query()
            ->where('status', TransactionStatus::Paid->value)
            ->min('paid_at');

        return $firstPaidAt === null
            ? null
            : CarbonImmutable::parse($firstPaidAt)->setTimezone($timezone)
                ->startOfWeek(CarbonImmutable::MONDAY);
    }

    /** @return array<int, CarbonImmutable> */
    private function weeks(CarbonImmutable $start, CarbonImmutable $through): array
    {
        $weeks = [];

        for ($week = $start; $week->lessThanOrEqualTo($through); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, UnitItem>  $catalog
     * @param  array<int, CarbonImmutable>  $weeks
     * @return array{weeks: int, observations: int}
     */
    private function coverage(Collection $catalog, array $weeks): array
    {
        if ($weeks === []) {
            return ['weeks' => 0, 'observations' => 0];
        }

        $existing = HistoricalSale::query()
            ->whereBetween('week_start', [$weeks[0]->toDateString(), end($weeks)->toDateString()])
            ->get(['operational_unit_id', 'item_id', 'week_start'])
            ->mapWithKeys(fn (HistoricalSale $sale): array => [
                $this->observationKey(
                    $sale->operational_unit_id,
                    $sale->item_id,
                    $sale->week_start->toDateString(),
                ) => true,
            ]);
        $missingWeeks = [];
        $missingObservations = 0;

        foreach ($weeks as $week) {
            foreach ($catalog as $unitItem) {
                if (! $existing->has($this->observationKey(
                    $unitItem->operational_unit_id,
                    $unitItem->item_id,
                    $week->toDateString(),
                ))) {
                    $missingObservations++;
                    $missingWeeks[$week->toDateString()] = true;
                }
            }
        }

        return ['weeks' => count($missingWeeks), 'observations' => $missingObservations];
    }

    /**
     * @param  Collection<int, UnitItem>  $catalog
     * @param  array<int, CarbonImmutable>  $weeks
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(Collection $catalog, array $weeks, string $timezone): array
    {
        $metadata = $catalog->mapWithKeys(function (UnitItem $unitItem): array {
            $latest = HistoricalSale::query()
                ->where('operational_unit_id', $unitItem->operational_unit_id)
                ->where('item_id', $unitItem->item_id)
                ->latest('week_start')
                ->firstOrFail();

            return [$this->pairKey($unitItem->operational_unit_id, $unitItem->item_id) => $latest];
        });
        $rows = [];
        $timestamp = now();

        foreach ($weeks as $weekIndex => $week) {
            $aggregates = $this->transactionAggregates($week, $timezone);

            foreach ($catalog as $unitItem) {
                $key = $this->pairKey($unitItem->operational_unit_id, $unitItem->item_id);
                $aggregate = $aggregates->get($key);
                $units = (int) ($aggregate->units_sold ?? 0);
                $salesValue = (float) ($aggregate->sales_value ?? 0);
                /** @var HistoricalSale $latest */
                $latest = $metadata->get($key);
                $unitPrice = $units > 0
                    ? $salesValue / $units
                    : (float) $unitItem->selling_price;

                $rows[] = [
                    'operational_unit_id' => $unitItem->operational_unit_id,
                    'item_id' => $unitItem->item_id,
                    'week_id' => 'APP-'.$week->format('o-\WW'),
                    'week_start' => $week->toDateString(),
                    'week_end' => $week->endOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
                    'source_outlet' => $latest->source_outlet,
                    'source_sku' => $latest->source_sku,
                    'units_sold' => $units,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'sales_value' => number_format($salesValue, 2, '.', ''),
                    'source_file' => self::SOURCE,
                    'source_row' => $weekIndex + 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        return $rows;
    }

    /** @return Collection<string, object> */
    private function transactionAggregates(CarbonImmutable $week, string $timezone): Collection
    {
        $start = $week->setTimezone($timezone)->startOfDay()->utc();
        $end = $week->setTimezone($timezone)->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay()->utc();

        return TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', TransactionStatus::Paid->value)
            ->whereBetween('transactions.paid_at', [$start, $end])
            ->select('transactions.operational_unit_id', 'transaction_items.item_id')
            ->selectRaw('SUM(transaction_items.quantity) as units_sold')
            ->selectRaw('SUM(transaction_items.subtotal) as sales_value')
            ->groupBy('transactions.operational_unit_id', 'transaction_items.item_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $this->pairKey((int) $row->operational_unit_id, (int) $row->item_id) => $row,
            ]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function storeRows(
        array $rows,
        CarbonImmutable $startWeek,
        CarbonImmutable $throughWeekEnd,
    ): void {
        $hasImportedConflict = HistoricalSale::query()
            ->whereBetween('week_start', [$startWeek->toDateString(), $throughWeekEnd->toDateString()])
            ->where('source_file', '!=', self::SOURCE)
            ->exists();

        if ($hasImportedConflict) {
            throw new RuntimeException('Application sales synchronization overlaps imported historical data.');
        }

        DB::transaction(function () use ($rows): void {
            HistoricalSale::query()->upsert(
                $rows,
                ['operational_unit_id', 'item_id', 'week_start'],
                [
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
                ],
            );
        }, 3);
    }

    /**
     * @return array{start_week: null, through_week: string, weeks: int, series: int, expected_observations: int, rows_synchronized: int, missing_weeks_before: int, missing_observations_before: int, missing_weeks_after: int, missing_observations_after: int, check_only: bool}
     */
    private function emptySummary(CarbonImmutable $through, bool $checkOnly, int $series): array
    {
        return [
            'start_week' => null,
            'through_week' => $through->toDateString(),
            'weeks' => 0,
            'series' => $series,
            'expected_observations' => 0,
            'rows_synchronized' => 0,
            'missing_weeks_before' => 0,
            'missing_observations_before' => 0,
            'missing_weeks_after' => 0,
            'missing_observations_after' => 0,
            'check_only' => $checkOnly,
        ];
    }

    private function pairKey(int $unitId, int $itemId): string
    {
        return $unitId.'|'.$itemId;
    }

    private function observationKey(int $unitId, int $itemId, string $week): string
    {
        return $this->pairKey($unitId, $itemId).'|'.$week;
    }
}
