<?php

namespace App\Models;

use App\Enums\ForecastDecisionStatus;
use Database\Factories\ForecastResultDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastResultDecision extends Model
{
    /** @use HasFactory<ForecastResultDecisionFactory> */
    use HasFactory;

    protected $fillable = [
        'forecast_result_id',
        'status',
        'recommended_restock_snapshot',
        'final_restock_quantity',
        'reason',
        'decided_by',
        'decided_at',
    ];

    public function forecastResult(): BelongsTo
    {
        return $this->belongsTo(ForecastResult::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'status' => ForecastDecisionStatus::class,
            'recommended_restock_snapshot' => 'integer',
            'final_restock_quantity' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
