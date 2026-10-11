<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderRole
{
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