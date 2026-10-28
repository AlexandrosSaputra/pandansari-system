<?php

namespace App\Services;

use App\Enums\SafetyStockMode;
use App\Models\ForecastRun;
use App\Models\HistoricalSale;
use App\Models\InventoryBalance;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ForecastService
{
    private bool $aiServiceUnavailable = false;

    public function __construct(
        private WeeklyForecastModelSelector $modelSelector,
        private SafetyStockCalculator $safetyStockCalculator,
        private AiForecastClient $aiForecastClient,
    ) {}

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
            'prediction_provider_counts' => [
                'local' => 0,
                'ai_service' => 0,
                'local_fallback' => 0,
            ],
            'active_model_metrics' => null,
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
            $projection = $this->forecastDemand(
                $sales,
                $evaluation['model'],
                $forecastStart,
                $forecastEnd,
            );
            $predictedUnits = $projection['units'];
            $projectionMetrics = $projection['metrics'] ?? [
                'mae' => $evaluation['mae'],
                'rmse' => $evaluation['rmse'],
                'wape' => $evaluation['wape'],
            ];
            $balance = $balances->get($itemId);
            $available = $balance ? max(0, (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved) : 0;
            $minimumStock = (int) $unitItem->minimum_stock;
            $leadTimeDays = (int) $unitItem->supplier_lead_time_days;
            $orderPackSize = max(1, (int) $unitItem->order_pack_size);
            $safetyStockMode = $unitItem->safety_stock_mode;
            $targetServiceLevel = (float) $unitItem->target_service_level;
            $calculatedSafetyStock = $this->safetyStockCalculator->calculate(
                $projectionMetrics['rmse'],
                $leadTimeDays,
                $targetServiceLevel,
            );
            $safetyStock = $safetyStockMode === SafetyStockMode::Calculated
                ? $calculatedSafetyStock
                : $minimumStock;
            $unroundedRestock = max(0, $predictedUnits + $safetyStock - $available);
            $recommended = $unroundedRestock === 0
                ? 0
                : (int) (ceil($unroundedRestock / $orderPackSize) * $orderPackSize);

            $resultRows[] = [
                'operational_unit_id' => $unit->id,
                'item_id' => (int) $itemId,
                'model' => $projection['model'],
                'history_weeks' => $sales->count(),
                'predicted_units' => $predictedUnits,
                'available_quantity' => $available,
                'minimum_stock' => $minimumStock,
                'safety_stock_mode' => $safetyStockMode,
                'target_service_level' => $targetServiceLevel,
                'calculated_safety_stock' => $calculatedSafetyStock,
                'safety_stock' => $safetyStock,
                'supplier_lead_time_days' => $leadTimeDays,
                'order_pack_size' => $orderPackSize,
                'incoming_quantity' => 0,
                'unrounded_restock' => $unroundedRestock,
                'recommended_restock' => $recommended,
                'order_by_date' => $forecastStart->subDays($leadTimeDays)->toDateString(),
                'mae' => $projectionMetrics['mae'],
                'rmse' => $projectionMetrics['rmse'],
                'wape' => $projectionMetrics['wape'],
            ];
            $aggregate['absolute_error'] += $evaluation['absolute_error'];
            $aggregate['squared_error'] += $evaluation['squared_error'];
            $aggregate['actual'] += $evaluation['actual'];
            $aggregate['observations'] += $evaluation['observations'];
            $aggregate['model_counts'][$projection['model']] = ($aggregate['model_counts'][$projection['model']] ?? 0) + 1;
            $aggregate['prediction_provider_counts'][$projection['provider']]++;
            if ($projection['provider'] === 'ai_service') {
                $aggregate['active_model_metrics'] = $projectionMetrics;
            }

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
        $reportedMetrics = [
            'mae' => round($aggregate['absolute_error'] / max(1, $aggregate['observations']), 4),
            'rmse' => round(sqrt($aggregate['squared_error'] / max(1, $aggregate['observations'])), 4),
            'wape' => round(100 * $aggregate['absolute_error'] / max(1, $aggregate['actual']), 4),
        ];
        $metricSource = 'local_holdout';

        if ($aggregate['prediction_provider_counts']['ai_service'] === count($resultRows)
            && is_array($aggregate['active_model_metrics'])) {
            $reportedMetrics = $aggregate['active_model_metrics'];
            $metricSource = 'extra_trees_test_set';
        }

        $metrics = [
            'mae' => round($reportedMetrics['mae'], 4),
            'rmse' => round($reportedMetrics['rmse'], 4),
            'wape' => round($reportedMetrics['wape'], 4),
            'evaluation_weeks' => 52,
            'metric_source' => $metricSource,
            'model_counts' => $aggregate['model_counts'],
            'candidate_metrics' => $candidateMetrics,
            'prediction_provider_requested' => config('forecast.prediction.provider', 'local'),
            'prediction_provider_counts' => $aggregate['prediction_provider_counts'],
        ];
        $forecastWeeks = (int) $forecastStart->startOfWeek(CarbonImmutable::MONDAY)
            ->diffInWeeks($forecastEnd->startOfWeek(CarbonImmutable::MONDAY)) + 1;
        $method = $this->forecastMethod($aggregate['prediction_provider_counts'], count($resultRows));

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
            $method,
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
                'method' => $method,
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
     * @return array{units: int, model: string, provider: string, metrics: array{mae: float, rmse: float, wape: float}|null}
     */
    private function forecastDemand(
        Collection $sales,
        string $model,
        CarbonImmutable $forecastStart,
        CarbonImmutable $forecastEnd,
    ): array {
        $history = $sales->pluck('units_sold')
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();
        $lastSale = $sales->last();
        $lastHistoricalWeek = CarbonImmutable::parse(
            $lastSale->week_start->toDateString(),
            config('forecast.timezone'),
        );
        $periodStart = CarbonImmutable::parse($forecastStart->toDateString(), config('forecast.timezone'));
        $periodEnd = CarbonImmutable::parse($forecastEnd->toDateString(), config('forecast.timezone'));
        $firstForecastWeek = $periodStart->startOfWeek(CarbonImmutable::MONDAY);
        $lastForecastWeek = $periodEnd->startOfWeek(CarbonImmutable::MONDAY);
        $steps = (int) $lastHistoricalWeek->diffInWeeks($lastForecastWeek);
        $provider = 'local';
        $projectionModel = $model;
        $projectionMetrics = null;

        if (config('forecast.prediction.provider', 'local') === 'ai_service' && ! $this->aiServiceUnavailable) {
            try {
                $aiProjection = $this->aiForecastClient->project($sales, $steps);
                $projected = $aiProjection['predictions'];
                $projectionModel = $aiProjection['model_version'];
                $projectionMetrics = $aiProjection['metrics'];
                $provider = 'ai_service';
            } catch (Throwable $error) {
                $this->aiServiceUnavailable = true;
                Log::warning('AI forecast service failed; using local time-series fallback.', [
                    'operational_unit_id' => $sales->first()->operational_unit_id,
                    'item_id' => $sales->first()->item_id,
                    'error' => $error->getMessage(),
                ]);
                $projected = $this->modelSelector->project($history, $steps, $model);
                $provider = 'local_fallback';
            }
        } elseif (config('forecast.prediction.provider', 'local') === 'ai_service') {
            $projected = $this->modelSelector->project($history, $steps, $model);
            $provider = 'local_fallback';
        } else {
            $projected = $this->modelSelector->project($history, $steps, $model);
        }
        $predictions = [];
        $offset = 0;

        for ($week = $lastHistoricalWeek->addWeek(); $week->lessThanOrEqualTo($lastForecastWeek); $week = $week->addWeek()) {
            $predictions[$week->toDateString()] = $projected[$offset];
            $offset++;
        }

        $total = 0.0;

        for ($week = $firstForecastWeek; $week->lessThanOrEqualTo($lastForecastWeek); $week = $week->addWeek()) {
            $weekEnd = $week->endOfWeek(CarbonImmutable::SUNDAY);
            $overlapStart = $week->greaterThan($periodStart) ? $week : $periodStart;
            $overlapEnd = $weekEnd->lessThan($periodEnd) ? $weekEnd : $periodEnd;
            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $weeklyPrediction = $predictions[$week->toDateString()] ?? (float) $lastSale->units_sold;
            $total += $weeklyPrediction * ($overlapDays / 7);
        }

        return [
            'units' => max(0, (int) ceil($total)),
            'model' => $projectionModel,
            'provider' => $provider,
            'metrics' => $projectionMetrics,
        ];
    }

    /** @param array{local: int, ai_service: int, local_fallback: int} $providerCounts */
    private function forecastMethod(array $providerCounts, int $results): string
    {
        if ($providerCounts['ai_service'] === $results) {
            return 'extra_trees_ai_service_v1';
        }
        if ($providerCounts['ai_service'] > 0 && $providerCounts['local_fallback'] > 0) {
            return 'hybrid_ai_service_fallback_v1';
        }
        if ($providerCounts['local_fallback'] > 0) {
            return 'local_time_series_ai_fallback_v1';
        }

        return 'per_sku_time_series_selection_v2';
    }
}
