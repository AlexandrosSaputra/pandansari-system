<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\OperationalUnitAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private OperationalUnitAccess $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($this->access->canUseApplication($user), 403, 'This action is unauthorized.');
        abort_unless($user->hasPermission($permission), 403, 'This action is unauthorized.');

        return $next($request);
    }
}
