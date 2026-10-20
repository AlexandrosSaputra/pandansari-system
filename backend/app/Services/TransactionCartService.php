<?php

namespace App\Services;

use App\Enums\TransactionInputMethod;
use App\Enums\TransactionStatus;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UnitItem;
use App\Models\User;
use App\Support\DecimalMoney;
use App\Support\TransactionAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionCartService
{
    private const MAX_QUANTITY = 4294967295;

    public function __construct(
        private TransactionAccess $access,
        private TransactionCalculator $calculator,
        private DecimalMoney $money,
    ) {}

    /**
     * @return array{transaction: Transaction, created: bool}
     */
    public function resolveCart(User $actor, OperationalUnit $unit): array
    {
        return DB::transaction(function () use ($actor, $unit): array {
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->canTransact($lockedActor), 403, 'This action is unauthorized.');
            abort_if(
                $lockedActor->activeRole()?->slug === 'admin' && $lockedActor->activeOperationalUnitId() !== $unit->id,
                403,
                'This action is unauthorized.',
            );
            $unit = OperationalUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            abort_unless($unit->is_active, 409, 'The operational unit is inactive.');

            $cart = Transaction::query()
                ->where('operational_unit_id', $unit->id)
                ->where('created_by', $actor->id)
                ->where('status', TransactionStatus::Cart->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($cart) {
                return ['transaction' => $this->loadTransaction($cart), 'created' => false];
            }

            $cart = Transaction::create([
                'transaction_number' => 'TMP-'.Str::uuid(),
                'operational_unit_id' => $unit->id,
                'status' => TransactionStatus::Cart,
                'created_by' => $actor->id,
                'paid_by' => null,
                'subtotal' => '0.00',
                'total' => '0.00',
                'visitor_count' => 0,
                'paid_at' => null,
            ]);
            $cart->update([
                'transaction_number' => sprintf('TRX-%s-%06d', now()->format('Ymd'), $cart->id),
            ]);

            return ['transaction' => $this->loadTransaction($cart), 'created' => true];
        }, 3);
    }

    public function addItem(
        User $actor,
        Transaction $transaction,
        TransactionInputMethod $inputMethod,
        ?int $itemId,
        ?string $barcode,
        int $quantity,
    ): Transaction {
        return DB::transaction(function () use ($actor, $transaction, $inputMethod, $itemId, $barcode, $quantity): Transaction {
            $transaction = $this->lockMutableCart($actor, $transaction);
            $unit = $this->lockActiveUnit($transaction);
            [$item, $unitItem] = $this->resolveUnitItem($unit, $inputMethod, $itemId, $barcode);
            $line = TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if ($line) {
                abort_if($line->quantity > self::MAX_QUANTITY - $quantity, 409, 'Transaction item quantity exceeds the supported range.');
                $line->quantity += $quantity;
                $line->subtotal = $this->money->multiply((string) $line->unit_price, $line->quantity);
                $line->save();
            } else {
                $line = TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitItem->selling_price,
                    'subtotal' => $this->money->multiply((string) $unitItem->selling_price, $quantity),
                    'input_method' => $inputMethod,
                ]);
            }

            $this->recalculate($transaction);

            return $this->loadTransaction($transaction);
        }, 3);
    }

    public function updateItem(
        User $actor,
        Transaction $transaction,
        TransactionItem $transactionItem,
        int $quantity,
    ): Transaction {
        return DB::transaction(function () use ($actor, $transaction, $transactionItem, $quantity): Transaction {
            $transaction = $this->lockMutableCart($actor, $transaction);
            $this->lockActiveUnit($transaction);
            $line = TransactionItem::query()
                ->whereKey($transactionItem->id)
                ->where('transaction_id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $line->update([
                'quantity' => $quantity,
                'subtotal' => $this->money->multiply((string) $line->unit_price, $quantity),
            ]);

            $this->recalculate($transaction);

            return $this->loadTransaction($transaction);
        }, 3);
    }

    public function removeItem(
        User $actor,
        Transaction $transaction,
        TransactionItem $transactionItem,
    ): Transaction {
        return DB::transaction(function () use ($actor, $transaction, $transactionItem): Transaction {
            $transaction = $this->lockMutableCart($actor, $transaction);
            $this->lockActiveUnit($transaction);
            $line = TransactionItem::query()
                ->whereKey($transactionItem->id)
                ->where('transaction_id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $line->delete();

            $this->recalculate($transaction);

            return $this->loadTransaction($transaction);
        }, 3);
    }

    private function lockMutableCart(User $actor, Transaction $transaction): Transaction
    {
        $transaction = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
        abort_unless($this->access->canMutateCart($actor, $transaction), 403, 'This action is unauthorized.');
        abort_unless($transaction->isCart(), 409, 'A paid transaction is immutable.');

        return $transaction;
    }

    private function lockActiveUnit(Transaction $transaction): OperationalUnit
    {
        $unit = OperationalUnit::query()->whereKey($transaction->operational_unit_id)->lockForUpdate()->firstOrFail();
        abort_unless($unit->is_active, 409, 'The operational unit is inactive.');

        return $unit;
    }

    /**
     * @return array{Item, UnitItem}
     */
    private function resolveUnitItem(
        OperationalUnit $unit,
        TransactionInputMethod $inputMethod,
        ?int $itemId,
        ?string $barcode,
    ): array {
        $item = $inputMethod === TransactionInputMethod::Manual
            ? Item::query()->whereKey($itemId)->lockForUpdate()->first()
            : Item::query()->where('barcode', $barcode)->lockForUpdate()->first();
        $field = $inputMethod === TransactionInputMethod::Manual ? 'item_id' : 'barcode';

        if (! $item?->is_active) {
            throw ValidationException::withMessages([$field => 'The selected item is not active or was not found.']);
        }

        $unitItem = UnitItem::query()
            ->where('operational_unit_id', $unit->id)
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $unitItem) {
            throw ValidationException::withMessages([$field => 'The selected item is not active at this operational unit.']);
        }

        return [$item, $unitItem];
    }

    private function recalculate(Transaction $transaction): void
    {
        $lines = TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->with('item')
            ->orderBy('item_id')
            ->get();
        $this->calculator->recalculate($transaction, $lines);
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
