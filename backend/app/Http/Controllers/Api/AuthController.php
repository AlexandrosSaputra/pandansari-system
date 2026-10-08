<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\OperationalUnitAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, OperationalUnitAccess $access, ActivityLogger $logger): AuthenticatedUserResource|JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $access->canUseApplication($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $request->session()->regenerate();
        $logger->log($user, 'AUTH_LOGIN_SUCCESS', $user);

        return $this->userResource($user);
    }

    public function me(Request $request, OperationalUnitAccess $access): AuthenticatedUserResource
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && $access->canUseApplication($user), 401, 'Unauthenticated.');

        return $this->userResource($user);
    }

    public function logout(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless(Auth::guard('web')->check(), 401, 'Unauthenticated.');

        $user = Auth::guard('web')->user();
        $logger->log($user, 'AUTH_LOGOUT', $user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userResource(User $user): AuthenticatedUserResource
    {
        $user->load(['role.permissions', 'operationalUnit']);

        return new AuthenticatedUserResource($user);
    }
}
