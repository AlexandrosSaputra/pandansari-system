<?php

namespace App\Services;

use App\Enums\ForecastDecisionStatus;
use App\Models\ForecastResult;
use App\Models\ForecastResultDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ForecastDecisionService
{
    public function __construct(private ActivityLogger $logger) {}

    public function decide(
        User $actor,
        ForecastResult $forecastResult,
        ForecastDecisionStatus $status,
        ?int $finalRestockQuantity,
        ?string $reason,
    ): ForecastResultDecision {
        return DB::transaction(function () use ($actor, $forecastResult, $status, $finalRestockQuantity, $reason): ForecastResultDecision {
            $forecastResult = ForecastResult::query()
                ->whereKey($forecastResult->id)
                ->lockForUpdate()
                ->firstOrFail();

            $decision = ForecastResultDecision::create([
                'forecast_result_id' => $forecastResult->id,
                'status' => $status,
                'recommended_restock_snapshot' => $forecastResult->recommended_restock,
                'final_restock_quantity' => $status === ForecastDecisionStatus::Approved
                    ? $finalRestockQuantity
                    : null,
                'reason' => $reason,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            $this->logger->log($actor, 'FORECAST_RECOMMENDATION_'.$status->value, $decision, [
                'forecast_run_id' => $forecastResult->forecast_run_id,
                'forecast_result_id' => $forecastResult->id,
                'operational_unit_id' => $forecastResult->operational_unit_id,
                'item_id' => $forecastResult->item_id,
                'recommended_restock' => $forecastResult->recommended_restock,
                'final_restock_quantity' => $decision->final_restock_quantity,
                'reason' => $reason,
            ]);

            return $decision->load(['decisionMaker', 'forecastResult']);
        }, 3);
    }
}
