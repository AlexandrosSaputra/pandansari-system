<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'operational_unit_id',
        'status',
        'created_by',
        'paid_by',
        'subtotal',
        'total',
        'visitor_count',
        'paid_at',
    ];

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function isCart(): bool
    {
        return $this->status === TransactionStatus::Cart;
    }

    public function isPaid(): bool
    {
        return $this->status === TransactionStatus::Paid;
    }

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'visitor_count' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
