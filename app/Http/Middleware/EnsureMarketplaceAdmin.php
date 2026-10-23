<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protect the marketplace administration surface.
 *
 * The admin login has always identified administrators by the legacy
 * `is_admin` flag. Keep that as the canonical rule here so accounts with
 * `role = admin` but `is_admin = false` are not silently granted access.
 */
class EnsureMarketplaceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            if ($wantsJson) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        if (! $user->is_admin) {
            if ($wantsJson) {
                return response()->json([
                    'message' => 'This action is restricted to administrators.',
                ], 403);
            }

            abort(403, 'This action is restricted to administrators.');
        }

        if (! $user->isActive()) {
            if ($wantsJson) {
                return response()->json(['message' => 'Your account is not active.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Your account is not active.',
            ]);
        }

        return $next($request);
    }
}
