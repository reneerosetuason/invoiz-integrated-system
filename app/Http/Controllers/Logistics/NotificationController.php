<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Bell JSON feed (XHR with Accept: application/json) and the
     * dedicated /notifications page (browser navigation).
     *
     * Both honor the user's notification preferences: suppressed types
     * never appear in either surface.
     */
    public function index(Request $request): View|JsonResponse
    {
        // The bell only surfaces what this user chose to receive in
        // Settings — Notification Settings; operational alerts that have no
        // preference category are always shown.
        $query = Notification::forUserPreferences($request->user()->id);

        if ($request->wantsJson()) {
            $notifications = (clone $query)->latest()->take(20)->get();
            $unreadCount = (clone $query)->unread()->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        $validated = $request->validate([
            'filter' => 'sometimes|string|in:all,unread',
            'type' => 'sometimes|string|max:50',
        ]);

        $filter = $validated['filter'] ?? 'all';
        $type = $validated['type'] ?? null;

        if ($filter === 'unread') {
            $query->unread();
        }
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        return view('logistics.notifications.index', [
            'notifications' => $query->latest()->paginate(20)->withQueryString(),
            'unreadCount' => Notification::forUserPreferences($request->user()->id)->unread()->count(),
            'filter' => $filter,
            'activeType' => $type,
            'types' => Notification::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $notification->markAsRead();

        return response()->json([
            // Scoped like the bell list itself: suppressed preference types
            // must not inflate the badge.
            'unread_count' => Notification::forUserPreferences(auth()->id())->unread()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::unread()->update(['is_read' => true]);

        return response()->json([
            'unread_count' => Notification::forUserPreferences(auth()->id())->unread()->count(),
        ]);
    }
}
