<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number',
        'source_location_id',
        'destination_location_id',
        'status',
        'created_by',
        'responded_by',
        'rejection_reason',
        'responded_at',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === StockTransferStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === StockTransferStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === StockTransferStatus::Rejected;
    }

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'responded_at' => 'datetime',
        ];
    }
}
