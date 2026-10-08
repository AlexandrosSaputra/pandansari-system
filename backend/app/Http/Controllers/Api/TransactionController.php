<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveTransactionCartRequest;
use App\Http\Requests\TransactionCatalogRequest;
use App\Http\Requests\TransactionListRequest;
use App\Http\Resources\TransactionCatalogResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\TransactionCartService;
use App\Services\TransactionCheckoutService;
use App\Support\TransactionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function catalog(
        TransactionCatalogRequest $request,
        TransactionAccess $access,
    ): AnonymousResourceCollection {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $filters = $request->validated();
        $unit = $access->resolveUnit($user, $filters['operational_unit_id'] ?? null);
        $locationId = $unit->inventoryLocation()->active()->units()->value('id');

        $catalog = UnitItem::query()
            ->where('unit_items.operational_unit_id', $unit->id)
            ->where('unit_items.is_active', true)
            ->whereHas('item', fn (Builder $item) => $item->where('is_active', true))
            ->leftJoin('inventory_balances as balance', function (JoinClause $join) use ($locationId): void {
                $join->on('balance.item_id', '=', 'unit_items.item_id')
                    ->where('balance.inventory_location_id', $locationId);
            })
            ->select('unit_items.*')
            ->selectRaw('COALESCE(balance.quantity_on_hand, 0) as quantity_on_hand')
            ->selectRaw('COALESCE(balance.quantity_reserved, 0) as quantity_reserved')
            ->with(['item', 'operationalUnit'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('item',
                fn (Builder $item) => $item->where('item_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"),
            ))
            ->orderBy('unit_items.id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return TransactionCatalogResource::collection($catalog);
    }

    public function cart(
        ResolveTransactionCartRequest $request,
        TransactionAccess $access,
        TransactionCartService $carts,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $attributes = $request->validated();
        $unit = $access->resolveUnit($user, $attributes['operational_unit_id'] ?? null);
        $result = $carts->resolveCart($user, $unit);

        return (new TransactionResource($result['transaction']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    public function index(
        TransactionListRequest $request,
        TransactionAccess $access,
    ): AnonymousResourceCollection {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canViewTransactions($user), 403, 'This action is unauthorized.');
        $filters = $request->validated();

        $transactions = $access->scopeTransactions(Transaction::query(), $user)
            ->with(['operationalUnit', 'creator', 'payer'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['operational_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query->where('operational_unit_id', $unitId))
            ->when($filters['created_by'] ?? null, fn (Builder $query, int $creatorId) => $query->where('created_by', $creatorId))
            ->when($filters['paid_by'] ?? null, fn (Builder $query, int $payerId) => $query->where('paid_by', $payerId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('transaction_number', 'like', "%{$search}%"))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return TransactionResource::collection($transactions);
    }

    public function show(
        Request $request,
        Transaction $transaction,
        TransactionAccess $access,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canView($user, $transaction), 403, 'This action is unauthorized.');
        $transaction->load(['operationalUnit', 'creator', 'payer', 'transactionItems.item']);

        return new TransactionResource($transaction);
    }

    public function checkout(
        Request $request,
        Transaction $transaction,
        TransactionCheckoutService $checkout,
    ): TransactionResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return new TransactionResource($checkout->checkout($user, $transaction));
    }
}
