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

            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }

        return $next($request);
    }
}
