<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\SellerNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = SellerNotification::forSeller(auth()->id())
            ->latest()
            ->paginate(30);

        return view('seller.notifications.index', compact('notifications'));
    }

    public function markRead($id)
    {
        SellerNotification::forSeller(auth()->id())
            ->where('id', $id)
            ->update(['is_read' => true]);

        return back();
    }

    public function markAllRead()
    {
        SellerNotification::forSeller(auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back();
    }
}