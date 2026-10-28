<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->email_verified_at || ! $user->canUseBuyerArea()) {
            return response()->json([
                'message' => 'Please sign in with a verified, approved buyer account.',
                'code' => 'buyer_access_denied',
            ], 403);
        }
        return $next($request);
    }
}
