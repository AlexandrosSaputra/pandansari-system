<?php

namespace App\Services;

use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $actor): User {
            $assignments = $this->normalizedAssignments($attributes);
            $this->validateAssignments($assignments, ! array_key_exists('assignments', $attributes));
            $this->validateActiveAccountAssignments(
                $assignments,
                array_key_exists('is_active', $attributes)
                    ? filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN)
                    : true,
            );
            $primary = collect($assignments)->firstWhere('is_active', true) ?? $assignments[0];

            $user = new User;
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);
            $user->forceFill([
                'role_id' => $primary['role_id'],
                'operational_unit_id' => $primary['operational_unit_id'],
                'is_active' => $attributes['is_active'] ?? true,
            ])->save();

            $this->syncAssignments($user, $assignments);

            $this->logger->log($actor, 'USER_CREATED', $user, [
                'assignments' => $assignments,
            ]);

            return $user->load(['assignments.role', 'assignments.operationalUnit']);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->ensureLegacyAssignment();
            $assignments = $this->normalizedAssignments($attributes, $user);
            $this->validateAssignments(
                $assignments,
                ! array_key_exists('assignments', $attributes) && array_key_exists('role_id', $attributes),
            );

            $newIsActive = array_key_exists('is_active', $attributes)
                ? filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN)
                : $user->is_active;
            $this->validateActiveAccountAssignments($assignments, $newIsActive);

            abort_if($user->is($actor) && ! $newIsActive, 409, 'You cannot deactivate your own account.');

            $hadSuperadmin = $user->is_active && $user->assignments()
                ->where('is_active', true)
                ->whereHas('role', fn ($role) => $role->where('slug', 'superadmin'))
                ->exists();
            $superadminRoleId = Role::where('slug', 'superadmin')->value('id');
            $willHaveSuperadmin = $newIsActive && collect($assignments)->contains(
                fn (array $assignment): bool => $assignment['is_active'] && $assignment['role_id'] === $superadminRoleId,
            );

            if ($hadSuperadmin && ! $willHaveSuperadmin) {
                $activeSuperadmins = User::where('is_active', true)
                    ->whereHas('assignments', fn ($query) => $query
                        ->where('is_active', true)
                        ->whereHas('role', fn ($role) => $role->where('slug', 'superadmin')))
                    ->count();
                abort_if($activeSuperadmins <= 1, 409, 'The last active superadmin cannot be removed.');
            }

            if ($user->is($actor) && array_key_exists('assignments', $attributes)) {
                $active = $actor->activeAssignment();
                $activeKey = $active ? $this->assignmentKey($active->role_id, $active->operational_unit_id) : null;
                $keepsActive = ! $activeKey || collect($assignments)->contains(
                    fn (array $assignment): bool => $assignment['is_active']
                        && $this->assignmentKey($assignment['role_id'], $assignment['operational_unit_id']) === $activeKey,
                );
                abort_unless($keepsActive, 409, 'You cannot remove your active role assignment.');
            }

            $wasActive = $user->is_active;
            $user->fill(array_intersect_key($attributes, array_flip(['name', 'email', 'password'])));
            $primary = collect($assignments)->firstWhere('is_active', true) ?? $assignments[0];
            $user->forceFill([
                'role_id' => $primary['role_id'],
                'operational_unit_id' => $primary['operational_unit_id'],
                'is_active' => $newIsActive,
            ]);
            $changedFields = array_values(array_diff(array_keys($user->getDirty()), ['password', 'updated_at']));
            $user->save();
            $this->syncAssignments($user, $assignments);

            $statusChanged = $wasActive !== $newIsActive;
            $this->logger->log($actor, $statusChanged ? 'USER_STATUS_CHANGED' : 'USER_UPDATED', $user, $statusChanged
                ? ['is_active' => $newIsActive]
                : ['changed_fields' => $changedFields, 'assignments' => $assignments]);

            return $user->load(['assignments.role', 'assignments.operationalUnit']);
        });
    }

    /** @param list<array{role_id: int, operational_unit_id: ?int, is_active: bool}> $assignments */
    private function validateAssignments(array $assignments, bool $legacyFields = false): void
    {
        $seen = [];

        foreach ($assignments as $index => $assignment) {
            $role = Role::whereKey($assignment['role_id'])->where('is_active', true)->first();
            $unitId = $assignment['operational_unit_id'];

            if (! $role) {
                $key = $legacyFields ? 'role_id' : "assignments.$index.role_id";
                throw ValidationException::withMessages([$key => 'An active role is required.']);
            }

            if ($role->slug === 'admin') {
                $unit = $unitId ? OperationalUnit::whereKey($unitId)->where('is_active', true)->first() : null;
                if (! $unit) {
                    $key = $legacyFields ? 'operational_unit_id' : "assignments.$index.operational_unit_id";
                    throw ValidationException::withMessages([$key => 'An active operational unit is required for Admin.']);
                }
            } elseif ($unitId !== null) {
                $key = $legacyFields ? 'operational_unit_id' : "assignments.$index.operational_unit_id";
                throw ValidationException::withMessages([$key => 'This role cannot be assigned an operational unit.']);
            }

            $key = $this->assignmentKey($assignment['role_id'], $unitId);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["assignments.$index.role_id" => 'This role assignment is duplicated.']);
            }
            $seen[$key] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{role_id: int, operational_unit_id: ?int, is_active: bool}>
     */
    private function normalizedAssignments(array $attributes, ?User $user = null): array
    {
        if (array_key_exists('assignments', $attributes)) {
            return collect($attributes['assignments'])->map(fn (array $assignment): array => [
                'role_id' => (int) $assignment['role_id'],
                'operational_unit_id' => isset($assignment['operational_unit_id']) ? (int) $assignment['operational_unit_id'] : null,
                'is_active' => array_key_exists('is_active', $assignment)
                    ? filter_var($assignment['is_active'], FILTER_VALIDATE_BOOLEAN)
                    : true,
            ])->values()->all();
        }

        if (array_key_exists('role_id', $attributes)) {
            return [[
                'role_id' => (int) $attributes['role_id'],
                'operational_unit_id' => isset($attributes['operational_unit_id']) ? (int) $attributes['operational_unit_id'] : null,
                'is_active' => true,
            ]];
        }

        return $user?->assignments()->get()->map(fn (UserRoleAssignment $assignment): array => [
            'role_id' => $assignment->role_id,
            'operational_unit_id' => $assignment->operational_unit_id,
            'is_active' => $assignment->is_active,
        ])->all() ?? [];
    }

    /** @param list<array{role_id: int, operational_unit_id: ?int, is_active: bool}> $assignments */
    private function syncAssignments(User $user, array $assignments): void
    {
        $keep = [];

        foreach ($assignments as $assignment) {
            $model = $user->assignments()->updateOrCreate([
                'role_id' => $assignment['role_id'],
                'operational_unit_id' => $assignment['operational_unit_id'],
            ], ['is_active' => $assignment['is_active']]);
            $keep[] = $model->id;
        }

        $user->assignments()->whereNotIn('id', $keep)->delete();
    }

    private function assignmentKey(int $roleId, ?int $unitId): string
    {
        return $roleId.':'.($unitId ?? 'global');
    }

    /** @param list<array{role_id: int, operational_unit_id: ?int, is_active: bool}> $assignments */
    private function validateActiveAccountAssignments(array $assignments, bool $userIsActive): void
    {
        if ($userIsActive && ! collect($assignments)->contains('is_active', true)) {
            throw ValidationException::withMessages([
                'assignments' => 'An active account requires at least one active role assignment.',
            ]);
        }
    }
}
