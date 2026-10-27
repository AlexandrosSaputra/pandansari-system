<?php

namespace App\Models;

use Database\Factories\HistoricalSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalSale extends Model
{
    /** @use HasFactory<HistoricalSaleFactory> */
    use HasFactory;

    protected $fillable = [
        'operational_unit_id',
        'item_id',
        'week_id',
        'week_start',
        'week_end',
        'source_outlet',
        'source_sku',
        'units_sold',
        'unit_price',
        'sales_value',
        'source_file',
        'source_row',
    ];

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
            'week_start' => 'date',
            'week_end' => 'date',
            'units_sold' => 'integer',
            'unit_price' => 'decimal:2',
            'sales_value' => 'decimal:2',
            'source_row' => 'integer',
        ];
    }
}
