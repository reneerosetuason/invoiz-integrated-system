<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/** Shared limits across login aliases, keyed by account AND by source IP. */
class AuthenticationThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $isLogin = str_ends_with($request->path(), 'login');
        $group = $isLogin ? 'login' : 'recovery-registration';
        $email = is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : '';
        $keys = [
            ['auth:'.$group.':ip:'.hash('sha256', (string) $request->ip()), $isLogin ? 30 : 15],
            ['auth:'.$group.':account:'.hash('sha256', $email.'|'.$request->ip()), $isLogin ? 5 : 5],
        ];
        foreach ($keys as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $wait = RateLimiter::availableIn($key);
                return $request->expectsJson()
                    ? response()->json(['message' => 'Too many attempts. Please try again in a minute.'], 429, ['Retry-After' => $wait])
                    : response('Too many attempts. Please try again in a minute.', 429, ['Retry-After' => $wait]);
            }
        }
        foreach ($keys as [$key]) {
            RateLimiter::hit($key, 60);
        }
        return $next($request);
    }
}
