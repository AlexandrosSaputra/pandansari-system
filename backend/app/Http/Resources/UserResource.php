<?php

namespace App\Http\Resources;

use App\Models\UserRoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignments = $this->resource->assignments;
        $primary = $assignments->firstWhere('is_active', true) ?? $assignments->first();
        $role = $primary?->role ?? $this->resource->role;
        $unit = $primary?->operationalUnit ?? $this->resource->operationalUnit;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'assignments' => $assignments->map(fn (UserRoleAssignment $assignment): array => [
                'id' => $assignment->id,
                'is_active' => $assignment->is_active,
                'role' => [
                    'id' => $assignment->role->id,
                    'name' => $assignment->role->name,
                    'slug' => $assignment->role->slug,
                ],
                'operational_unit' => $assignment->operationalUnit ? [
                    'id' => $assignment->operationalUnit->id,
                    'code' => $assignment->operationalUnit->code,
                    'name' => $assignment->operationalUnit->name,
                    'type' => $assignment->operationalUnit->type->value,
                ] : null,
            ])->values()->all(),
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ] : null,
            'operational_unit' => $unit ? [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'type' => $unit->type->value,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
