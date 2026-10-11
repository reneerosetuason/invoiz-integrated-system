<?php

namespace App\Providers;

use App\Listeners\RecordLoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('permanentlyDelete', function ($user) {
            return $user->role === 'admin';
        });

        Event::listen(Login::class, RecordLoginHistory::class);
    }
}
