<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\ActivityLogger;
use App\Support\OperationalUnitAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, ActivityLogger $logger): AuthenticatedUserResource|JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $user = Auth::guard('web')->user();

        if ($user instanceof User) {
            $user->ensureLegacyAssignment();
        }

        if (! $user instanceof User || $user->usableAssignments()->isEmpty()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $request->session()->regenerate();
        $request->session()->forget(User::ACTIVE_ASSIGNMENT_SESSION_KEY);

        $assignments = $user->usableAssignments();
        if ($assignments->count() === 1) {
            $request->session()->put(User::ACTIVE_ASSIGNMENT_SESSION_KEY, $assignments->first()->id);
        }

        $logger->log($user, 'AUTH_LOGIN_SUCCESS', $user);

        return $this->userResource($user);
    }

    public function me(): AuthenticatedUserResource
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && $user->usableAssignments()->isNotEmpty(), 401, 'Unauthenticated.');

        return $this->userResource($user);
    }

    public function session(OperationalUnitAccess $access): AuthenticatedUserResource|JsonResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return response()->json(['data' => null]);
        }

        $user->ensureLegacyAssignment();

        if ($user->usableAssignments()->isEmpty()) {
            Auth::guard('web')->logout();

            return response()->json(['data' => null]);
        }

        $assignment = $user->activeAssignment();
        if ($assignment && ! $access->canUseAssignment($user, $assignment)) {
            request()->session()->forget(User::ACTIVE_ASSIGNMENT_SESSION_KEY);
        }

        return $this->userResource($user);
    }

    public function selectAssignment(Request $request, OperationalUnitAccess $access, ActivityLogger $logger): AuthenticatedUserResource
    {
        $attributes = $request->validate([
            'assignment_id' => ['required', 'integer'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $assignment = $user->assignments()
            ->with(['role.permissions', 'operationalUnit'])
            ->find($attributes['assignment_id']);

        abort_unless($assignment instanceof UserRoleAssignment && $access->canUseAssignment($user, $assignment), 422, 'The selected role assignment is unavailable.');

        $request->session()->put(User::ACTIVE_ASSIGNMENT_SESSION_KEY, $assignment->id);
        $logger->log($user, 'AUTH_ASSIGNMENT_SELECTED', $user, [
            'assignment_id' => $assignment->id,
            'role_id' => $assignment->role_id,
            'operational_unit_id' => $assignment->operational_unit_id,
        ]);

        return $this->userResource($user);
    }

    public function logout(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless(Auth::guard('web')->check(), 401, 'Unauthenticated.');

        $user = Auth::guard('web')->user();
        $logger->log($user, 'AUTH_LOGOUT', $user);
        $request->session()->forget(User::ACTIVE_ASSIGNMENT_SESSION_KEY);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userResource(User $user): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($user);
    }
}
