<?php

namespace Database\Factories;

use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastResult>
 */
class ForecastResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forecast_run_id' => fn (): int => ForecastRun::query()->firstOrFail()->id,
            'operational_unit_id' => fn (array $attributes): int => ForecastRun::query()->findOrFail($attributes['forecast_run_id'])->operational_unit_id,
            'item_id' => fn (): int => Item::query()->products()->firstOrFail()->id,
            'model' => 'seasonal_52',
            'history_weeks' => 208,
            'predicted_units' => 20,
            'available_quantity' => 10,
            'minimum_stock' => 5,
            'incoming_quantity' => 0,
            'recommended_restock' => 15,
            'mae' => 3,
            'rmse' => 4,
            'wape' => 15,
        ];
    }
}
