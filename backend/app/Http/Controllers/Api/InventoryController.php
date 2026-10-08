<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockInRequest;
use App\Http\Requests\StockOutRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\UnitInventoryResource;
use App\Http\Resources\WarehouseInventoryResource;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\WarehouseStockService;
use App\Support\OperationalUnitAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function warehouse(
        Request $request,
        OperationalUnitAccess $access,
        WarehouseStockService $stock,
    ): AnonymousResourceCollection {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canAccessWarehouse($user), 403, 'This action is unauthorized.');

        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $warehouse = $stock->mainWarehouse();

        $items = Item::query()
            ->where('items.type', ItemType::Product->value)
            ->leftJoin('inventory_balances as balance', function (JoinClause $join) use ($warehouse): void {
                $join->on('balance.item_id', '=', 'items.id')
                    ->where('balance.inventory_location_id', $warehouse->id);
            })
            ->select('items.*')
            ->selectRaw('COALESCE(balance.quantity_on_hand, 0) as quantity_on_hand')
            ->selectRaw('COALESCE(balance.quantity_reserved, 0) as quantity_reserved')
            ->addSelect('balance.updated_at as balance_updated_at')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $item) => $item->where('items.item_code', 'like', "%{$search}%")
                    ->orWhere('items.barcode', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%"),
            ))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query
                ->where('items.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('items.name')
            ->orderBy('items.id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return WarehouseInventoryResource::collection($items);
    }

    public function unit(
        Request $request,
        OperationalUnit $operationalUnit,
        OperationalUnitAccess $access,
    ): AnonymousResourceCollection {
        $user = $request->user();
        abort_unless($user instanceof User && $access->allowsInventoryRead($user, $operationalUnit), 403, 'This action is unauthorized.');

        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $location = $operationalUnit->inventoryLocation()->units()->firstOrFail();

        $items = UnitItem::query()
            ->where('unit_items.operational_unit_id', $operationalUnit->id)
            ->where('unit_items.is_active', true)
            ->whereHas('item', fn (Builder $item) => $item
                ->where('type', ItemType::Product->value)
                ->where('is_active', true))
            ->leftJoin('inventory_balances as balance', function (JoinClause $join) use ($location): void {
                $join->on('balance.item_id', '=', 'unit_items.item_id')
                    ->where('balance.inventory_location_id', $location->id);
            })
            ->select('unit_items.*')
            ->selectRaw('COALESCE(balance.quantity_on_hand, 0) as quantity_on_hand')
            ->selectRaw('COALESCE(balance.quantity_reserved, 0) as quantity_reserved')
            ->addSelect('balance.updated_at as balance_updated_at')
            ->with(['item', 'operationalUnit'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('item',
                fn (Builder $item) => $item->where('item_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"),
            ))
            ->orderBy('unit_items.id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UnitInventoryResource::collection($items);
    }

    public function movements(Request $request, OperationalUnitAccess $access): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canReadInventory($user), 403, 'This action is unauthorized.');

        $filters = $request->validate([
            'inventory_location_id' => ['sometimes', 'integer', 'exists:inventory_locations,id'],
            'item_id' => ['sometimes', 'integer', 'exists:items,id'],
            'movement_type' => ['sometimes', Rule::enum(StockMovementType::class)],
            'direction' => ['sometimes', Rule::enum(StockDirection::class)],
            'performed_by' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = $access->scopeStockMovements(StockMovement::query(), $user)
            ->with(['inventoryLocation', 'item', 'performer'])
            ->when($filters['inventory_location_id'] ?? null, fn (Builder $query, int $id) => $query->where('inventory_location_id', $id))
            ->when($filters['item_id'] ?? null, fn (Builder $query, int $id) => $query->where('item_id', $id))
            ->when($filters['movement_type'] ?? null, fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['performed_by'] ?? null, fn (Builder $query, int $id) => $query->where('performed_by', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }

    public function stockIn(
        StockInRequest $request,
        OperationalUnitAccess $access,
        WarehouseStockService $stock,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canAccessWarehouse($user), 403, 'This action is unauthorized.');
        $attributes = $request->validated();
        $movement = $stock->stockIn($user, $attributes['item_id'], $attributes['quantity'], $attributes['note'] ?? null);

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }

    public function stockOut(
        StockOutRequest $request,
        OperationalUnitAccess $access,
        WarehouseStockService $stock,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $access->canAccessWarehouse($user), 403, 'This action is unauthorized.');
        $attributes = $request->validated();
        $movement = $stock->stockOut($user, $attributes['item_id'], $attributes['quantity'], $attributes['note'] ?? null);

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}
