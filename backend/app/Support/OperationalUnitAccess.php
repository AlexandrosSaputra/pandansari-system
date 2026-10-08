<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OperationalUnitAccess
{
    public function canUseApplication(User $user): bool
    {
        $role = $user->role;

        if (! $user->is_active || ! $role?->is_active) {
            return false;
        }

        if ($role->slug === 'admin') {
            return $user->operationalUnit?->is_active === true;
        }

        return true;
    }

    public function allows(User $user, OperationalUnit $unit): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->role->slug) {
            'superadmin' => true,
            'admin' => $unit->is_active && $user->operational_unit_id === $unit->id,
            default => false,
        };
    }

    public function canAccessWarehouse(User $user): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return in_array($user->role->slug, ['warehouse', 'superadmin'], true);
    }

    public function canReadInventory(User $user): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return in_array($user->role->slug, ['admin', 'warehouse', 'superadmin'], true);
    }

    public function allowsInventoryRead(User $user, OperationalUnit $unit): bool
    {
        if (! $this->canReadInventory($user)) {
            return false;
        }

        return match ($user->role->slug) {
            'superadmin' => true,
            'warehouse' => $unit->is_active,
            'admin' => $unit->is_active && $user->operational_unit_id === $unit->id,
            default => false,
        };
    }

    public function canRespondToTransfer(User $user, StockTransfer $transfer): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->role->slug) {
            'superadmin' => true,
            'admin' => $transfer->destinationLocation?->operational_unit_id === $user->operational_unit_id
                && $user->operationalUnit?->is_active === true,
            default => false,
        };
    }

    public function canViewTransfer(User $user, StockTransfer $transfer): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->role->slug) {
            'superadmin' => true,
            'warehouse' => $transfer->sourceLocation?->code === 'WH-MAIN',
            'admin' => $transfer->destinationLocation?->operational_unit_id === $user->operational_unit_id,
            default => false,
        };
    }

    /**
     * @param  Builder<StockMovement>  $query
     * @return Builder<StockMovement>
     */
    public function scopeStockMovements(Builder $query, User $user): Builder
    {
        return match ($user->role?->slug) {
            'superadmin' => $query,
            'admin' => $query->whereHas('inventoryLocation', fn (Builder $location) => $location
                ->where('operational_unit_id', $user->operational_unit_id)),
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
        return match ($user->role?->slug) {
            'superadmin' => $query,
            'warehouse' => $query->whereHas('sourceLocation', fn (Builder $location) => $location->where('code', 'WH-MAIN')),
            'admin' => $query->whereHas('destinationLocation', fn (Builder $location) => $location
                ->where('operational_unit_id', $user->operational_unit_id)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
