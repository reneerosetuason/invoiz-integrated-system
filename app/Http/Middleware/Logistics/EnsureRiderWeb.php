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

        // Linked riders keep their marketplace role; the Rider row carries
        // rider access. The profile must be approved and not inactive.
        if (! $user->hasApprovedRiderProfile() || ! $user->isActive()) {
            return redirect('/logistics/login')->with('error', 'This area is for rider accounts only.');
        }

        return $next($request);
    }
}