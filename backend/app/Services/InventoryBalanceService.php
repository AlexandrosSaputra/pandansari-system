<?php

namespace App\Services;

use App\Models\InventoryBalance;

class InventoryBalanceService
{
    public function lockOrCreate(int $inventoryLocationId, int $itemId): InventoryBalance
    {
        InventoryBalance::firstOrCreate(
            [
                'inventory_location_id' => $inventoryLocationId,
                'item_id' => $itemId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ],
        );

        return InventoryBalance::query()
            ->where('inventory_location_id', $inventoryLocationId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function assertInvariant(InventoryBalance $balance): void
    {
        abort_if(
            $balance->quantity_on_hand < 0
                || $balance->quantity_reserved < 0
                || $balance->quantity_reserved > $balance->quantity_on_hand,
            409,
            'Inventory balance invariant would be violated.',
        );
    }
}
