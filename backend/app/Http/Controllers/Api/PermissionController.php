<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'domain' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z]+(?:-[a-z]+)*$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return PermissionResource::collection(
            Permission::query()
                ->when($filters['domain'] ?? null, fn (Builder $query, string $domain) => $query->where('slug', 'like', $domain.'.%'))
                ->orderBy('slug')
                ->paginate($filters['per_page'] ?? 15)
                ->withQueryString(),
        );
    }
}
