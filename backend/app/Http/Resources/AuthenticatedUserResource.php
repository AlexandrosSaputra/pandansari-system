<?php

namespace App\Http\Resources;

use App\Models\UserRoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->resource->activeAssignment();
        $role = $assignment?->role;
        $unit = $assignment?->operationalUnit;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'assignments' => $this->resource->usableAssignments()
                ->map(fn (UserRoleAssignment $item): array => $this->assignment($item))
                ->all(),
            'active_assignment' => $assignment ? $this->assignment($assignment) : null,
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
                'scanner_enabled' => $unit->scanner_enabled,
            ] : null,
            'permissions' => $role?->permissions->pluck('slug')->sort()->values()->all() ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function assignment(UserRoleAssignment $assignment): array
    {
        return [
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
                'scanner_enabled' => $assignment->operationalUnit->scanner_enabled,
            ] : null,
        ];
    }
}
