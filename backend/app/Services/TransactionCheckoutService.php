<?php

namespace App\Services;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UnitItem;
use App\Models\User;
use App\Support\TransactionAccess;
use Illuminate\Support\Facades\DB;

class TransactionCheckoutService
{
    public function __construct(
        private TransactionAccess $access,
        private TransactionCalculator $calculator,
        private InventoryBalanceService $balances,
        private ActivityLogger $logger,
    ) {}

    public function checkout(User $actor, Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($actor, $transaction): Transaction {
            $transaction = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canMutateCart($actor, $transaction), 403, 'This action is unauthorized.');
            abort_unless($transaction->isCart(), 409, 'A paid transaction is immutable.');

            $unit = OperationalUnit::query()->whereKey($transaction->operational_unit_id)->lockForUpdate()->firstOrFail();
            abort_unless($unit->is_active, 409, 'The operational unit is inactive.');

            $lines = TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();
            abort_if($lines->isEmpty(), 409, 'The cart is empty.');

            $itemIds = $lines->pluck('item_id')->all();
            $items = Item::query()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $unitItems = UnitItem::query()
                ->where('operational_unit_id', $unit->id)
                ->whereIn('item_id', $itemIds)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            foreach ($lines as $line) {
                $item = $items->get($line->item_id);
                $unitItem = $unitItems->get($line->item_id);
                abort_unless($item?->is_active && $unitItem?->is_active, 409, 'A cart item is no longer active at this operational unit.');
                $line->setRelation('item', $item);
            }

            $this->calculator->recalculate($transaction, $lines);

            $productLines = $lines->filter(fn (TransactionItem $line): bool => $line->item->type === ItemType::Product)->values();
            /** @var array<int, InventoryBalance> $lockedBalances */
            $lockedBalances = [];

            if ($productLines->isNotEmpty()) {
                $location = InventoryLocation::query()
                    ->where('operational_unit_id', $unit->id)
                    ->where('type', InventoryLocationType::Unit->value)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                abort_unless($location, 409, 'The operational unit inventory location is unavailable.');

                foreach ($productLines as $line) {
                    $balance = $this->balances->lockOrCreate($location->id, $line->item_id);
                    abort_if($line->quantity > $balance->available_quantity, 409, 'Insufficient available stock.');
                    $lockedBalances[$line->item_id] = $balance;
                }

                foreach ($productLines as $line) {
                    $balance = $lockedBalances[$line->item_id];
                    $balance->quantity_on_hand -= $line->quantity;
                    $this->balances->assertInvariant($balance);
                    $balance->save();

                    StockMovement::create([
                        'inventory_location_id' => $location->id,
                        'item_id' => $line->item_id,
                        'direction' => StockDirection::Out,
                        'movement_type' => StockMovementType::Sale,
                        'quantity' => $line->quantity,
                        'reference_type' => Transaction::MOVEMENT_REFERENCE_TYPE,
                        'reference_id' => $transaction->id,
                        'performed_by' => $actor->id,
                        'note' => null,
                    ]);
                }
            }

            $transaction->update([
                'status' => TransactionStatus::Paid,
                'paid_by' => $actor->id,
                'paid_at' => now(),
            ]);

            $this->logger->log($actor, 'TRANSACTION_PAID', $transaction, [
                'transaction_number' => $transaction->transaction_number,
                'operational_unit_id' => $unit->id,
                'total' => $transaction->total,
                'visitor_count' => $transaction->visitor_count,
                'item_count' => $lines->count(),
            ]);

            return $this->loadTransaction($transaction);
        }, 3);
    }

    private function loadTransaction(Transaction $transaction): Transaction
    {
        return $transaction->fresh([
            'operationalUnit',
            'creator',
            'payer',
            'transactionItems.item',
        ]);
    }
}
