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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();