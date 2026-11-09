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
                $request->is('logistics/*') => '/logistics/login',
                $request->is('seller/*') => '/seller/login',
                default => '/login',
            };

            if ($request->is('logistics/*') && ! $request->isMethod('POST')) {
                return redirect($loginPath)->with('error', 'Your account is not active.');
            }

            return redirect($loginPath)->withErrors(['email' => 'Your account is not active.']);
        }

        if ($user?->isBuyer() && ! $user->isAdmin() && ! $request->isMethodSafe()) {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $next) {
                $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $fresh->isActive()) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return $request->expectsJson()
                        ? response()->json(['message' => config('account_deletion.inactive_message')], 403)
                        : redirect('/login')->withErrors(['email' => config('account_deletion.inactive_message')]);
                }
                return $next($request);
            });
        }
        return $next($request);
    }
}
