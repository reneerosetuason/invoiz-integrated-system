<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Notification;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PickupRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = PickupRequest::query()
            ->with(['delivery.logisticsCenter', 'center']);

        if (! $user->isAdmin() && ! $user->isLogisticsAdmin()) {
            if ($user->isLogisticsStaff() && $user->center_id) {
                $centerId = (int) $user->center_id;
                $query->where(function ($query) use ($centerId) {
                    $query->where('pickup_requests.center_id', $centerId)
                        ->orWhere(function ($legacyRequests) use ($centerId) {
                            $legacyRequests->whereNull('pickup_requests.center_id')
                                ->whereHas('delivery', function ($delivery) use ($centerId) {
                                    $delivery->where('center_id', $centerId);
                                });
                        });
                });
            } else {
                // A logistics account without a center must not see requests
                // belonging to every center.
                $query->whereRaw('1 = 0');
            }
        }

        $pickupRequests = $query
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));
                $query->whereHas('delivery', function ($delivery) use ($search) {
                    $delivery->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('sender_name', 'like', "%{$search}%")
                        ->orWhere('sender_phone', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('status')
            ->latest('requested_at')
            ->paginate(10)
            ->withQueryString();

        return view('logistics.pickup-requests.index', [
            'pickupRequests' => $pickupRequests,
        ]);
    }

    public function show(Request $request, PickupRequest $pickupRequest): View
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        return view('logistics.pickup-requests.show', [
            'pickupRequest' => $pickupRequest->load(['delivery', 'delivery.items', 'delivery.logisticsCenter', 'center', 'reviewer']),
            'canReview' => $this->canReview($user, $pickupRequest),
        ]);
    }

    public function approve(Request $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        if ($pickupRequest->status !== 'pending') {
            return back()->with('error', 'This pickup request has already been reviewed.');
        }

        $pickupRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Pickup request approved. Assigning a rider is a separate step on the delivery page.');
    }

    public function reject(Request $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        if ($pickupRequest->status !== 'pending') {
            return back()->with('error', 'This pickup request has already been reviewed.');
        }

        $pickupRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        Notification::create([
            'type' => 'pickup_failed',
            'title' => 'Pickup Failed',
            'message' => "Pickup request #{$pickupRequest->id} was rejected. Reason: {$validated['rejection_reason']}",
            'icon' => '??????',
            'priority' => 'high',
            'link' => route('pickup-requests.show', $pickupRequest),
        ]);

        return back()->with('success', 'Pickup request rejected. The delivery was not modified.');
    }

    public function canReview(User $user, PickupRequest $pickupRequest): bool
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin()) {
            return true;
        }

        if (! $user->isLogisticsStaff() || ! $user->center_id) {
            return false;
        }

        // Older requests were created without their own center_id. In those
        // cases the delivery's center is the source of truth.
        $requestCenterId = $pickupRequest->center_id
            ?? $pickupRequest->delivery?->center_id
            ?? $pickupRequest->delivery()->value('center_id');

        return $requestCenterId !== null
            && (int) $requestCenterId === (int) $user->center_id;
    }

    private function authorizeView(User $user, PickupRequest $pickupRequest): void
    {
        if (! $this->canReview($user, $pickupRequest)) {
            abort(403, 'You can only review pickup requests at your assigned logistics center.');
        }
    }
}



