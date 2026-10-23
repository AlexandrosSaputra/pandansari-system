<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionInputMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionScanRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCartService;

class TransactionScanController extends Controller
{
    public function store(
        StoreTransactionScanRequest $request,
        Transaction $transaction,
        TransactionCartService $carts,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->tokenCan('transaction:scan'), 403, 'This token cannot submit scanner input.');
        abort_unless($transaction->operationalUnit?->scanner_enabled, 409, 'The scanner is disabled for this operational unit.');

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
