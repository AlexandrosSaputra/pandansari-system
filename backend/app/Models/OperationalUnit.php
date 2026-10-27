<?php

namespace App\Models;

use App\Enums\OperationalUnitType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OperationalUnit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'scanner_enabled',
        'is_active',
        'created_by',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unitItems(): HasMany
    {
        return $this->hasMany(UnitItem::class);
    }

    public function inventoryLocation(): HasOne
    {
        return $this->hasOne(InventoryLocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function historicalSales(): HasMany
    {
        return $this->hasMany(HistoricalSale::class);
    }

    public function forecastRuns(): HasMany
    {
        return $this->hasMany(ForecastRun::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ofType(Builder $query, OperationalUnitType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    protected function casts(): array
    {
        return [
            'type' => OperationalUnitType::class,
            'scanner_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
