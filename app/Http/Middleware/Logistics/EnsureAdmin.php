<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/logistics/login');
        }

        if (! $user->isAdmin() && ! $user->isLogisticsAdmin()) {
            abort(403, 'This action is restricted to administrators.');
        }

        if (! $user->isActive()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is not active.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/logistics/login')->with('error', 'Your account is not active.');
        }

        return $next($request);
    }
}
