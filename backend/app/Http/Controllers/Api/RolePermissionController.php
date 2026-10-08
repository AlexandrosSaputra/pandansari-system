<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    public function update(RolePermissionsRequest $request, Role $role, ActivityLogger $logger): RoleResource
    {
        abort_if($role->slug === 'superadmin', 409, 'Superadmin permissions are managed by the system seeder.');

        $slugs = array_values(array_unique($request->validated('permissions')));

        $role = DB::transaction(function () use ($role, $slugs, $request, $logger): Role {
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_if($role->slug === 'superadmin', 409, 'Superadmin permissions are managed by the system seeder.');

            $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id')->all();
            $role->permissions()->sync($permissionIds);
            $logger->log($request->user(), 'ROLE_PERMISSIONS_UPDATED', $role, ['permissions' => $slugs]);

            return $role->load('permissions');
        });

        return new RoleResource($role);
    }
}
