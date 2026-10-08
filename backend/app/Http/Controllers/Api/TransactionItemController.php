<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionInputMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddTransactionItemRequest;
use App\Http\Requests\UpdateTransactionItemRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\TransactionCartService;
use Illuminate\Http\Request;

class TransactionItemController extends Controller
{
    public function store(
        AddTransactionItemRequest $request,
        Transaction $transaction,
        TransactionCartService $carts,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $attributes = $request->validated();

        return new TransactionResource($carts->addItem(
            $user,
            $transaction,
            TransactionInputMethod::from($attributes['input_method']),
            $attributes['item_id'] ?? null,
            $attributes['barcode'] ?? null,
            $attributes['quantity'] ?? 1,
        ));
    }

    public function update(
        UpdateTransactionItemRequest $request,
        Transaction $transaction,
        TransactionItem $transactionItem,
        TransactionCartService $carts,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return new TransactionResource($carts->updateItem(
            $user,
            $transaction,
            $transactionItem,
            $request->validated('quantity'),
        ));
    }

    public function destroy(
        Request $request,
        Transaction $transaction,
        TransactionItem $transactionItem,
        TransactionCartService $carts,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return new TransactionResource($carts->removeItem($user, $transaction, $transactionItem));
    }
}
