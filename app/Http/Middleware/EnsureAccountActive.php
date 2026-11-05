<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => $user->archived_at ? config('account_deletion.inactive_message') : 'Your account is not active.', 'code' => 'account_inactive'], 403);
        }

        if ($user->isBuyer() && ! $user->isAdmin() && ! $request->isMethodSafe()) {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $next) {
                $fresh = \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $fresh->isActive()) return response()->json(['message' => config('account_deletion.inactive_message'), 'code' => 'account_inactive'], 403);
                return $next($request);
            });
        }

        return $next($request);
    }
}
