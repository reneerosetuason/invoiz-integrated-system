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
        View::composer('buyer.layouts.website', function ($view): void {
            $user = auth()->user();
            $pending = null;
            if ($user?->isBuyer() && ! $user->isAdmin() && \Illuminate\Support\Facades\Schema::hasTable('account_deletion_requests')) {
                $pending = app(\App\Services\BuyerAccountDeletionService::class)->active($user);
            }
            $view->with('pendingAccountDeletion', $pending);
        });
    }
}
