<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        @header_remove('X-Powered-By');

        $response = $next($request);

        $response->headers->remove('X-Powered-By');

        foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin'] as $name => $value) {
            if (! $response->headers->has($name)) $response->headers->set($name, $value);
        }
        // Report-only CSP: observes inline/remote script usage without blocking
        // anything. Violations appear in the browser console only; flip to an
        // enforcing Content-Security-Policy once inline JS is audited.
        if (! $response->headers->has('Content-Security-Policy-Report-Only') && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy-Report-Only', "default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.tailwindcss.com blob:; connect-src 'self' https: ws: wss:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        }
        return $response;
    }
}
