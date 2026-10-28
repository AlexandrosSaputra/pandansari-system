<?php

namespace App\Services;

use InvalidArgumentException;

class WeeklyForecastModelSelector
{
    /** @var array<int, string> */
    public const array MODELS = [
        'lag_1',
        'moving_average_4',
        'seasonal_52',
        'seasonal_average_3',
    ];

    /**
     * @param  array<int, float>  $values
     * @return array{model: string, mae: float, rmse: float, wape: float, absolute_error: float, squared_error: float, actual: float, observations: int, candidates: array<string, array{model: string, mae: float, rmse: float, wape: float, absolute_error: float, squared_error: float, actual: float, observations: int}>}
     */
    public function evaluate(array $values): array
    {
        $count = count($values);

        if ($count < 8) {
            throw new InvalidArgumentException('At least eight weeks of history are required.');
        }

        $models = ['lag_1', 'moving_average_4'];
        $start = max(4, $count - 52);

        if ($count >= 104) {
            $models[] = 'seasonal_52';
            $start = max(52, $count - 52);
        }

        if ($count >= 208) {
            $models[] = 'seasonal_average_3';
            $start = max(156, $count - 52);
        }

        $evaluations = [];

        foreach ($models as $model) {
            $absoluteError = 0.0;
            $squaredError = 0.0;
            $actualTotal = 0.0;
            $observations = 0;

            for ($index = $start; $index < $count; $index++) {
                $prediction = $this->predictAt($values, $index, $model);
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

        $selected = collect($evaluations)->sortBy(['mae', 'rmse'])->first();

        return [...$selected, 'candidates' => $evaluations];
    }

    /**
     * @param  array<int, float>  $history
     * @return array<int, float>
     */
    public function project(array $history, int $steps, string $model): array
    {
        if (! in_array($model, self::MODELS, true)) {
            throw new InvalidArgumentException("Unsupported weekly forecast model: {$model}");
        }

        $series = array_values($history);
        $predictions = [];

        for ($step = 0; $step < $steps; $step++) {
            $prediction = $this->predictAt($series, count($series), $model);
            $series[] = $prediction;
            $predictions[] = $prediction;
        }

        return $predictions;
    }

    /** @param array<int, float> $values */
    private function predictAt(array $values, int $index, string $model): float
    {
        return match ($model) {
            'lag_1' => $values[$index - 1],
            'moving_average_4' => array_sum(array_slice($values, $index - 4, 4)) / 4,
            'seasonal_52' => $values[$index - 52],
            'seasonal_average_3' => (
                $values[$index - 52]
                + $values[$index - 104]
                + $values[$index - 156]
            ) / 3,
            default => throw new InvalidArgumentException("Unsupported weekly forecast model: {$model}"),
        };
    }
}
