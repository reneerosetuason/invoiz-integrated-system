<?php

namespace App\Http\Middleware\Logistics;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderManager
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageRiders(),
            403,
            'Rider management is restricted to authorized logistics personnel.'
        );

        return $next($request);
    }
}
