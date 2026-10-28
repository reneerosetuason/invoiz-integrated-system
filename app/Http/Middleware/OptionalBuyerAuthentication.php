<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Public browsing remains public; bearer clients also receive their own state. */
class OptionalBuyerAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
            abort_unless($user, 401, 'Unauthenticated.');
            abort_unless($user->isActive(), 403, 'Your account is not active.');
            $request->setUserResolver(fn () => $user);
        }

        return $next($request);
    }
}
