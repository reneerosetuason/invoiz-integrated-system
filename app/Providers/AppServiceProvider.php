<?php

namespace App\Providers;

use App\View\Composers\SellerLayoutComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
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
        Blade::anonymousComponentPath(resource_path('views/shared/components'));
        View::composer('seller.layouts.app', SellerLayoutComposer::class);
    }
}
