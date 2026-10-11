<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/logistics/login');
        }

        if ($user->role !== 'rider' || $user->status !== 'active') {
            return redirect('/logistics/login')->with('error', 'This area is for rider accounts only.');
        }

        return $next($request);
    }
}