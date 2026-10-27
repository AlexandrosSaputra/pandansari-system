<?php

namespace App\Models;

use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'item_code',
        'barcode',
        'name',
        'type',
        'uom',
        'visitor_factor',
        'is_active',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unitItems(): HasMany
    {
        return $this->hasMany(UnitItem::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function historicalSales(): HasMany
    {
        return $this->hasMany(HistoricalSale::class);
    }

    public function forecastResults(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function products(Builder $query): Builder
    {
        return $query->where('type', ItemType::Product->value);
    }

    #[Scope]
    protected function services(Builder $query): Builder
    {
        return $query->where('type', ItemType::Service->value);
    }

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'visitor_factor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
