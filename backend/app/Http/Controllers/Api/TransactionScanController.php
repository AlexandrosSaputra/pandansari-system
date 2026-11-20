<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScannerCommand;
use App\Enums\TransactionInputMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionScanRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCartService;
use App\Services\TransactionCheckoutService;

class TransactionScanController extends Controller
{
    public function store(
        StoreTransactionScanRequest $request,
        Transaction $transaction,
        TransactionCartService $carts,
        TransactionCheckoutService $checkout,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->tokenCan('transaction:scan'), 403, 'This token cannot submit scanner input.');
        abort_unless($transaction->operationalUnit?->scanner_enabled, 409, 'The scanner is disabled for this operational unit.');

        if ($request->validated('barcode') === ScannerCommand::Checkout->value) {
            return new TransactionResource($checkout->checkout($user, $transaction));
        }

        return new TransactionResource($carts->addItem(
            $user,
            $transaction,
            TransactionInputMethod::Scanner,
            null,
            $request->validated('barcode'),
            1,
        ));
    }
}
