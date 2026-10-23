<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderHistoryController extends Controller
{
    /**
     * Rider delivery history (delivered / failed), searchable and filterable.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $validated = $request->validate([
            'search' => 'sometimes|string|max:255',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'status' => 'sometimes|string|in:delivered,failed,lost,damaged',
        ]);

        $query = Delivery::with(['items'])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['delivered', 'delivery_failed', 'lost', 'damaged']);

        if (! empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('tracking_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('recipient_name', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('sender_name', 'like', '%' . $validated['search'] . '%');
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'failed') {
                $query->whereIn('status', ['delivery_failed', 'lost', 'damaged']);
            } else {
                $query->where('status', $validated['status']);
            }
        }

        $items = $query->latest()->paginate(15);

        return response()->json([
            'history' => collect($items->items())
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'tracking_number' => $d->tracking_number,
                    'shop_name' => $d->sender_name,
                    'customer_name' => $d->recipient_name,
                    'delivered_at' => $d->delivered_at?->toIso8601String(),
                    'status' => $d->status,
                    'status_label' => $d->status === 'delivered' ? 'Delivered' : ($d->status === 'lost' ? 'Lost' : ($d->status === 'damaged' ? 'Damaged' : 'Failed')),
                    'payment_method' => $d->payment_method,
                    'total' => $d->amount_to_collect,
                    'earned' => $d->earnings()->value('amount'),
                ])->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'per_page' => $items->perPage(),
            ],
        ]);
    }
}




