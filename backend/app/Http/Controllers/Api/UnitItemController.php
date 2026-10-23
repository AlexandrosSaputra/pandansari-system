<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnitItemRequest;
use App\Http\Resources\UnitItemResource;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitItemController extends Controller
{
    public function index(Request $request, OperationalUnit $operationalUnit): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ItemType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $items = $operationalUnit->unitItems()->with(['item', 'operationalUnit'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('item',
                fn (Builder $item) => $item->where('item_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"),
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->whereHas(
                'item',
                fn (Builder $item) => $item->where('type', $type),
            ))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UnitItemResource::collection($items);
    }

    public function store(UnitItemRequest $request, OperationalUnit $operationalUnit, ActivityLogger $logger): JsonResponse
    {
        $attributes = $request->validated();

        $unitItem = DB::transaction(function () use ($attributes, $request, $operationalUnit, $logger): UnitItem {
            $unitItem = UnitItem::create([
                ...$attributes,
                'operational_unit_id' => $operationalUnit->id,
                'minimum_stock' => $attributes['minimum_stock'] ?? 0,
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);
            $logger->log($request->user(), 'UNIT_ITEM_CREATED', $unitItem, [
                'operational_unit_id' => $operationalUnit->id,
                'item_id' => $unitItem->item_id,
            ]);

            return $unitItem->load(['item', 'operationalUnit']);
        });

        return (new UnitItemResource($unitItem))->response()->setStatusCode(201);
    }

    public function update(UnitItemRequest $request, OperationalUnit $operationalUnit, UnitItem $unitItem, ActivityLogger $logger): UnitItemResource
    {
        abort_unless($unitItem->operational_unit_id === $operationalUnit->id, 404);

        $unitItem = DB::transaction(function () use ($request, $unitItem, $logger): UnitItem {
            $wasActive = $unitItem->is_active;
            $unitItem->fill($request->validated());
            $changedFields = array_keys($unitItem->getDirty());
            $unitItem->save();

            $statusChanged = $wasActive !== $unitItem->is_active;
            $logger->log($request->user(), $statusChanged ? 'UNIT_ITEM_STATUS_CHANGED' : 'UNIT_ITEM_UPDATED', $unitItem, $statusChanged
                ? ['is_active' => $unitItem->is_active]
                : ['changed_fields' => $changedFields]);

            return $unitItem->load(['item', 'operationalUnit']);
        });

        return new UnitItemResource($unitItem);
    }
}
