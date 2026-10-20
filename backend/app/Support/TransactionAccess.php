<?php

namespace App\Support;

use App\Enums\TransactionStatus;
use App\Models\OperationalUnit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TransactionAccess
{
    public function __construct(private OperationalUnitAccess $operationalUnits) {}

    public function canTransact(User $user): bool
    {
        return $this->operationalUnits->canUseApplication($user)
            && in_array($user->activeRole()?->slug, ['admin', 'superadmin'], true);
    }

    public function canViewTransactions(User $user): bool
    {
        return $this->canTransact($user);
    }

    public function resolveUnit(User $user, ?int $requestedUnitId): OperationalUnit
    {
        abort_unless($this->canTransact($user), 403, 'This action is unauthorized.');

        if ($user->activeRole()?->slug === 'admin') {
            $unit = $user->activeOperationalUnit();
        } else {
            if ($requestedUnitId === null) {
                throw ValidationException::withMessages([
                    'operational_unit_id' => 'The operational unit field is required.',
                ]);
            }

            $unit = OperationalUnit::find($requestedUnitId);
        }

        if (! $unit?->is_active) {
            throw ValidationException::withMessages([
                'operational_unit_id' => 'The selected operational unit must be active.',
            ]);
        }

        return $unit;
    }

    public function canMutateCart(User $user, Transaction $transaction): bool
    {
        if (! $this->canTransact($user) || $transaction->created_by !== $user->id) {
            return false;
        }

        return $user->activeRole()?->slug === 'superadmin'
            || $transaction->operational_unit_id === $user->activeOperationalUnitId();
    }

    public function canView(User $user, Transaction $transaction): bool
    {
        if (! $this->canViewTransactions($user)) {
            return false;
        }

        if ($user->activeRole()?->slug === 'superadmin') {
            return true;
        }

        if ($transaction->operational_unit_id !== $user->activeOperationalUnitId()) {
            return false;
        }

        return $transaction->status === TransactionStatus::Paid
            || ($transaction->status === TransactionStatus::Cart && $transaction->created_by === $user->id);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeTransactions(Builder $query, User $user): Builder
    {
        return match ($user->activeRole()?->slug) {
            'superadmin' => $query,
            'admin' => $query
                ->where('operational_unit_id', $user->activeOperationalUnitId())
                ->where(function (Builder $transactions) use ($user): void {
                    $transactions->where('status', TransactionStatus::Paid->value)
                        ->orWhere(function (Builder $carts) use ($user): void {
                            $carts->where('status', TransactionStatus::Cart->value)
                                ->where('created_by', $user->id);
                        });
                }),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
