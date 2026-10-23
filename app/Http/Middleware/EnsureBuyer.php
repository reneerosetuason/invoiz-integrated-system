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

        $hasApprovedStore = $user->canSell();

        if (! $user->canUseBuyerArea() || ! $user->isActive()) {
            Auth::logout();
            return redirect('/login');
        }

        if ($hasApprovedStore && $request->session()->get('active_role') !== 'buyer') {
            return redirect()->route('choose');
        }

        view()->share('userName', $user->name);

        return $next($request);
    }
}
