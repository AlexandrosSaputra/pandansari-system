<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Support\DecimalMoney;
use Illuminate\Support\Collection;

class TransactionCalculator
{
    private const MAX_VISITOR_COUNT = 4294967295;

    public function __construct(private DecimalMoney $money) {}

    /**
     * @param  Collection<int, TransactionItem>  $lines
     */
    public function recalculate(Transaction $transaction, Collection $lines): Transaction
    {
        $subtotals = [];
        $visitorCount = 0;

        foreach ($lines as $line) {
            $lineSubtotal = $this->money->multiply((string) $line->unit_price, $line->quantity);
            if ($line->subtotal !== $lineSubtotal) {
                $line->subtotal = $lineSubtotal;
                $line->save();
            }
            $subtotals[] = $lineSubtotal;

            $visitorFactor = $line->item->visitor_factor;
            abort_if(
                $visitorFactor > 0 && $line->quantity > intdiv(self::MAX_VISITOR_COUNT - $visitorCount, $visitorFactor),
                409,
                'Visitor count exceeds the supported range.',
            );
            $visitorCount += $line->quantity * $visitorFactor;
        }

        $subtotal = $this->money->sum($subtotals);
        $transaction->fill([
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'visitor_count' => $visitorCount,
        ])->save();

        return $transaction;
    }
}
