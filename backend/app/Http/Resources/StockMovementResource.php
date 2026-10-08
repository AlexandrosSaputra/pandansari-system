<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->resource->inventoryLocation;
        $item = $this->resource->item;
        $performer = $this->resource->performer;

        return [
            'id' => $this->id,
            'location' => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'type' => $location->type->value,
                'operational_unit_id' => $location->operational_unit_id,
            ],
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'barcode' => $item->barcode,
                'name' => $item->name,
            ],
            'direction' => $this->direction->value,
            'movement_type' => $this->movement_type->value,
            'quantity' => $this->quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'performer' => [
                'id' => $performer->id,
                'name' => $performer->name,
                'email' => $performer->email,
            ],
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
