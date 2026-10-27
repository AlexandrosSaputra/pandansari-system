<?php

namespace App\Models;

use Database\Factories\ForecastRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastRun extends Model
{
    /** @use HasFactory<ForecastRunFactory> */
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'operational_unit_id',
        'horizon',
        'target_date',
        'forecast_start',
        'forecast_end',
        'history_through',
        'forecast_weeks',
        'method',
        'metrics',
        'explanation_provider',
        'explanation_status',
        'explanation',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'forecast_start' => 'date',
            'forecast_end' => 'date',
            'history_through' => 'date',
            'forecast_weeks' => 'integer',
            'metrics' => 'array',
            'explanation' => 'array',
        ];
    }
}
