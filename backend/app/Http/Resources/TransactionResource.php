<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->resource->operationalUnit;
        $creator = $this->resource->creator;
        $payer = $this->resource->payer;

        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'operational_unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type->value,
            ],
            'status' => $this->status->value,
            'creator' => [
                'id' => $creator->id,
                'name' => $creator->name,
                'email' => $creator->email,
            ],
            'payer' => $payer ? [
                'id' => $payer->id,
                'name' => $payer->name,
                'email' => $payer->email,
            ] : null,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'visitor_count' => $this->visitor_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'items' => TransactionItemResource::collection($this->whenLoaded('transactionItems')),
        ];
    }
}
