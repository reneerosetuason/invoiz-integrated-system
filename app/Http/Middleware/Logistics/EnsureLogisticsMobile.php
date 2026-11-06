<?php

namespace App\Http\Middleware\Logistics;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the unified mobile app's logistics scanning surface.
 *
 * A Sanctum token must carry the `logistics` ability (issued only by
 * AuthController after backend role resolution) and the account must still
 * resolve to logistics personnel. Rider and Hub Transport Driver tokens can
 * never reach these endpoints, even if a client calls them directly.
 */
class EnsureLogisticsMobile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user instanceof User
            && $user->currentAccessToken()?->can('logistics')
            && $user->isLogisticsStaff()
            && $user->isActive();

        if (! $allowed) {
            return response()->json([
                'message' => 'This area is restricted to authorized logistics personnel.',
            ], 403);
        }

        return $next($request);
    }
}
