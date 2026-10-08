<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function createdOperationalUnits(): HasMany
    {
        return $this->hasMany(OperationalUnit::class, 'created_by');
    }

    public function createdItems(): HasMany
    {
        return $this->hasMany(Item::class, 'created_by');
    }

    public function createdUnitItems(): HasMany
    {
        return $this->hasMany(UnitItem::class, 'created_by');
    }

    public function createdInventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class, 'created_by');
    }

    public function performedStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by');
    }

    public function createdStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'created_by');
    }

    public function respondedStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'responded_by');
    }

    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    public function paidTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'paid_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
