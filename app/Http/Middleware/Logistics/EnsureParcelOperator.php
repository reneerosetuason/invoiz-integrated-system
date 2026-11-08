<?php

namespace App\Http\Middleware\Logistics;

use App\Support\LogisticsAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards parcel counter operations. Logistics Admin management accounts keep
 * read-only monitoring through the same pages and APIs, but every physical
 * parcel action (receive, scan, sort, dispatch, manifest loading/receiving)
 * requires operational staff credentials assigned to a hub.
 */
class EnsureParcelOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'unauthorized',
                    'message' => 'Sign in with a logistics staff account to process parcels.',
                ], 401);
            }

            return redirect('/logistics/login');
        }

        if (! LogisticsAccess::canProcessParcels($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'read_only',
                    'message' => 'Parcel processing is reserved for logistics staff assigned to a hub. Management accounts have read-only access.',
                ], 403);
            }

            abort(403, 'Parcel processing is reserved for logistics staff assigned to a logistics hub. Management accounts have read-only access.');
        }

        return $next($request);
    }
}
