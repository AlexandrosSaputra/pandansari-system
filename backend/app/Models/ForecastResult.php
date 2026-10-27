<?php

namespace App\Models;

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
        'incoming_quantity',
        'recommended_restock',
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
            'incoming_quantity' => 'integer',
            'recommended_restock' => 'integer',
            'mae' => 'decimal:4',
            'rmse' => 'decimal:4',
            'wape' => 'decimal:4',
        ];
    }
}
