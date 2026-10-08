<?php

namespace App\Support;

use App\Models\OperationalUnit;
use App\Models\User;

class OperationalUnitAccess
{
    public function canUseApplication(User $user): bool
    {
        $role = $user->role;

        if (! $user->is_active || ! $role?->is_active) {
            return false;
        }

        if ($role->slug === 'admin') {
            return $user->operationalUnit?->is_active === true;
        }

        return true;
    }

    public function allows(User $user, OperationalUnit $unit): bool
    {
        if (! $this->canUseApplication($user)) {
            return false;
        }

        return match ($user->role->slug) {
            'superadmin' => true,
            'admin' => $unit->is_active && $user->operational_unit_id === $unit->id,
            default => false,
        };
    }
}
