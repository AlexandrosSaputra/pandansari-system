<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseInventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantityOnHand = (int) ($this->quantity_on_hand ?? 0);
        $quantityReserved = (int) ($this->quantity_reserved ?? 0);

        return [
            'item' => [
                'id' => $this->id,
                'item_code' => $this->item_code,
                'barcode' => $this->barcode,
                'name' => $this->name,
                'type' => $this->type->value,
                'uom' => $this->uom,
                'is_active' => $this->is_active,
            ],
            'quantity_on_hand' => $quantityOnHand,
            'quantity_reserved' => $quantityReserved,
            'available_quantity' => $quantityOnHand - $quantityReserved,
            'updated_at' => $this->balance_updated_at
                ? CarbonImmutable::parse($this->balance_updated_at)->toIso8601String()
                : null,
        ];
    }
}
