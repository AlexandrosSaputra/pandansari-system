<?php

namespace App\Services;

use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $actor): User {
            $this->validateAssignment((int) $attributes['role_id'], $attributes['operational_unit_id'] ?? null);

            $user = new User;
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);
            $user->forceFill([
                'role_id' => $attributes['role_id'],
                'operational_unit_id' => $attributes['operational_unit_id'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ])->save();

            $this->logger->log($actor, 'USER_CREATED', $user, [
                'role_id' => $user->role_id,
                'operational_unit_id' => $user->operational_unit_id,
            ]);

            return $user->load(['role', 'operationalUnit']);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $superadminRole = Role::where('slug', 'superadmin')->lockForUpdate()->first();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $roleId = (int) ($attributes['role_id'] ?? $user->role_id);
            $unitId = array_key_exists('operational_unit_id', $attributes)
                ? $attributes['operational_unit_id']
                : $user->operational_unit_id;
            $this->validateAssignment($roleId, $unitId);

            $newIsActive = array_key_exists('is_active', $attributes)
                ? filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN)
                : $user->is_active;

            abort_if($user->is($actor) && ! $newIsActive, 409, 'You cannot deactivate your own account.');

            if ($superadminRole && $user->role_id === $superadminRole->id && $user->is_active && ($roleId !== $superadminRole->id || ! $newIsActive)) {
                $activeSuperadmins = User::where('role_id', $superadminRole->id)->where('is_active', true)->count();
                abort_if($activeSuperadmins <= 1, 409, 'The last active superadmin cannot be removed.');
            }

            $wasActive = $user->is_active;
            $user->fill(array_intersect_key($attributes, array_flip(['name', 'email', 'password'])));
            $user->forceFill([
                'role_id' => $roleId,
                'operational_unit_id' => $unitId,
                'is_active' => $newIsActive,
            ]);
            $changedFields = array_values(array_diff(array_keys($user->getDirty()), ['password', 'updated_at']));
            $user->save();

            $statusChanged = $wasActive !== $newIsActive;
            $this->logger->log($actor, $statusChanged ? 'USER_STATUS_CHANGED' : 'USER_UPDATED', $user, $statusChanged
                ? ['is_active' => $newIsActive]
                : ['changed_fields' => $changedFields]);

            return $user->load(['role', 'operationalUnit']);
        });
    }

    private function validateAssignment(int $roleId, mixed $unitId): void
    {
        $role = Role::whereKey($roleId)->where('is_active', true)->first();

        if (! $role) {
            throw ValidationException::withMessages(['role_id' => 'An active role is required.']);
        }

        if ($role->slug === 'admin') {
            $unit = $unitId ? OperationalUnit::whereKey($unitId)->where('is_active', true)->first() : null;

            if (! $unit) {
                throw ValidationException::withMessages(['operational_unit_id' => 'An active operational unit is required for Admin.']);
            }

            return;
        }

        if ($unitId !== null) {
            throw ValidationException::withMessages(['operational_unit_id' => 'This role cannot be assigned an operational unit.']);
        }
    }
}
