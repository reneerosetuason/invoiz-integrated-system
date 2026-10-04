<?php

namespace App\View\Composers;

use App\Models\SellerNotification;
use Illuminate\View\View;

class SellerLayoutComposer
{
    public function compose(View $view): void
    {
        $notifications = collect();
        $unreadCount = 0;

        if (auth()->check()) {
            $notifications = SellerNotification::forSeller(auth()->id())
                ->latest()
                ->limit(8)
                ->get();

            $unreadCount = SellerNotification::forSeller(auth()->id())
                ->where('is_read', false)
                ->count();
        }

        $view->with('layoutNotifications', $notifications);
        $view->with('layoutUnreadCount', $unreadCount);
    }
}