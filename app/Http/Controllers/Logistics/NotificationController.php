<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // The bell only surfaces what this user chose to receive in
        // Settings ??? Notification Settings; operational alerts that have no
        // preference category are always shown.
        $query = Notification::forUserPreferences($request->user()->id);

        $notifications = (clone $query)->latest()->take(20)->get();
        $unreadCount = (clone $query)->unread()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $notification->markAsRead();

        return response()->json([
            // Scoped like the bell list itself: suppressed preference types
            // must not inflate the badge.
            'unread_count' => Notification::forUserPreferences(auth()->id())->unread()->count(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::unread()->update(['is_read' => true]);

        return response()->json([
            'unread_count' => Notification::forUserPreferences(auth()->id())->unread()->count(),
        ]);
    }
}



