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
            'model_counts' => ['lag_1' => 0, 'seasonal_52' => 0],
        ];

        foreach ($salesByItem as $itemId => $sales) {
            $unitItem = $unitItems->get($itemId);

            if (! $unitItem instanceof UnitItem) {
                continue;
            }

            $evaluation = $this->evaluateModels($sales);
            $predictedUnits = $this->forecastDemand(
                $sales,
                $evaluation['model'],
                $forecastStart,
                $forecastEnd,
            );
            $balance = $balances->get($itemId);
            $available = $balance ? max(0, (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved) : 0;
            $minimumStock = (int) $unitItem->minimum_stock;
            $recommended = max(0, $predictedUnits + $minimumStock - $available);

            $resultRows[] = [
                'operational_unit_id' => $unit->id,
                'item_id' => (int) $itemId,
                'model' => $evaluation['model'],
                'history_weeks' => $sales->count(),
                'predicted_units' => $predictedUnits,
                'available_quantity' => $available,
                'minimum_stock' => $minimumStock,
                'incoming_quantity' => 0,
                'recommended_restock' => $recommended,
                'mae' => $evaluation['mae'],
                'rmse' => $evaluation['rmse'],
                'wape' => $evaluation['wape'],
            ];
            $aggregate['absolute_error'] += $evaluation['absolute_error'];
            $aggregate['squared_error'] += $evaluation['squared_error'];
            $aggregate['actual'] += $evaluation['actual'];
            $aggregate['observations'] += $evaluation['observations'];
            $aggregate['model_counts'][$evaluation['model']]++;
        }

        if ($resultRows === []) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'Data historis tidak cocok dengan katalog aktif unit ini.',
            ]);
        }

        $metrics = [
            'mae' => round($aggregate['absolute_error'] / max(1, $aggregate['observations']), 4),
            'rmse' => round(sqrt($aggregate['squared_error'] / max(1, $aggregate['observations'])), 4),
            'wape' => round(100 * $aggregate['absolute_error'] / max(1, $aggregate['actual']), 4),
            'evaluation_weeks' => 52,
            'model_counts' => $aggregate['model_counts'],
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
                'method' => 'per_sku_baseline_selection',
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

    /**
     * @param  Collection<int, HistoricalSale>  $sales
     * @return array{model: string, mae: float, rmse: float, wape: float, absolute_error: float, squared_error: float, actual: float, observations: int}
     */
    private function evaluateModels(Collection $sales): array
    {
        $values = $sales->pluck('units_sold')->map(fn (mixed $value): float => (float) $value)->values()->all();
        $count = count($values);

        if ($count < 8) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'Setiap produk membutuhkan sedikitnya delapan minggu data historis.',
            ]);
        }

        $start = max(1, $count - 52);
        $models = ['lag_1'];

        if ($count >= 104) {
            $models[] = 'seasonal_52';
            $start = max(52, $count - 52);
        }

        $evaluations = [];

        foreach ($models as $model) {
            $absoluteError = 0.0;
            $squaredError = 0.0;
            $actualTotal = 0.0;
            $observations = 0;

            for ($index = $start; $index < $count; $index++) {
                $prediction = $model === 'seasonal_52'
                    ? $values[$index - 52]
                    : $values[$index - 1];
                $error = $values[$index] - $prediction;
                $absoluteError += abs($error);
                $squaredError += $error ** 2;
                $actualTotal += $values[$index];
                $observations++;
            }

            $evaluations[$model] = [
                'model' => $model,
                'mae' => round($absoluteError / max(1, $observations), 4),
                'rmse' => round(sqrt($squaredError / max(1, $observations)), 4),
                'wape' => round(100 * $absoluteError / max(1, $actualTotal), 4),
                'absolute_error' => $absoluteError,
                'squared_error' => $squaredError,
                'actual' => $actualTotal,
                'observations' => $observations,
            ];
        }

        return collect($evaluations)->sortBy(['mae', 'rmse'])->first();
    }

    /** @param Collection<int, HistoricalSale> $sales */
    private function forecastDemand(
        Collection $sales,
        string $model,
        CarbonImmutable $forecastStart,
        CarbonImmutable $forecastEnd,
    ): int {
        $actual = $sales->mapWithKeys(fn (HistoricalSale $sale): array => [
            $sale->week_start->toDateString() => (float) $sale->units_sold,
        ])->all();
        $lastSale = $sales->last();
        $lastHistoricalWeek = $lastSale->week_start->toImmutable();
        $firstForecastWeek = $forecastStart->startOfWeek(CarbonImmutable::MONDAY);
        $lastForecastWeek = $forecastEnd->startOfWeek(CarbonImmutable::MONDAY);
        $predictions = [];

        for ($week = $lastHistoricalWeek->addWeek(); $week->lessThanOrEqualTo($lastForecastWeek); $week = $week->addWeek()) {
            $key = $week->toDateString();

            if ($model === 'seasonal_52') {
                $seasonalKey = $week->subWeeks(52)->toDateString();
                $predictions[$key] = $actual[$seasonalKey]
                    ?? $predictions[$seasonalKey]
                    ?? (float) $lastSale->units_sold;
            } else {
                $previousKey = $week->subWeek()->toDateString();
                $predictions[$key] = $actual[$previousKey]
                    ?? $predictions[$previousKey]
                    ?? (float) $lastSale->units_sold;
            }
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
