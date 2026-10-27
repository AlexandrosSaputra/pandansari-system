<?php

namespace App\Http\Resources;

use App\Enums\ItemType;
use App\Enums\StockUrgency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionCatalogResource extends JsonResource
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
        $quantityOnHand = (int) ($this->quantity_on_hand ?? 0);
        $quantityReserved = (int) ($this->quantity_reserved ?? 0);

        return [
            'id' => $this->id,
            'operational_unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type->value,
            ],
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'type' => $item->type->value,
                'uom' => $item->uom,
            ],
            'selling_price' => $this->selling_price,
            'minimum_stock' => $this->minimum_stock,
            'inventory' => $item->type === ItemType::Product ? [
                'quantity_on_hand' => $quantityOnHand,
                'quantity_reserved' => $quantityReserved,
                'available_quantity' => $quantityOnHand - $quantityReserved,
                'stock_urgency' => StockUrgency::forAvailableQuantity(
                    $quantityOnHand - $quantityReserved,
                    (int) $this->minimum_stock,
                )->value,
                'low_stock' => $quantityOnHand <= $this->minimum_stock,
            ] : null,
        ];
    }
}
