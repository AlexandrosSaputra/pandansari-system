<?php

namespace App\Models;

use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_location_id',
        'item_id',
        'direction',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'performed_by',
        'note',
    ];

    public const UPDATED_AT = null;

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected function casts(): array
    {
        return [
            'direction' => StockDirection::class,
            'movement_type' => StockMovementType::class,
            'quantity' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
