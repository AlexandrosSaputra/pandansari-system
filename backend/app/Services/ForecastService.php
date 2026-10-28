<?php

namespace App\Services;

use App\Models\ForecastRun;
use App\Models\HistoricalSale;
use App\Models\InventoryBalance;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForecastService
{
    public function __construct(private WeeklyForecastModelSelector $modelSelector) {}

    /**
     * @param  array{operational_unit_id: int, horizon: string, target_date?: string|null}  $attributes
     */
    public function generate(User $actor, array $attributes): ForecastRun
    {
        $unit = OperationalUnit::query()->active()->findOrFail($attributes['operational_unit_id']);
        [$forecastStart, $forecastEnd] = $this->resolvePeriod(
            $attributes['horizon'],
            $attributes['target_date'] ?? null,
        );
        $historyThrough = HistoricalSale::query()
            ->where('operational_unit_id', $unit->id)
            ->max('week_end');

        if ($historyThrough === null) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'Unit ini belum memiliki data penjualan historis.',
            ]);
        }

        $historyThrough = CarbonImmutable::parse($historyThrough);

        if ($historyThrough->greaterThanOrEqualTo($forecastEnd)) {
            throw ValidationException::withMessages([
                'target_date' => 'Tanggal target harus berada setelah periode data historis.',
            ]);
        }

        $salesByItem = HistoricalSale::query()
            ->where('operational_unit_id', $unit->id)
            ->orderBy('week_start')
            ->get()
            ->groupBy('item_id');

        if ($salesByItem->isEmpty()) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'Unit ini belum memiliki data penjualan historis.',
            ]);
        }

        $location = $unit->inventoryLocation()->active()->units()->firstOrFail();
        $balances = InventoryBalance::query()
            ->where('inventory_location_id', $location->id)
            ->get()
            ->keyBy('item_id');
        $unitItems = UnitItem::query()
            ->where('operational_unit_id', $unit->id)
            ->whereIn('item_id', $salesByItem->keys())
            ->with('item')
            ->get()
            ->keyBy('item_id');
        $resultRows = [];
        $aggregate = [
            'absolute_error' => 0.0,
            'squared_error' => 0.0,
            'actual' => 0.0,
            'observations' => 0,
            'model_counts' => array_fill_keys(WeeklyForecastModelSelector::MODELS, 0),
            'candidate_metrics' => array_fill_keys(WeeklyForecastModelSelector::MODELS, [
                'absolute_error' => 0.0,
                'squared_error' => 0.0,
                'actual' => 0.0,
                'observations' => 0,
            ]),
        ];

        foreach ($salesByItem as $itemId => $sales) {
            $unitItem = $unitItems->get($itemId);

            if (! $unitItem instanceof UnitItem) {
                continue;
            }

            if ($sales->count() < 8) {
                throw ValidationException::withMessages([
                    'operational_unit_id' => 'Setiap produk membutuhkan sedikitnya delapan minggu data historis.',
                ]);
            }

            $values = $sales->pluck('units_sold')
                ->map(fn (mixed $value): float => (float) $value)
                ->values()
                ->all();
            $evaluation = $this->modelSelector->evaluate($values);
            $predictedUnits = $this->forecastDemand(
                $sales,
                $evaluation['model'],
                $forecastStart,
                $forecastEnd,
            );
            $balance = $balances->get($itemId);
            $available = $balance ? max(0, (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved) : 0;
            $minimumStock = (int) $unitItem->minimum_stock;
            $leadTimeDays = (int) $unitItem->supplier_lead_time_days;
            $orderPackSize = max(1, (int) $unitItem->order_pack_size);
            $unroundedRestock = max(0, $predictedUnits + $minimumStock - $available);
            $recommended = $unroundedRestock === 0
                ? 0
                : (int) (ceil($unroundedRestock / $orderPackSize) * $orderPackSize);

            $resultRows[] = [
                'operational_unit_id' => $unit->id,
                'item_id' => (int) $itemId,
                'model' => $evaluation['model'],
                'history_weeks' => $sales->count(),
                'predicted_units' => $predictedUnits,
                'available_quantity' => $available,
                'minimum_stock' => $minimumStock,
                'supplier_lead_time_days' => $leadTimeDays,
                'order_pack_size' => $orderPackSize,
                'incoming_quantity' => 0,
                'unrounded_restock' => $unroundedRestock,
                'recommended_restock' => $recommended,
                'order_by_date' => $forecastStart->subDays($leadTimeDays)->toDateString(),
                'mae' => $evaluation['mae'],
                'rmse' => $evaluation['rmse'],
                'wape' => $evaluation['wape'],
            ];
            $aggregate['absolute_error'] += $evaluation['absolute_error'];
            $aggregate['squared_error'] += $evaluation['squared_error'];
            $aggregate['actual'] += $evaluation['actual'];
            $aggregate['observations'] += $evaluation['observations'];
            $aggregate['model_counts'][$evaluation['model']]++;

            foreach ($evaluation['candidates'] as $model => $candidate) {
                $aggregate['candidate_metrics'][$model]['absolute_error'] += $candidate['absolute_error'];
                $aggregate['candidate_metrics'][$model]['squared_error'] += $candidate['squared_error'];
                $aggregate['candidate_metrics'][$model]['actual'] += $candidate['actual'];
                $aggregate['candidate_metrics'][$model]['observations'] += $candidate['observations'];
            }
        }

        if ($resultRows === []) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'Data historis tidak cocok dengan katalog aktif unit ini.',
            ]);
        }

        $candidateMetrics = collect($aggregate['candidate_metrics'])
            ->filter(fn (array $candidate): bool => $candidate['observations'] > 0)
            ->map(fn (array $candidate): array => [
                'mae' => round($candidate['absolute_error'] / $candidate['observations'], 4),
                'rmse' => round(sqrt($candidate['squared_error'] / $candidate['observations']), 4),
                'wape' => round(100 * $candidate['absolute_error'] / max(1, $candidate['actual']), 4),
                'observations' => $candidate['observations'],
            ])->all();
        $metrics = [
            'mae' => round($aggregate['absolute_error'] / max(1, $aggregate['observations']), 4),
            'rmse' => round(sqrt($aggregate['squared_error'] / max(1, $aggregate['observations'])), 4),
            'wape' => round(100 * $aggregate['absolute_error'] / max(1, $aggregate['actual']), 4),
            'evaluation_weeks' => 52,
            'model_counts' => $aggregate['model_counts'],
            'candidate_metrics' => $candidateMetrics,
        ];
        $forecastWeeks = (int) $forecastStart->startOfWeek(CarbonImmutable::MONDAY)
            ->diffInWeeks($forecastEnd->startOfWeek(CarbonImmutable::MONDAY)) + 1;

        return DB::transaction(function () use (
            $actor,
            $unit,
            $attributes,
            $forecastStart,
            $forecastEnd,
            $historyThrough,
            $forecastWeeks,
            $metrics,
            $resultRows,
        ): ForecastRun {
            $run = ForecastRun::create([
                'requested_by' => $actor->id,
                'operational_unit_id' => $unit->id,
                'horizon' => $attributes['horizon'],
                'target_date' => $forecastEnd,
                'forecast_start' => $forecastStart,
                'forecast_end' => $forecastEnd,
                'history_through' => $historyThrough,
                'forecast_weeks' => $forecastWeeks,
                'method' => 'per_sku_time_series_selection_v2',
                'metrics' => $metrics,
                'explanation_provider' => config('forecast.explanation.provider', 'none'),
                'explanation_status' => 'pending',
            ]);

            $timestamp = now();
            $run->results()->createMany(array_map(fn (array $row): array => [
                ...$row,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $resultRows));

            return $run->load(['requester', 'operationalUnit', 'results.item']);
        });
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function resolvePeriod(string $horizon, ?string $targetDate): array
    {
        $today = CarbonImmutable::today(config('forecast.timezone'));

        return match ($horizon) {
            'next_week' => [
                $today->next(CarbonImmutable::MONDAY),
                $today->next(CarbonImmutable::MONDAY)->endOfWeek(CarbonImmutable::SUNDAY),
            ],
            'next_month' => [
                $today->addMonthNoOverflow()->startOfMonth(),
                $today->addMonthNoOverflow()->endOfMonth(),
            ],
            'custom' => [$today->addDay(), CarbonImmutable::parse($targetDate)],
            default => throw ValidationException::withMessages(['horizon' => 'Horizon prediksi tidak valid.']),
        };
    }

    /** @param Collection<int, HistoricalSale> $sales */
    private function forecastDemand(
        Collection $sales,
        string $model,
        CarbonImmutable $forecastStart,
        CarbonImmutable $forecastEnd,
    ): int {
        $history = $sales->pluck('units_sold')
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();
        $lastSale = $sales->last();
        $lastHistoricalWeek = $lastSale->week_start->toImmutable();
        $firstForecastWeek = $forecastStart->startOfWeek(CarbonImmutable::MONDAY);
        $lastForecastWeek = $forecastEnd->startOfWeek(CarbonImmutable::MONDAY);
        $steps = (int) $lastHistoricalWeek->diffInWeeks($lastForecastWeek);
        $projected = $this->modelSelector->project($history, $steps, $model);
        $predictions = [];
        $offset = 0;

        for ($week = $lastHistoricalWeek->addWeek(); $week->lessThanOrEqualTo($lastForecastWeek); $week = $week->addWeek()) {
            $predictions[$week->toDateString()] = $projected[$offset];
            $offset++;
        }

        $total = 0.0;

        for ($week = $firstForecastWeek; $week->lessThanOrEqualTo($lastForecastWeek); $week = $week->addWeek()) {
            $weekEnd = $week->endOfWeek(CarbonImmutable::SUNDAY);
            $overlapStart = $week->greaterThan($forecastStart) ? $week : $forecastStart;
            $overlapEnd = $weekEnd->lessThan($forecastEnd) ? $weekEnd : $forecastEnd;
            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $weeklyPrediction = $predictions[$week->toDateString()] ?? (float) $lastSale->units_sold;
            $total += $weeklyPrediction * ($overlapDays / 7);
        }

        return max(0, (int) ceil($total));
    }
}
