<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ItemType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $items = Item::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('item_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"),
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ItemResource::collection($items);
    }

    public function store(ItemRequest $request, ActivityLogger $logger): JsonResponse
    {
        $attributes = $request->validated();

        $item = DB::transaction(function () use ($attributes, $request, $logger): Item {
            $item = Item::create([
                ...$attributes,
                'visitor_factor' => $attributes['visitor_factor'] ?? 0,
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);
            $logger->log($request->user(), 'ITEM_CREATED', $item, ['item_code' => $item->item_code]);

            return $item;
        });

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    public function show(Item $item): ItemResource
    {
        return new ItemResource($item);
    }

    public function update(ItemRequest $request, Item $item, ActivityLogger $logger): ItemResource
    {
        $item = DB::transaction(function () use ($request, $item, $logger): Item {
            $wasActive = $item->is_active;
            $item->fill($request->validated());
            $changedFields = array_keys($item->getDirty());
            $item->save();

            $statusChanged = $wasActive !== $item->is_active;
            $logger->log($request->user(), $statusChanged ? 'ITEM_STATUS_CHANGED' : 'ITEM_UPDATED', $item, $statusChanged
                ? ['is_active' => $item->is_active]
                : ['changed_fields' => $changedFields]);

            return $item;
        });

        return new ItemResource($item);
    }
}
