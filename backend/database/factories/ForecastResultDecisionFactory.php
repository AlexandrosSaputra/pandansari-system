<?php

namespace Database\Factories;

use App\Enums\ForecastDecisionStatus;
use App\Models\ForecastResult;
use App\Models\ForecastResultDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastResultDecision>
 */
class ForecastResultDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forecast_result_id' => fn (): int => ForecastResult::query()->firstOrFail()->id,
            'status' => ForecastDecisionStatus::Approved,
            'recommended_restock_snapshot' => fn (array $attributes): int => ForecastResult::query()
                ->findOrFail($attributes['forecast_result_id'])
                ->recommended_restock,
            'final_restock_quantity' => fn (array $attributes): int => $attributes['recommended_restock_snapshot'],
            'reason' => null,
            'decided_by' => fn (): int => User::query()->firstOrFail()->id,
            'decided_at' => now(),
        ];
    }
}
