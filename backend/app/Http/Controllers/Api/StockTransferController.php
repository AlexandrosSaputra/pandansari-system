<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStockTransferRequest;
use App\Http\Requests\RejectStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockTransferService;
use App\Support\OperationalUnitAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index(Request $request, OperationalUnitAccess $access): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(StockTransferStatus::class)],
            'destination_operational_unit_id' => ['sometimes', 'integer', 'exists:operational_units,id'],
            'created_by' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $transfers = $access->scopeStockTransfers(StockTransfer::query(), $user)
            ->with(['sourceLocation', 'destinationLocation.operationalUnit', 'creator', 'responder'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('transfer_number', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['destination_operational_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query
                ->whereHas('destinationLocation', fn (Builder $location) => $location->where('operational_unit_id', $unitId)))
            ->when($filters['created_by'] ?? null, fn (Builder $query, int $creatorId) => $query->where('created_by', $creatorId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return StockTransferResource::collection($transfers);
    }

    public function store(
        CreateStockTransferRequest $request,
        OperationalUnitAccess $access,
        StockTransferService $transfers,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canAccessWarehouse($user), 403, 'This action is unauthorized.');
        $attributes = $request->validated();
        $transfer = $transfers->create($user, $attributes['destination_operational_unit_id'], $attributes['items']);

        return (new StockTransferResource($transfer))->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        StockTransfer $stockTransfer,
        OperationalUnitAccess $access,
    ): StockTransferResource {
        $user = $request->user();
        $stockTransfer->load([
            'sourceLocation',
            'destinationLocation.operationalUnit',
            'creator',
            'responder',
            'transferItems.item',
        ]);
        abort_unless($user instanceof User && $access->canViewTransfer($user, $stockTransfer), 403, 'This action is unauthorized.');

        return new StockTransferResource($stockTransfer);
    }

    public function accept(
        Request $request,
        StockTransfer $stockTransfer,
        OperationalUnitAccess $access,
        StockTransferService $transfers,
    ): StockTransferResource {
        $user = $request->user();
        $stockTransfer->load('destinationLocation');
        abort_unless($user instanceof User && $access->canRespondToTransfer($user, $stockTransfer), 403, 'This action is unauthorized.');

        return new StockTransferResource($transfers->accept($user, $stockTransfer));
    }

    public function reject(
        RejectStockTransferRequest $request,
        StockTransfer $stockTransfer,
        OperationalUnitAccess $access,
        StockTransferService $transfers,
    ): StockTransferResource {
        $user = $request->user();
        $stockTransfer->load('destinationLocation');
        abort_unless($user instanceof User && $access->canRespondToTransfer($user, $stockTransfer), 403, 'This action is unauthorized.');

        return new StockTransferResource($transfers->reject($user, $stockTransfer, $request->validated('rejection_reason')));
    }
}
