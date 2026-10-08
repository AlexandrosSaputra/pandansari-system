<?php

namespace App\Services;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UnitItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private InventoryBalanceService $balances,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: int}>  $items
     */
    public function create(User $actor, int $destinationUnitId, array $items): StockTransfer
    {
        return DB::transaction(function () use ($actor, $destinationUnitId, $items): StockTransfer {
            $source = $this->lockMainWarehouse();
            [$destinationUnit, $destinationLocation] = $this->lockDestination($destinationUnitId);
            $items = collect($items)->sortBy('item_id')->values();
            $itemIds = $items->pluck('item_id')->all();

            $products = Item::query()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->validateProducts($products, $itemIds);
            $this->validateUnitItems($destinationUnit, $itemIds);

            /** @var array<int, InventoryBalance> $sourceBalances */
            $sourceBalances = [];
            foreach ($items as $line) {
                $balance = $this->balances->lockOrCreate($source->id, $line['item_id']);
                abort_if($line['quantity'] > $balance->available_quantity, 409, 'Insufficient available stock.');
                $sourceBalances[$line['item_id']] = $balance;
            }

            $transfer = StockTransfer::create([
                'transfer_number' => 'TMP-'.Str::uuid(),
                'source_location_id' => $source->id,
                'destination_location_id' => $destinationLocation->id,
                'status' => StockTransferStatus::Pending,
                'created_by' => $actor->id,
                'responded_by' => null,
                'responded_at' => null,
                'rejection_reason' => null,
            ]);
            $transfer->update([
                'transfer_number' => sprintf('TRF-%s-%06d', now()->format('Ymd'), $transfer->id),
            ]);

            foreach ($items as $line) {
                $balance = $sourceBalances[$line['item_id']];
                $balance->quantity_reserved += $line['quantity'];
                $this->balances->assertInvariant($balance);
                $balance->save();

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                ]);
            }

            $this->logger->log($actor, 'TRANSFER_CREATED', $transfer, [
                'transfer_number' => $transfer->transfer_number,
                'destination_operational_unit_id' => $destinationUnit->id,
                'item_count' => $items->count(),
            ]);

            return $this->loadTransfer($transfer);
        }, 3);
    }

    public function accept(User $actor, StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->isPending(), 409, 'The transfer has already been responded to.');

            $source = InventoryLocation::query()->whereKey($transfer->source_location_id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $source->code === 'WH-MAIN'
                    && $source->type === InventoryLocationType::Warehouse
                    && $source->is_active,
                409,
                'The transfer source is unavailable.',
            );

            $destinationUnitId = InventoryLocation::query()
                ->whereKey($transfer->destination_location_id)
                ->value('operational_unit_id');
            $destinationUnit = OperationalUnit::query()->whereKey($destinationUnitId)->lockForUpdate()->firstOrFail();
            abort_unless($destinationUnit->is_active, 409, 'The destination operational unit is inactive.');
            $destination = InventoryLocation::query()->whereKey($transfer->destination_location_id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $destination->type === InventoryLocationType::Unit
                    && $destination->is_active
                    && $destination->operational_unit_id === $destinationUnit->id,
                409,
                'The transfer destination is unavailable.',
            );

            $lines = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->orderBy('item_id')
                ->get();
            $itemIds = $lines->pluck('item_id')->all();
            $products = Item::query()->whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $this->validateProductsForResponse($products, $itemIds);
            $this->validateUnitItemsForResponse($destinationUnit, $itemIds);

            /** @var array<int, InventoryBalance> $sourceBalances */
            $sourceBalances = [];
            foreach ($lines as $line) {
                $balance = $this->balances->lockOrCreate($source->id, $line->item_id);
                abort_if(
                    $balance->quantity_reserved < $line->quantity
                        || $balance->quantity_on_hand < $line->quantity
                        || $balance->quantity_reserved > $balance->quantity_on_hand,
                    409,
                    'Reserved warehouse stock is no longer consistent.',
                );
                $sourceBalances[$line->item_id] = $balance;
            }

            /** @var array<int, InventoryBalance> $destinationBalances */
            $destinationBalances = [];
            foreach ($lines as $line) {
                $balance = $this->balances->lockOrCreate($destination->id, $line->item_id);
                $this->balances->assertInvariant($balance);
                $destinationBalances[$line->item_id] = $balance;
            }

            foreach ($lines as $line) {
                $sourceBalance = $sourceBalances[$line->item_id];
                $sourceBalance->quantity_on_hand -= $line->quantity;
                $sourceBalance->quantity_reserved -= $line->quantity;
                $this->balances->assertInvariant($sourceBalance);
                $sourceBalance->save();

                $destinationBalance = $destinationBalances[$line->item_id];
                $destinationBalance->quantity_on_hand += $line->quantity;
                $this->balances->assertInvariant($destinationBalance);
                $destinationBalance->save();

                $this->recordTransferMovement($source, $line, $actor, StockDirection::Out, StockMovementType::TransferOut);
                $this->recordTransferMovement($destination, $line, $actor, StockDirection::In, StockMovementType::TransferIn);
            }

            $transfer->update([
                'status' => StockTransferStatus::Accepted,
                'responded_by' => $actor->id,
                'responded_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->logger->log($actor, 'TRANSFER_ACCEPTED', $transfer, [
                'transfer_number' => $transfer->transfer_number,
                'item_count' => $lines->count(),
            ]);

            return $this->loadTransfer($transfer);
        }, 3);
    }

    public function reject(User $actor, StockTransfer $transfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $reason): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->isPending(), 409, 'The transfer has already been responded to.');

            $source = InventoryLocation::query()->whereKey($transfer->source_location_id)->lockForUpdate()->firstOrFail();
            abort_unless($source->code === 'WH-MAIN' && $source->type === InventoryLocationType::Warehouse, 409, 'The transfer source is invalid.');

            $lines = StockTransferItem::query()
                ->where('stock_transfer_id', $transfer->id)
                ->orderBy('item_id')
                ->get();

            foreach ($lines as $line) {
                $balance = $this->balances->lockOrCreate($source->id, $line->item_id);
                abort_if(
                    $balance->quantity_reserved < $line->quantity
                        || $balance->quantity_reserved > $balance->quantity_on_hand,
                    409,
                    'Reserved warehouse stock is no longer consistent.',
                );
                $balance->quantity_reserved -= $line->quantity;
                $this->balances->assertInvariant($balance);
                $balance->save();
            }

            $transfer->update([
                'status' => StockTransferStatus::Rejected,
                'responded_by' => $actor->id,
                'responded_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->logger->log($actor, 'TRANSFER_REJECTED', $transfer, [
                'transfer_number' => $transfer->transfer_number,
                'item_count' => $lines->count(),
            ]);

            return $this->loadTransfer($transfer);
        }, 3);
    }

    private function lockMainWarehouse(): InventoryLocation
    {
        return InventoryLocation::query()
            ->where('code', 'WH-MAIN')
            ->where('type', InventoryLocationType::Warehouse->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{OperationalUnit, InventoryLocation}
     */
    private function lockDestination(int $unitId): array
    {
        $unit = OperationalUnit::query()->whereKey($unitId)->lockForUpdate()->first();
        if (! $unit?->is_active) {
            throw ValidationException::withMessages([
                'destination_operational_unit_id' => 'The destination operational unit must be active.',
            ]);
        }

        $location = InventoryLocation::query()
            ->where('operational_unit_id', $unit->id)
            ->where('type', InventoryLocationType::Unit->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
        if (! $location) {
            throw ValidationException::withMessages([
                'destination_operational_unit_id' => 'The destination has no active unit inventory location.',
            ]);
        }

        return [$unit, $location];
    }

    /**
     * @param  Collection<int, Item>  $products
     * @param  list<int>  $itemIds
     */
    private function validateProducts(Collection $products, array $itemIds): void
    {
        foreach ($itemIds as $index => $itemId) {
            $item = $products->get($itemId);
            if (! $item?->is_active || $item->type !== ItemType::Product) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'The selected item must be an active product.',
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function validateUnitItems(OperationalUnit $unit, array $itemIds): void
    {
        $registeredIds = UnitItem::query()
            ->where('operational_unit_id', $unit->id)
            ->whereIn('item_id', $itemIds)
            ->where('is_active', true)
            ->orderBy('item_id')
            ->lockForUpdate()
            ->pluck('item_id');

        foreach ($itemIds as $index => $itemId) {
            if (! $registeredIds->contains($itemId)) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'The product is not active at the destination unit.',
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Item>  $products
     * @param  list<int>  $itemIds
     */
    private function validateProductsForResponse(Collection $products, array $itemIds): void
    {
        foreach ($itemIds as $itemId) {
            $item = $products->get($itemId);
            abort_unless($item?->is_active && $item->type === ItemType::Product, 409, 'A transfer product is no longer active.');
        }
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function validateUnitItemsForResponse(OperationalUnit $unit, array $itemIds): void
    {
        $registeredIds = UnitItem::query()
            ->where('operational_unit_id', $unit->id)
            ->whereIn('item_id', $itemIds)
            ->where('is_active', true)
            ->orderBy('item_id')
            ->lockForUpdate()
            ->pluck('item_id');

        abort_unless($registeredIds->count() === count($itemIds), 409, 'A transfer product is no longer active at the destination unit.');
    }

    private function recordTransferMovement(
        InventoryLocation $location,
        StockTransferItem $line,
        User $actor,
        StockDirection $direction,
        StockMovementType $type,
    ): void {
        StockMovement::create([
            'inventory_location_id' => $location->id,
            'item_id' => $line->item_id,
            'direction' => $direction,
            'movement_type' => $type,
            'quantity' => $line->quantity,
            'reference_type' => StockTransfer::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $line->stock_transfer_id,
            'performed_by' => $actor->id,
        ]);
    }

    private function loadTransfer(StockTransfer $transfer): StockTransfer
    {
        return $transfer->fresh([
            'sourceLocation',
            'destinationLocation.operationalUnit',
            'creator',
            'responder',
            'transferItems.item',
        ]);
    }
}
