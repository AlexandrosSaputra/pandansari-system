<?php

namespace Database\Factories;

use App\Models\ForecastRun;
use App\Models\OperationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastRun>
 */
class ForecastRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek()->startOfWeek();

        return [
            'requested_by' => fn (): int => User::query()->firstOrFail()->id,
            'operational_unit_id' => fn (): int => OperationalUnit::query()->firstOrFail()->id,
            'horizon' => 'next_week',
            'target_date' => $start->copy()->endOfWeek(),
            'forecast_start' => $start,
            'forecast_end' => $start->copy()->endOfWeek(),
            'history_through' => now()->subWeek()->endOfWeek(),
            'forecast_weeks' => 1,
            'method' => 'per_sku_time_series_selection_v2',
            'metrics' => ['mae' => 1, 'rmse' => 1, 'wape' => 5, 'evaluation_weeks' => 52],
            'explanation_provider' => 'none',
            'explanation_status' => 'disabled',
            'explanation' => null,
        ];
    }
}
