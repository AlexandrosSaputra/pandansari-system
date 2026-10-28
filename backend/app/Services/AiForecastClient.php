<?php

namespace App\Services;

use App\Models\HistoricalSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiForecastClient
{
    /**
     * @param  Collection<int, HistoricalSale>  $sales
     * @return array{model_version: string, metrics: array{mae: float, rmse: float, wape: float}, predictions: array<int, float>}
     */
    public function project(Collection $sales, int $steps): array
    {
        $baseUrl = rtrim((string) config('forecast.prediction.ai_service.base_url'), '/');
        $response = Http::acceptJson()
            ->timeout((int) config('forecast.prediction.ai_service.timeout', 10))
            ->post($baseUrl.'/v1/forecasts/weekly', [
                'outlet' => (string) $sales->firstOrFail()->source_outlet,
                'sku' => (string) $sales->firstOrFail()->source_sku,
                'week_starts' => $sales->pluck('week_start')
                    ->map(fn (mixed $week): string => $week->toDateString())
                    ->values()
                    ->all(),
                'units_sold' => $sales->pluck('units_sold')
                    ->map(fn (mixed $units): float => (float) $units)
                    ->values()
                    ->all(),
                'steps' => $steps,
            ]);

        $response->throw();
        $modelVersion = $response->json('model_version');
        $metrics = $response->json('evaluation_metrics');
        $predictions = $response->json('predictions');

        if (! is_string($modelVersion) || $modelVersion === '' || strlen($modelVersion) > 32) {
            throw new RuntimeException('AI service returned an invalid model version.');
        }
        if (! is_array($predictions) || count($predictions) !== $steps) {
            throw new RuntimeException('AI service returned an unexpected number of predictions.');
        }
        if (! is_array($metrics)
            || ! is_numeric($metrics['mae'] ?? null)
            || ! is_numeric($metrics['rmse'] ?? null)
            || ! is_numeric($metrics['wape'] ?? null)
            || (float) $metrics['mae'] < 0
            || (float) $metrics['rmse'] < 0
            || (float) $metrics['wape'] < 0) {
            throw new RuntimeException('AI service returned invalid evaluation metrics.');
        }

        $lastWeek = $sales->last()->week_start->toImmutable();
        $values = [];

        foreach (array_values($predictions) as $index => $prediction) {
            $expectedWeek = $lastWeek->addWeeks($index + 1)->toDateString();
            if (! is_array($prediction)
                || ($prediction['week_start'] ?? null) !== $expectedWeek
                || ! is_numeric($prediction['units'] ?? null)
                || (float) $prediction['units'] < 0) {
                throw new RuntimeException('AI service returned an invalid weekly prediction.');
            }
            $values[] = (float) $prediction['units'];
        }

        return [
            'model_version' => $modelVersion,
            'metrics' => [
                'mae' => (float) $metrics['mae'],
                'rmse' => (float) $metrics['rmse'],
                'wape' => (float) $metrics['wape'],
            ],
            'predictions' => $values,
        ];
    }
}
