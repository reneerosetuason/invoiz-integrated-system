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

        $pickupRequests = PickupRequest::query()
            ->with(['delivery', 'center'])
            ->when($user->isStaff(), function ($query) use ($user) {
                $query->whereNotNull('center_id')->where('center_id', $user->center_id);
            })
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
            ->paginate(15)
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
            'pickupRequest' => $pickupRequest->load(['delivery', 'delivery.items', 'center', 'reviewer']),
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
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isStaff()) {
            return false;
        }

        return $pickupRequest->center_id && $pickupRequest->center_id == $user->center_id;
    }

    private function authorizeView(User $user, PickupRequest $pickupRequest): void
    {
        if (! $this->canReview($user, $pickupRequest)) {
            abort(403, 'You can only review pickup requests at your assigned logistics center.');
        }
    }
}



