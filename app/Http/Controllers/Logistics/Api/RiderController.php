<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\RiderEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RiderController extends Controller
{
    private function rider(Request $request): Rider
    {
        return $request->user()->rider;
    }

    /**
     * Return the rider profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $rider->loadCount(['deliveries']);

        return response()->json([
            'rider' => $this->riderPayload($rider),
        ]);
    }

    /**
     * Update editable rider profile fields.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'sometimes|string|max:30',
            'vehicle_type' => 'sometimes|string|max:255',
            'license_plate' => 'nullable|string|max:255',
        ]);

        $rider = $this->rider($request);
        $rider->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'rider' => $this->riderPayload($rider->fresh()),
        ]);
    }

    /**
     * Toggle rider online/offline availability.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:online,offline',
        ]);

        $rider = $this->rider($request);

        if ($validated['status'] === 'online') {
            $blocked = $rider->assignmentBlockReason();

            if ($blocked !== null) {
                return response()->json([
                    'message' => 'You cannot go online. '.$blocked.'.',
                    'errors' => ['status' => ['You cannot go online. '.$blocked.'.']],
                ], 422);
            }
        }

        $rider->update(['is_online' => $validated['status'] === 'online']);

        return response()->json([
            'message' => $rider->is_online ? 'You are now online.' : 'You are now offline.',
            'status' => $rider->is_online ? 'online' : 'offline',
            'rider' => $this->riderPayload($rider->fresh()),
        ]);
    }

    /**
     * Rider dashboard summary.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $today = now()->startOfDay();
        $activeStatuses = [
            'accepted', 'going_to_pickup', 'arrived_at_shop',
            'picked_up', 'out_for_delivery', 'arrived_at_customer',
        ];

        $query = Delivery::where('rider_id', $rider->id);

        $stats = [
            'assigned' => (clone $query)->where('status', 'assigned')->count(),
            'to_pick_up' => (clone $query)->whereIn('status', ['accepted', 'going_to_pickup', 'arrived_at_shop'])->count(),
            'in_transit' => (clone $query)->whereIn('status', ['picked_up', 'out_for_delivery', 'arrived_at_customer'])->count(),
            'completed' => (clone $query)->where('status', 'delivered')->count(),
            'failed' => (clone $query)->where('status', 'delivery_failed')->count(),
        ];

        $todayEarnings = RiderEarning::where('rider_id', $rider->id)
            ->where('earned_on', $today->toDateString())
            ->sum('amount');

        $current = (clone $query)
            ->with(['items', 'rider'])
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('updated_at')
            ->first();

        $upcomingPickup = (clone $query)
            ->with(['items'])
            ->whereIn('status', ['assigned', 'accepted', 'going_to_pickup'])
            ->orderBy('created_at')
            ->first();

        $recentCompleted = (clone $query)
            ->with(['items'])
            ->where('status', 'delivered')
            ->orderByDesc('delivered_at')
            ->take(5)
            ->get();

        $unreadNotifications = $rider->notifications()->where('is_read', false)->count();

        return response()->json([
            'stats' => $stats,
            'today_earnings' => (float) $todayEarnings,
            'current_delivery' => $current ? $this->deliveryPayload($current) : null,
            'upcoming_pickup' => $upcomingPickup ? $this->deliveryPayload($upcomingPickup) : null,
            'recent_completed' => $recentCompleted->map(fn ($d) => $this->deliveryPayload($d))->values(),
            'unread_notifications' => $unreadNotifications,
            'rider' => $this->riderPayload($rider->fresh()),
        ]);
    }

    private function riderPayload(Rider $rider): array
    {
        $rider->loadCount('deliveries');

        return [
            'id' => $rider->id,
            'name' => $rider->name,
            'email' => $rider->email,
            'phone' => $rider->phone,
            'vehicle_type' => $rider->vehicle_type,
            'license_plate' => $rider->license_plate,
            'license_number' => $rider->license_number,
            'license_expiry' => $rider->license_expiry?->format('Y-m-d'),
            'license_status' => $rider->licenseStatus(),
            'license_expired' => $rider->licenseExpired(),
            'license_expiring_soon' => $rider->licenseExpiringSoon(),
            'vehicle_registration' => $rider->vehicle_registration,
            'company' => $rider->logisticsCenter?->name,
            'service_area' => $rider->serviceArea?->name,
            'status' => $rider->status,
            'is_online' => $rider->is_online,
            'can_go_online' => $rider->canGoOnline(),
            'online_block_reason' => $rider->assignmentBlockReason(),
            'avatar' => $rider->avatar ? url('storage/' . $rider->avatar) : null,
            'total_deliveries' => $rider->deliveries_count,
            'completed_deliveries' => Delivery::where('rider_id', $rider->id)->where('status', 'delivered')->count(),
            'failed_deliveries' => Delivery::where('rider_id', $rider->id)->where('status', 'delivery_failed')->count(),
            'documents' => $this->documentsPayload($rider),
        ];
    }

    /**
     * The rider's uploaded application documents. Each entry carries a
     * short-lived signed URL so the rider app can open the private file
     * directly (no bearer token is available to the external viewer). The URL
     * grants access to exactly one document and only while it is unexpired.
     */
    private function documentsPayload(Rider $rider): array
    {
        $application = RiderApplication::where('email', $rider->email)->latest('id')->first();

        if (! $application) {
            return [];
        }

        return $application->supportingDocuments
            ->map(fn (RiderApplicationDocument $doc) => [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'label' => $doc->typeLabel(),
                'name' => $doc->original_filename,
                'mime_type' => $doc->contentMime(),
                'is_image' => $doc->isImage(),
                'size' => $doc->humanSize(),
                'url' => URL::temporarySignedRoute(
                    'api.logistics.rider.documents.show',
                    now()->addMinutes(30),
                    ['document' => $doc->id],
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * Serve one of the authenticated rider's own application documents.
     *
     * Access requires a valid (unexpired, untampered) signed URL — generated
     * only inside the rider's own profile response — and the document must
     * belong to an application whose email matches a provisioned rider.
     */
    public function document(RiderApplicationDocument $document): BinaryFileResponse
    {
        $application = $document->application;

        abort_unless(
            $application && Rider::where('email', $application->email)->exists(),
            403,
            'This document is not available.',
        );

        abort_unless($document->fileExists(), 404, 'Document is missing or was deleted.');

        return response()->file($document->absolutePath(), [
            'Content-Type' => $document->contentMime(),
            'Content-Disposition' => 'inline; filename="' . basename($document->original_filename) . '"',
        ]);
    }

    public function deliveryPayload(Delivery $delivery): array
    {
        $items = $delivery->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'variant_label' => $item->variant_label,
            'quantity' => $item->quantity,
            'price' => (float) $item->price,
            'subtotal' => (float) $item->subtotal,
        ])->values();

        $subtotal = $items->sum('subtotal');
        $total = $subtotal + (float) ($delivery->delivery_fee ?? 0);

        return [
            'id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'order_id' => $delivery->order_id,
            'status' => $delivery->status,
            'status_label' => $this->statusLabel($delivery->status),
            'parcel_status' => $delivery->parcel_status,
            'delivery_instructions' => $delivery->notes ?? $delivery->delivery_notes,
            'logistics_center' => $delivery->logisticsCenter ? [
                'id' => $delivery->logisticsCenter->id,
                'name' => $delivery->logisticsCenter->name,
            ] : null,
            'destination_center' => $delivery->destinationCenter ? [
                'id' => $delivery->destinationCenter->id,
                'name' => $delivery->destinationCenter->name,
            ] : null,
            'service_area' => $delivery->serviceArea ? [
                'id' => $delivery->serviceArea->id,
                'name' => $delivery->serviceArea->name,
            ] : null,
            'shop' => [
                'name' => $delivery->sender_name,
                'phone' => $delivery->sender_phone,
                'address' => $delivery->sender_address,
                'latitude' => $delivery->sender_lat !== null ? (float) $delivery->sender_lat : null,
                'longitude' => $delivery->sender_lng !== null ? (float) $delivery->sender_lng : null,
            ],
            'customer' => [
                'name' => $delivery->recipient_name,
                'phone' => $delivery->recipient_phone,
                'address' => $delivery->recipient_address,
                'latitude' => $delivery->recipient_lat !== null ? (float) $delivery->recipient_lat : null,
                'longitude' => $delivery->recipient_lng !== null ? (float) $delivery->recipient_lng : null,
            ],
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => $delivery->delivery_fee !== null ? (float) $delivery->delivery_fee : null,
            'total' => round($total, 2),
            'payment_method' => $delivery->payment_method,
            'amount_to_collect' => $delivery->amount_to_collect !== null ? (float) $delivery->amount_to_collect : null,
            'pickup_pin_required' => ! empty($delivery->pickup_pin),
            'notes' => $delivery->notes ?? $delivery->delivery_notes,
            'weight' => $delivery->weight,
            'assigned_at' => $delivery->assigned_at?->toIso8601String(),
            'accepted_at' => $delivery->accepted_at?->toIso8601String(),
            'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'failure_reason' => $delivery->failure_reason,
            'reattempt_requested_at' => $delivery->reattempt_requested_at?->toIso8601String(),
            'returned_at' => $delivery->returned_at?->toIso8601String(),
            'return_reason' => $delivery->return_reason,
            'sorting_center_handoff_at' => $delivery->sorting_center_handoff_at?->toIso8601String(),
            'sorting_center_handoff_rider_id' => $delivery->sorting_center_handoff_rider_id,
            'sorting_center_pickup_at' => $delivery->sorting_center_pickup_at?->toIso8601String(),
            'sorting_center_pickup_rider_id' => $delivery->sorting_center_pickup_rider_id,
            'received_at' => $delivery->received_at?->toIso8601String(),
            'scanned_at' => $delivery->scanned_at?->toIso8601String(),
            'sorted_at' => $delivery->sorted_at?->toIso8601String(),
            'dispatched_at' => $delivery->dispatched_at?->toIso8601String(),
            'proof' => $delivery->proof ? [
                'type' => $delivery->proof->type,
                'signature_name' => $delivery->proof->signature_name,
                'verified_at' => $delivery->proof->verified_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'waiting_for_rider' => 'Waiting for Rider',
            'assigned' => 'New Assignment',
            'accepted' => 'Accepted',
            'going_to_pickup' => 'Going to Pickup',
            'arrived_at_shop' => 'Arrived at Shop',
            'picked_up' => 'Picked Up',
            'out_for_delivery' => 'Out for Delivery',
            'arrived_at_customer' => 'Arrived at Customer',
            'delivered' => 'Delivered',
            'delivery_failed' => 'Delivery Failed',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}




