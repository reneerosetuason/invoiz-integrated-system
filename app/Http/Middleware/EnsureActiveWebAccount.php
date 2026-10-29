<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveWebAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $buyerId = $request->session()->get('buyer.id');
        $user = $request->user() ?? ($buyerId ? User::find($buyerId) : null);

        if (($user && ! $user->isActive()) || ($buyerId && ! $user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is not active.'], 403);
            }

            $loginPath = match (true) {
                $request->is('admin/*') => '/admin/login',
                $request->is('logistics/*') => '/logistics/login',
                $request->is('seller/*') => '/seller/login',
                default => '/login',
            };

            if ($request->is('logistics/*') && ! $request->isMethod('POST')) {
                return redirect($loginPath)->with('error', 'Your account is not active.');
            }

            return redirect($loginPath)->withErrors(['email' => 'Your account is not active.']);
        }

        return $next($request);
    }
}
