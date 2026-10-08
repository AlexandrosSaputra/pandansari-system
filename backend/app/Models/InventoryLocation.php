<?php

namespace App\Models;

use App\Enums\InventoryLocationType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'operational_unit_id',
        'is_active',
        'created_by',
    ];

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_location_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_location_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function warehouse(Builder $query): Builder
    {
        return $query->where('type', InventoryLocationType::Warehouse->value);
    }

    #[Scope]
    protected function units(Builder $query): Builder
    {
        return $query->where('type', InventoryLocationType::Unit->value);
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryLocationType::class,
            'is_active' => 'boolean',
        ];
    }
}
