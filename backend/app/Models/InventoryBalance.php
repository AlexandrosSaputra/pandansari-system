<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = [
        'inventory_location_id',
        'item_id',
        'quantity_on_hand',
        'quantity_reserved',
    ];

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->quantity_on_hand - $this->quantity_reserved,
        );
    }

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
        ];
    }
}
