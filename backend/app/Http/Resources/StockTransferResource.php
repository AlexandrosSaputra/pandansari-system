<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = $this->resource->sourceLocation;
        $destination = $this->resource->destinationLocation;
        $destinationUnit = $destination->operationalUnit;
        $creator = $this->resource->creator;
        $responder = $this->resource->responder;

        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'status' => $this->status->value,
            'source_location' => [
                'id' => $source->id,
                'code' => $source->code,
                'name' => $source->name,
                'type' => $source->type->value,
            ],
            'destination' => [
                'operational_unit' => $destinationUnit ? [
                    'id' => $destinationUnit->id,
                    'code' => $destinationUnit->code,
                    'name' => $destinationUnit->name,
                ] : null,
                'location' => [
                    'id' => $destination->id,
                    'code' => $destination->code,
                    'name' => $destination->name,
                    'type' => $destination->type->value,
                ],
            ],
            'creator' => [
                'id' => $creator->id,
                'name' => $creator->name,
                'email' => $creator->email,
            ],
            'responder' => $responder ? [
                'id' => $responder->id,
                'name' => $responder->name,
                'email' => $responder->email,
            ] : null,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'items' => StockTransferItemResource::collection($this->whenLoaded('transferItems')),
        ];
    }
}
