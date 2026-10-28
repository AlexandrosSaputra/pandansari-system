<?php

namespace App\Models;

use App\Enums\SafetyStockMode;
use Database\Factories\ForecastResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastResult extends Model
{
    /** @use HasFactory<ForecastResultFactory> */
    use HasFactory;

    protected $fillable = [
        'forecast_run_id',
        'operational_unit_id',
        'item_id',
        'model',
        'history_weeks',
        'predicted_units',
        'available_quantity',
        'minimum_stock',
        'safety_stock_mode',
        'target_service_level',
        'calculated_safety_stock',
        'safety_stock',
        'supplier_lead_time_days',
        'order_pack_size',
        'incoming_quantity',
        'unrounded_restock',
        'recommended_restock',
        'order_by_date',
        'mae',
        'rmse',
        'wape',
    ];

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function casts(): array
    {
        return [
            'history_weeks' => 'integer',
            'predicted_units' => 'integer',
            'available_quantity' => 'integer',
            'minimum_stock' => 'integer',
            'safety_stock_mode' => SafetyStockMode::class,
            'target_service_level' => 'decimal:2',
            'calculated_safety_stock' => 'integer',
            'safety_stock' => 'integer',
            'supplier_lead_time_days' => 'integer',
            'order_pack_size' => 'integer',
            'incoming_quantity' => 'integer',
            'unrounded_restock' => 'integer',
            'recommended_restock' => 'integer',
            'order_by_date' => 'date',
            'mae' => 'decimal:4',
            'rmse' => 'decimal:4',
            'wape' => 'decimal:4',
        ];
    }
}
