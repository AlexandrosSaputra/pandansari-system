<?php

namespace App\Http\Resources;

use App\Enums\ForecastDecisionStatus;
use App\Models\ForecastResult;
use App\Models\ForecastResultDecision;
use App\Support\ForecastDataFreshness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->resource->operationalUnit;
        $requester = $this->resource->requester;

        return [
            'id' => $this->id,
            'operational_unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type->value,
            ],
            'requester' => [
                'id' => $requester->id,
                'name' => $requester->name,
                'email' => $requester->email,
            ],
            'horizon' => $this->horizon,
            'target_date' => $this->target_date->toDateString(),
            'forecast_start' => $this->forecast_start->toDateString(),
            'forecast_end' => $this->forecast_end->toDateString(),
            'history_through' => $this->history_through->toDateString(),
            'data_freshness' => app(ForecastDataFreshness::class)->forRun($this->resource),
            'forecast_weeks' => $this->forecast_weeks,
            'method' => $this->method,
            'metrics' => $this->metrics,
            'explanation_provider' => $this->explanation_provider,
            'explanation_status' => $this->explanation_status,
            'explanation' => $this->explanation,
            'created_at' => $this->created_at?->toIso8601String(),
            'results' => $this->whenLoaded('results', fn (): array => $this->results
                ->sortByDesc('recommended_restock')
                ->values()
                ->map(function ($result): array {
                    $currentDecision = $result->decisions->first();

                    return [
                        'id' => $result->id,
                        'item' => [
                            'id' => $result->item->id,
                            'item_code' => $result->item->item_code,
                            'name' => $result->item->name,
                            'uom' => $result->item->uom,
                        ],
                        'model' => $result->model,
                        'history_weeks' => $result->history_weeks,
                        'predicted_units' => $result->predicted_units,
                        'available_quantity' => $result->available_quantity,
                        'minimum_stock' => $result->minimum_stock,
                        'safety_stock_mode' => $result->safety_stock_mode->value,
                        'target_service_level' => (float) $result->target_service_level,
                        'calculated_safety_stock' => $result->calculated_safety_stock,
                        'safety_stock' => $result->safety_stock,
                        'supplier_lead_time_days' => $result->supplier_lead_time_days,
                        'order_pack_size' => $result->order_pack_size,
                        'incoming_quantity' => $result->incoming_quantity,
                        'unrounded_restock' => $result->unrounded_restock ?? $result->recommended_restock,
                        'recommended_restock' => $result->recommended_restock,
                        'decision_status' => $currentDecision?->status->value ?? ForecastDecisionStatus::Pending->value,
                        'final_restock_quantity' => $currentDecision?->final_restock_quantity,
                        'decision_reason' => $currentDecision?->reason,
                        'decided_by' => $currentDecision === null ? null : [
                            'id' => $currentDecision->decisionMaker->id,
                            'name' => $currentDecision->decisionMaker->name,
                            'email' => $currentDecision->decisionMaker->email,
                        ],
                        'decided_at' => $currentDecision?->decided_at?->toIso8601String(),
                        'decision_history' => $result->decisions
                            ->map(fn (ForecastResultDecision $decision): array => $this->decision($decision))
                            ->all(),
                        'order_by_date' => $result->order_by_date?->toDateString(),
                        'order_status' => $this->orderStatus($result),
                        'mae' => (float) $result->mae,
                        'rmse' => (float) $result->rmse,
                        'wape' => (float) $result->wape,
                    ];
                })->all()),
        ];
    }

    /** @return array<string, mixed> */
    private function decision(ForecastResultDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'status' => $decision->status->value,
            'recommended_restock_snapshot' => $decision->recommended_restock_snapshot,
            'final_restock_quantity' => $decision->final_restock_quantity,
            'reason' => $decision->reason,
            'decided_by' => [
                'id' => $decision->decisionMaker->id,
                'name' => $decision->decisionMaker->name,
                'email' => $decision->decisionMaker->email,
            ],
            'decided_at' => $decision->decided_at->toIso8601String(),
        ];
    }

    private function orderStatus(ForecastResult $result): string
    {
        if ($result->recommended_restock === 0) {
            return 'not_required';
        }

        if ($result->order_by_date === null || $this->created_at === null) {
            return 'unknown';
        }

        $orderBy = $result->order_by_date->startOfDay();
        $createdAt = $this->created_at->startOfDay();

        if ($orderBy->lessThan($createdAt)) {
            return 'overdue';
        }

        return $orderBy->equalTo($createdAt) ? 'due_today' : 'planned';
    }
}
