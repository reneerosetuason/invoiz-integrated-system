<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::user();

        if (! $user) {
            return redirect('/logistics/login');
        }

        if (! in_array($user->role, ['admin', 'staff', 'logistics_admin', 'logistics_staff'], true) && empty($user->center_id)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/logistics/login')->with('error', 'This area is restricted to authorized logistics staff.');
        }

        return $next($request);
    }
}