<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource->item;

        return [
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'uom' => $item->uom,
            ],
            'quantity' => $this->quantity,
        ];
    }
}
