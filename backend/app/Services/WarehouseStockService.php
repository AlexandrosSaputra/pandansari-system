<?php

namespace App\Services;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseStockService
{
    public function __construct(
        private InventoryBalanceService $balances,
        private ActivityLogger $logger,
    ) {}

    public function mainWarehouse(): InventoryLocation
    {
        return InventoryLocation::query()
            ->where('code', 'WH-MAIN')
            ->where('type', InventoryLocationType::Warehouse->value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function stockIn(User $actor, int $itemId, int $quantity, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($actor, $itemId, $quantity, $note): StockMovement {
            $warehouse = $this->mainWarehouse();
            $item = $this->lockProduct($itemId);
            $balance = $this->balances->lockOrCreate($warehouse->id, $item->id);

            $balance->quantity_on_hand += $quantity;
            $this->balances->assertInvariant($balance);
            $balance->save();

            $movement = StockMovement::create([
                'inventory_location_id' => $warehouse->id,
                'item_id' => $item->id,
                'direction' => StockDirection::In,
                'movement_type' => StockMovementType::StockIn,
                'quantity' => $quantity,
                'performed_by' => $actor->id,
                'note' => $note,
            ]);

            $this->logger->log($actor, 'WAREHOUSE_STOCK_IN', $movement, [
                'stock_movement_id' => $movement->id,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'location_id' => $warehouse->id,
            ]);

            return $movement->load(['inventoryLocation', 'item', 'performer']);
        }, 3);
    }

    public function stockOut(User $actor, int $itemId, int $quantity, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($actor, $itemId, $quantity, $note): StockMovement {
            $warehouse = $this->mainWarehouse();
            $item = $this->lockProduct($itemId);
            $balance = $this->balances->lockOrCreate($warehouse->id, $item->id);

            abort_if($quantity > $balance->available_quantity, 409, 'Insufficient available stock.');

            $balance->quantity_on_hand -= $quantity;
            $this->balances->assertInvariant($balance);
            $balance->save();

            $movement = StockMovement::create([
                'inventory_location_id' => $warehouse->id,
                'item_id' => $item->id,
                'direction' => StockDirection::Out,
                'movement_type' => StockMovementType::StockOut,
                'quantity' => $quantity,
                'performed_by' => $actor->id,
                'note' => $note,
            ]);

            $this->logger->log($actor, 'WAREHOUSE_STOCK_OUT', $movement, [
                'stock_movement_id' => $movement->id,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'location_id' => $warehouse->id,
            ]);

            return $movement->load(['inventoryLocation', 'item', 'performer']);
        }, 3);
    }

    private function lockProduct(int $itemId): Item
    {
        $item = Item::query()->whereKey($itemId)->lockForUpdate()->first();

        if (! $item?->is_active || $item->type !== ItemType::Product) {
            throw ValidationException::withMessages([
                'item_id' => 'The selected item must be an active product.',
            ]);
        }

        return $item;
    }
}
