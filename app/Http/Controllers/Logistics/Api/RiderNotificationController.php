<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderNotificationController extends Controller
{
    /**
     * List rider notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $notifications = $rider->notifications()->latest()->paginate(20);

        return response()->json([
            'notifications' => collect($notifications->items())
                ->map(fn ($n) => $this->payload($n))->values(),
            'unread_count' => $rider->notifications()->where('is_read', false)->count(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, RiderNotification $notification): JsonResponse
    {
        $rider = $request->user()->rider;

        if ((int) $notification->rider_id !== (int) $rider->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => $this->payload($notification->fresh()),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $rider->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    private function payload(RiderNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}




