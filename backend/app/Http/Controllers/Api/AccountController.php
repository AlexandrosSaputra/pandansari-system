<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function updateProfile(AccountProfileRequest $request, ActivityLogger $logger): AuthenticatedUserResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        DB::transaction(function () use ($user, $request, $logger): void {
            $user->fill($request->validated());
            $changedFields = array_values(array_diff(array_keys($user->getDirty()), ['updated_at']));
            $user->save();

            if ($changedFields !== []) {
                $logger->log($user, 'ACCOUNT_PROFILE_UPDATED', $user, [
                    'changed_fields' => $changedFields,
                ]);
            }
        });

        $user->ensureLegacyAssignment();

        return new AuthenticatedUserResource($user->refresh());
    }

    public function updatePassword(ChangePasswordRequest $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        DB::transaction(function () use ($user, $request, $logger): void {
            $user->update(['password' => $request->validated('password')]);
            $logger->log($user, 'ACCOUNT_PASSWORD_UPDATED', $user);
        });

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }

    public function activityLogs(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $filters = $request->validate([
            'action' => ['sometimes', 'string', 'max:255'],
            'subject_type' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = ActivityLog::query()
            ->where('actor_id', $user->id)
            ->with('actor')
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $subjectType) => $query->where('subject_type', $subjectType))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }
}
