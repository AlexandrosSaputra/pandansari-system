<?php

namespace App\Http\Resources;

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
                ->map(fn ($result): array => [
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
                    'incoming_quantity' => $result->incoming_quantity,
                    'recommended_restock' => $result->recommended_restock,
                    'mae' => (float) $result->mae,
                    'rmse' => (float) $result->rmse,
                    'wape' => (float) $result->wape,
                ])->all()),
        ];
    }
}
