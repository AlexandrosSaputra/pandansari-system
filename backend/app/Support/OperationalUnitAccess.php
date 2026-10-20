<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Eloquent\Builder;

class OperationalUnitAccess
{
    public function canUseApplication(User $user): bool
    {
        $role = $user->activeRole();
        $unit = $user->activeOperationalUnit();

        if (! $user->is_active || ! $role?->is_active) {
            return false;
        }

        if ($role->slug === 'admin') {
            return $unit?->is_active === true;
        }

        return true;
    }

    public function canUseAssignment(User $user, UserRoleAssignment $assignment): bool
    {
        if (! $user->is_active || $assignment->user_id !== $user->id || ! $assignment->is_active || ! $assignment->role?->is_active) {
            return false;
        }

        return $assignment->role->slug !== 'admin' || $assignment->operationalUnit?->is_active === true;
    }

    public function allows(User $user, OperationalUnit $unit): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->activeRole()?->slug) {
            'superadmin' => true,
            'admin' => $unit->is_active && $user->activeOperationalUnitId() === $unit->id,
            default => false,
        };
    }

    public function canAccessWarehouse(User $user): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return in_array($user->activeRole()?->slug, ['warehouse', 'superadmin'], true);
    }

    public function canReadInventory(User $user): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return in_array($user->activeRole()?->slug, ['admin', 'warehouse', 'superadmin'], true);
    }

    public function allowsInventoryRead(User $user, OperationalUnit $unit): bool
    {
        if (! $this->canReadInventory($user)) {
            return false;
        }

        return match ($user->activeRole()?->slug) {
            'superadmin' => true,
            'warehouse' => $unit->is_active,
            'admin' => $unit->is_active && $user->activeOperationalUnitId() === $unit->id,
            default => false,
        };
    }

    public function canRespondToTransfer(User $user, StockTransfer $transfer): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->activeRole()?->slug) {
            'superadmin' => true,
            'admin' => $transfer->destinationLocation?->operational_unit_id === $user->activeOperationalUnitId()
                && $user->activeOperationalUnit()?->is_active === true,
            default => false,
        };
    }

    public function canViewTransfer(User $user, StockTransfer $transfer): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->activeRole()?->slug) {
            'superadmin' => true,
            'warehouse' => $transfer->sourceLocation?->code === 'WH-MAIN',
            'admin' => $transfer->destinationLocation?->operational_unit_id === $user->activeOperationalUnitId(),
            default => false,
        };
    }

    /**
     * @param  Builder<StockMovement>  $query
     * @return Builder<StockMovement>
     */
    public function scopeStockMovements(Builder $query, User $user): Builder
    {
        return match ($user->activeRole()?->slug) {
            'superadmin' => $query,
            'admin' => $query->whereHas('inventoryLocation', fn (Builder $location) => $location
                ->where('operational_unit_id', $user->activeOperationalUnitId())),
            'warehouse' => $query->where(function (Builder $movement): void {
                $movement->whereHas('inventoryLocation', fn (Builder $location) => $location
                    ->where('code', 'WH-MAIN'))
                    ->orWhere(function (Builder $transferMovement): void {
                        $transferMovement
                            ->where('movement_type', StockMovementType::TransferIn->value)
                            ->where('reference_type', StockTransfer::MOVEMENT_REFERENCE_TYPE)
                            ->whereExists(function ($transfers): void {
                                $transfers->selectRaw('1')
                                    ->from('stock_transfers')
                                    ->join('inventory_locations as source_locations', 'source_locations.id', '=', 'stock_transfers.source_location_id')
                                    ->whereColumn('stock_transfers.id', 'stock_movements.reference_id')
                                    ->where('source_locations.code', 'WH-MAIN');
                            });
                    });
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<StockTransfer>  $query
     * @return Builder<StockTransfer>
     */
    public function scopeStockTransfers(Builder $query, User $user): Builder
    {
        return match ($user->activeRole()?->slug) {
            'superadmin' => $query,
            'warehouse' => $query->whereHas('sourceLocation', fn (Builder $location) => $location->where('code', 'WH-MAIN')),
            'admin' => $query->whereHas('destinationLocation', fn (Builder $location) => $location
                ->where('operational_unit_id', $user->activeOperationalUnitId())),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
