<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/login');
        }

        if ($user->role !== 'buyer' || ! $user->isActive()) {
            Auth::logout();
            return redirect('/login');
        }

        view()->share('userName', $user->name);

        return $next($request);
    }
}
