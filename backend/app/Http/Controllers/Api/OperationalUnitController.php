<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryLocationType;
use App\Enums\OperationalUnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OperationalUnitRequest;
use App\Http\Resources\OperationalUnitResource;
use App\Models\InventoryLocation;
use App\Models\OperationalUnit;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationalUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(OperationalUnitType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $units = OperationalUnit::query()->with('inventoryLocation')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"),
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return OperationalUnitResource::collection($units);
    }

    public function store(OperationalUnitRequest $request, ActivityLogger $logger): JsonResponse
    {
        $attributes = $request->validated();

        $unit = DB::transaction(function () use ($attributes, $request, $logger): OperationalUnit {
            $unit = OperationalUnit::create([
                ...$attributes,
                'scanner_enabled' => $attributes['scanner_enabled'] ?? false,
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            InventoryLocation::create([
                'code' => 'UNIT-'.$unit->id,
                'name' => $unit->name,
                'type' => InventoryLocationType::Unit,
                'operational_unit_id' => $unit->id,
                'is_active' => $unit->is_active,
                'created_by' => $request->user()->id,
            ]);

            $logger->log($request->user(), 'OPERATIONAL_UNIT_CREATED', $unit, ['code' => $unit->code]);

            return $unit->load('inventoryLocation');
        });

        return (new OperationalUnitResource($unit))->response()->setStatusCode(201);
    }

    public function show(OperationalUnit $operationalUnit): OperationalUnitResource
    {
        return new OperationalUnitResource($operationalUnit->load('inventoryLocation'));
    }

    public function update(OperationalUnitRequest $request, OperationalUnit $operationalUnit, ActivityLogger $logger): OperationalUnitResource
    {
        $attributes = $request->validated();

        $unit = DB::transaction(function () use ($operationalUnit, $attributes, $request, $logger): OperationalUnit {
            $wasActive = $operationalUnit->is_active;
            $operationalUnit->fill($attributes);
            $changedFields = array_keys($operationalUnit->getDirty());
            $operationalUnit->save();
            $operationalUnit->inventoryLocation()->update([
                'name' => $operationalUnit->name,
                'is_active' => $operationalUnit->is_active,
            ]);

            $statusChanged = $wasActive !== $operationalUnit->is_active;
            $logger->log($request->user(), $statusChanged ? 'OPERATIONAL_UNIT_STATUS_CHANGED' : 'OPERATIONAL_UNIT_UPDATED', $operationalUnit, $statusChanged
                ? ['is_active' => $operationalUnit->is_active]
                : ['changed_fields' => $changedFields]);

            return $operationalUnit->load('inventoryLocation');
        });

        return new OperationalUnitResource($unit);
    }
}
