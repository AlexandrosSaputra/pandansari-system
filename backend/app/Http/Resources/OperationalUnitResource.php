<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'scanner_enabled' => $this->scanner_enabled,
            'is_active' => $this->is_active,
            'inventory_location' => $this->whenLoaded('inventoryLocation', fn (): ?array => $this->inventoryLocation ? [
                'id' => $this->inventoryLocation->id,
                'code' => $this->inventoryLocation->code,
                'name' => $this->inventoryLocation->name,
                'type' => $this->inventoryLocation->type->value,
                'is_active' => $this->inventoryLocation->is_active,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
