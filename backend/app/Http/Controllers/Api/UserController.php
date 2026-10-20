<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'max:255'],
            'operational_unit_id' => ['sometimes', 'integer', 'exists:operational_units,id'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()->with(['assignments.role', 'assignments.operationalUnit', 'role', 'operationalUnit'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when($filters['role'] ?? null, fn (Builder $query, string $slug) => $query->where(
                fn (Builder $query) => $query
                    ->whereHas('assignments.role', fn (Builder $role) => $role->where('slug', $slug))
                    ->orWhereHas('role', fn (Builder $role) => $role->where('slug', $slug)),
            ))
            ->when($filters['operational_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query->where(
                fn (Builder $query) => $query
                    ->whereHas('assignments', fn (Builder $assignment) => $assignment->where('operational_unit_id', $unitId))
                    ->orWhere('operational_unit_id', $unitId),
            ))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request, UserManager $manager): JsonResponse
    {
        $user = $manager->create($request->validated(), $request->user());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $user->ensureLegacyAssignment();

        return new UserResource($user->load(['assignments.role', 'assignments.operationalUnit', 'role', 'operationalUnit']));
    }

    public function update(UserRequest $request, User $user, UserManager $manager): UserResource
    {
        return new UserResource($manager->update($user, $request->validated(), $request->user()));
    }
}
