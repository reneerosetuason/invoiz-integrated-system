<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

use App\Http\Middleware\ApplyUserTimezone;
use App\Http\Middleware\Logistics\EnsureRiderRole;
use App\Http\Middleware\Logistics\EnsureRiderWeb;
use App\Http\Middleware\Logistics\EnsureLogisticsStaff;
use App\Http\Middleware\Logistics\EnsureAdmin;

require_once __DIR__ . '/../app/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
        $middleware->appendToGroup('web', ApplyUserTimezone::class);
        $middleware->alias([
            'seller' => \App\Http\Middleware\EnsureSeller::class,
            'buyer' => \App\Http\Middleware\EnsureBuyer::class,
            'staff' => \App\Http\Middleware\Logistics\EnsureLogisticsStaff::class,
            'admin' => \App\Http\Middleware\Logistics\EnsureAdmin::class,
            'rider.web' => \App\Http\Middleware\Logistics\EnsureRiderWeb::class,
            'rider' => \App\Http\Middleware\Logistics\EnsureRiderRole::class,
            // routes/logistics/api.php gates the rider API on role:rider (the
            // alias the standalone logistic-web app used). Without this entry
            // every route in that group throws
            // "Target class [role] does not exist". Kept alongside 'rider'
            // so both spellings resolve to the same middleware.
            'role' => \App\Http\Middleware\Logistics\EnsureRiderRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Logging out with a stale/expired CSRF token (double-clicks,
        // back-button resubmits, long-idle tabs) must never strand users on
        // a 419 page: the intent is to end the session, so end it and send
        // them to the matching login screen instead. NOTE: the handler
        // converts TokenMismatchException to HttpException(419) before
        // render callbacks run, so match on that.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $request->isMethod('post')) {
                return null;
            }
            if ($request->is('logistics/logout')) {
                $target = '/logistics/login';
            } elseif ($request->is('logout') || $request->is('admin/logout') || $request->is('seller/logout') || $request->is('buyer/logout')) {
                $target = '/login';
            } elseif ($request->is('logistics/deliveries*')) {
                // Stale-token submits from the deliveries modals (long-idle tabs,
                // back-button resubmits): send them back to the list with a fresh
                // token instead of stranding them on a 419 page. Nothing was changed.
                return redirect()->route('deliveries.index')->with('error', 'Your session had expired, so nothing was archived. Please try again.');
            } else {
                return null;
            }
            try {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } catch (\Throwable $ignored) {
            }

            return redirect($target)->with('error', 'Your session had expired, so you have been signed out. Please sign in again.');
        });
    })->create();