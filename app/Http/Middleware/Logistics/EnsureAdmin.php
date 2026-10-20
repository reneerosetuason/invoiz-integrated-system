<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('/logistics/login');
        }

        if (! $user->isAdmin() && ! in_array($user->role, ['admin', 'logistics_admin'], true)) {
            abort(403, 'This action is restricted to administrators.');
        }

        return $next($request);
    }
}