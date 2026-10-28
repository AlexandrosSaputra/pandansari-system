<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource->item;
        $unit = $this->resource->operationalUnit;

        return [
            'id' => $this->id,
            'operational_unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ],
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'type' => $item->type->value,
            ],
            'selling_price' => $this->selling_price,
            'minimum_stock' => $this->minimum_stock,
            'supplier_lead_time_days' => $this->supplier_lead_time_days,
            'order_pack_size' => $this->order_pack_size,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
