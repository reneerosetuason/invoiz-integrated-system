<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderRole
{
    /**
     * Ensure the authenticated user has the given role.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role) {
            return response()->json([
                'message' => 'Forbidden. You are not authorized to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}