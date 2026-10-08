<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return RoleResource::collection(
            Role::query()->with('permissions')->orderBy('slug')->paginate($filters['per_page'] ?? 15)->withQueryString(),
        );
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }
}
