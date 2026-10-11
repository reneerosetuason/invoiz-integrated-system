<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web guard for rider-only pages (e.g. rider messaging).
 * Riders authenticated through the Logistics login may only access
 * these pages; staff/admin are redirected to their dashboard.
 */
class EnsureRiderWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->role !== 'rider' || $user->status !== 'active') {
            return redirect()->route('login')->with('error', 'This area is for rider accounts only.');
        }

        return $next($request);
    }
}
