<?php

namespace App\Models;

use App\Enums\SafetyStockMode;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitItem extends Model
{
    protected $fillable = [
        'operational_unit_id',
        'item_id',
        'selling_price',
        'minimum_stock',
        'safety_stock_mode',
        'target_service_level',
        'supplier_lead_time_days',
        'order_pack_size',
        'is_active',
        'created_by',
    ];

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'minimum_stock' => 'integer',
            'safety_stock_mode' => SafetyStockMode::class,
            'target_service_level' => 'decimal:2',
            'supplier_lead_time_days' => 'integer',
            'order_pack_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
