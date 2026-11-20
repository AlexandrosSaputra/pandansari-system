<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Support\TransactionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardSalesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, TransactionAccess $access): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->activeRole()?->slug === 'admin' && $access->canViewTransactions($user), 403);
        $unit = $user->activeOperationalUnit();
        abort_unless($unit?->is_active, 403);
        $filters = $request->validate(['days' => ['sometimes', 'integer', 'in:7,30']]);
        $days = (int) ($filters['days'] ?? 7);
        $now = CarbonImmutable::now('Asia/Jakarta');
        $start = $now->startOfDay()->subDays($days - 1);
        $paid = $access->scopeTransactions(Transaction::query(), $user)
            ->where('status', TransactionStatus::Paid->value)
            ->whereBetween('paid_at', [$start->utc(), $now->utc()]);

        $daily = (clone $paid)->toBase()
            ->selectRaw('DATE(DATE_ADD(paid_at, INTERVAL 7 HOUR)) as date, SUM(total) as revenue, COUNT(*) as transactions, SUM(visitor_count) as visitors')
            ->groupBy('date')->orderBy('date')->get()->keyBy('date');
        $series = [];
        for ($day = $start; $day->lte($now); $day = $day->addDay()) {
            $row = $daily->get($day->toDateString());
            $series[] = [
                'date' => $day->toDateString(),
                'revenue' => (string) ($row?->revenue ?? '0.00'),
                'transactions' => (int) ($row?->transactions ?? 0),
            ];
        }
        $lines = TransactionItem::query()->whereIn('transaction_id', (clone $paid)->select('transactions.id'));
        $topItems = (clone $lines)->join('items', 'items.id', '=', 'transaction_items.item_id')
            ->selectRaw('items.id, items.name, items.uom, SUM(transaction_items.quantity) as quantity, SUM(transaction_items.subtotal) as revenue')
            ->groupBy('items.id', 'items.name', 'items.uom')
            ->orderByDesc('quantity')->orderBy('items.id')->limit(5)->get();
        $revenue = $daily->sum('revenue');
        $count = (int) $daily->sum('transactions');

        return response()->json(['data' => [
            'period' => ['from' => $start->toDateString(), 'to' => $now->toDateString(), 'days' => $days, 'timezone' => 'Asia/Jakarta'],
            'revenue' => number_format((float) $revenue, 2, '.', ''),
            'transactions' => $count,
            'average_transaction' => number_format($count > 0 ? $revenue / $count : 0, 2, '.', ''),
            'items_sold' => (int) (clone $lines)->sum('quantity'),
            'visitors' => (int) $daily->sum('visitors'),
            'daily' => $series,
            'top_items' => $topItems->map(fn ($item): array => [
                'id' => $item->id, 'name' => $item->name, 'uom' => $item->uom,
                'quantity' => (int) $item->quantity, 'revenue' => (string) $item->revenue,
            ]),
            'recent_transactions' => TransactionResource::collection((clone $paid)
                ->with(['operationalUnit', 'creator', 'payer'])->orderByDesc('paid_at')->orderByDesc('id')->limit(5)->get()),
            'generated_at' => $now->toIso8601String(),
        ]]);
    }
}
