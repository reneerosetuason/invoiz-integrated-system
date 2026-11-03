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

        $allowed = $user instanceof \App\Models\User && ($role === 'rider'
            ? ($user->hasApprovedRiderProfile() && $request->user()->currentAccessToken()?->can('rider'))
            : $user->role === $role);

        if (! $allowed) {
            return response()->json([
                'message' => 'Forbidden. You are not authorized to access this resource.',
            ], 403);
        }

        // Existing bearer tokens must stop working as soon as an account is
        // suspended or deactivated, not only at the next login.
        if (! $user->isActive()) {
            $status = $user->account_status ?: $user->status;

            return response()->json([
                'message' => $status === 'suspended'
                    ? 'Your account has been suspended. Please contact an administrator.'
                    : 'Your account is currently inactive. Please contact an administrator.',
            ], 403);
        }

        return $next($request);
    }
}