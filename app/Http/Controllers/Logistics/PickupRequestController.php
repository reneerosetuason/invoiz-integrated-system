<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PickupRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $this->isGlobalAdmin($user);

        $status = $request->input('status');

        if ($status === 'routing' && ! $isAdmin) {
            abort(403, 'Only administrators can route pickup requests.');
        }

        $pickupRequests = PickupRequest::query()
            ->with(['delivery', 'center'])
            ->visibleTo($user)
            ->when($status === 'routing', function ($query) use ($user) {
                // Dedicated admin queue: actionable pending requests that
                // still have no handling center.
                $query->needsRouting($user);
            })
            ->when($status === 'pending', function ($query) use ($user) {
                // The pending tab uses the same actionable query as the
                // sidebar badge, so stale rows never disagree with the count.
                $query->pendingReview($user);
            })
            ->when($status && ! in_array($status, ['routing', 'pending'], true), function ($query) use ($status) {
                $query->where('status', $status);
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
            'isAdmin' => $isAdmin,
            'needsRoutingCount' => $isAdmin
                ? PickupRequest::query()->needsRouting($user)->count()
                : 0,
            'activeStatus' => $status,
        ]);
    }

    public function show(Request $request, PickupRequest $pickupRequest): View
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        return view('logistics.pickup-requests.show', [
            'pickupRequest' => $pickupRequest->load(['delivery', 'delivery.items', 'center', 'reviewer']),
            'canReview' => $this->canReview($user, $pickupRequest) && $pickupRequest->center_id !== null,
            'canRoute' => $this->canRoute($user, $pickupRequest),
            'centers' => $this->canRoute($user, $pickupRequest)
                ? LogisticsCenter::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function route(Request $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $user = $request->user();

        if (! $this->isGlobalAdmin($user)) {
            abort(403, 'Only administrators can route pickup requests.');
        }

        $validated = $request->validate([
            'center_id' => [
                'required',
                'integer',
                Rule::exists('logistics_centers', 'id')->where('is_active', true),
            ],
        ]);

        $result = DB::transaction(function () use ($pickupRequest, $validated): string {
            $lockedPickup = PickupRequest::query()->whereKey($pickupRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedPickup->status !== 'pending') {
                return 'reviewed';
            }

            if ($lockedPickup->center_id !== null) {
                return 'already_routed';
            }

            $delivery = Delivery::query()->whereKey($lockedPickup->delivery_id)->lockForUpdate()->firstOrFail();

            if ($delivery->center_id !== null) {
                return 'delivery_has_center';
            }

            if ($delivery->picked_up_at !== null
                || $delivery->archived_at !== null
                || in_array($delivery->status, ['cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'delivered'], true)) {
                return 'not_actionable';
            }

            $lockedPickup->update(['center_id' => $validated['center_id']]);
            $delivery->update(['center_id' => $validated['center_id']]);

            return 'routed';
        });

        if ($result !== 'routed') {
            $message = match ($result) {
                'reviewed' => 'This pickup request has already been reviewed.',
                'already_routed' => 'This pickup request already has a logistics center.',
                'delivery_has_center' => 'The delivery already has a handling center and cannot be re-routed here.',
                default => 'This pickup request is no longer actionable.',
            };

            return back()->with('error', $message);
        }

        $pickupRequest->refresh()->load('delivery');

        try {
            Notification::create([
                'type' => 'new_delivery_request',
                'title' => 'Pickup Request Routed',
                'message' => "Pickup for {$pickupRequest->delivery->tracking_number} was routed to your center and needs review.",
                'icon' => 'pickup',
                'priority' => 'high',
                'center_id' => $pickupRequest->center_id,
                'audience' => 'center',
                'link' => route('pickup-requests.show', $pickupRequest),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Pickup request routed. The center can now review it.');
    }

    public function approve(Request $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        $result = DB::transaction(function () use ($pickupRequest, $user): string {
            $lockedPickup = PickupRequest::query()->whereKey($pickupRequest->id)->lockForUpdate()->firstOrFail();

            if (! $this->canReview($user, $lockedPickup)) {
                return 'forbidden';
            }

            if ($lockedPickup->status !== 'pending') {
                return 'reviewed';
            }

            if ($lockedPickup->center_id === null) {
                return 'unrouted';
            }

            $delivery = Delivery::query()->whereKey($lockedPickup->delivery_id)->lockForUpdate()->first();

            if (! $delivery || ! $this->isActionableDelivery($delivery)) {
                return 'not_actionable';
            }

            $lockedPickup->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return 'approved';
        });

        if ($result === 'forbidden') {
            abort(403, 'You can only review pickup requests at your assigned logistics center.');
        }

        if ($result === 'reviewed') {
            return back()->with('error', 'This pickup request has already been reviewed.');
        }

        if ($result === 'unrouted') {
            return back()->with('error', 'Route this pickup request to a logistics center before approving it.');
        }

        if ($result === 'not_actionable') {
            return back()->with('error', 'This pickup request is no longer actionable because the parcel has already moved past pickup.');
        }

        return back()->with('success', 'Pickup request approved. Assigning a rider is a separate step on the delivery page.');
    }

    public function reject(Request $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $pickupRequest);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $result = DB::transaction(function () use ($pickupRequest, $user, $validated): string {
            $lockedPickup = PickupRequest::query()->whereKey($pickupRequest->id)->lockForUpdate()->firstOrFail();

            if (! $this->canReview($user, $lockedPickup)) {
                return 'forbidden';
            }

            if ($lockedPickup->status !== 'pending') {
                return 'reviewed';
            }

            if ($lockedPickup->center_id === null) {
                return 'unrouted';
            }

            $delivery = Delivery::query()->whereKey($lockedPickup->delivery_id)->lockForUpdate()->first();

            if (! $delivery || ! $this->isActionableDelivery($delivery)) {
                return 'not_actionable';
            }

            $lockedPickup->update([
                'status' => 'rejected',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return 'rejected';
        });

        if ($result === 'forbidden') {
            abort(403, 'You can only review pickup requests at your assigned logistics center.');
        }

        if ($result === 'reviewed') {
            return back()->with('error', 'This pickup request has already been reviewed.');
        }

        if ($result === 'unrouted') {
            return back()->with('error', 'Route this pickup request to a logistics center before rejecting it.');
        }

        if ($result === 'not_actionable') {
            return back()->with('error', 'This pickup request is no longer actionable because the parcel has already moved past pickup.');
        }

        $pickupRequest->refresh()->load('delivery');

        Notification::create([
            'type' => 'pickup_failed',
            'title' => 'Pickup Failed',
            'message' => "Pickup request #{$pickupRequest->id} was rejected. Reason: {$validated['rejection_reason']}",
            'icon' => '⚠️',
            'priority' => 'high',
            'center_id' => $pickupRequest->center_id,
            'audience' => 'center',
            'link' => route('pickup-requests.show', $pickupRequest),
        ]);

        return back()->with('success', 'Pickup request rejected. The delivery was not modified.');
    }

    public function canReview(User $user, PickupRequest $pickupRequest): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        if (! $user->isLogisticsStaff()) {
            return false;
        }

        return $pickupRequest->center_id && $pickupRequest->center_id == $user->center_id;
    }

    private function canRoute(User $user, PickupRequest $pickupRequest): bool
    {
        if (! $this->isGlobalAdmin($user) || $pickupRequest->status !== 'pending') {
            return false;
        }

        $delivery = $pickupRequest->delivery;

        return $pickupRequest->center_id === null
            && $delivery !== null
            && $delivery->center_id === null
            && $delivery->picked_up_at === null
            && $delivery->archived_at === null
            && ! in_array($delivery->status, ['cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'delivered'], true);
    }

    /**
     * A pending pickup request is only reviewable while its parcel has not
     * moved past the pickup stage. This mirrors the actionable predicate used
     * by the pickup queue and sidebar badge so a direct POST cannot revive a
     * stale row.
     */
    private function isActionableDelivery(Delivery $delivery): bool
    {
        return $delivery->picked_up_at === null
            && $delivery->archived_at === null
            && ! in_array($delivery->status, [
                'picked_up',
                'out_for_delivery',
                'arrived_at_customer',
                'delivered',
                'cancelled',
                'delivery_failed',
                'lost',
                'damaged',
                'returned_to_seller',
            ], true);
    }

    /**
     * Global logistics oversight is role-based: the admin and logistics_admin
     * roles see and review every center. A legacy is_admin flag alone must not
     * widen pickup scope, and a center assignment on an oversight account must
     * not narrow it.
     */
    private function isGlobalAdmin(User $user): bool
    {
        return $user->isLogisticsAdmin();
    }

    private function authorizeView(User $user, PickupRequest $pickupRequest): void
    {
        if (! $this->canReview($user, $pickupRequest)) {
            abort(403, 'You can only review pickup requests at your assigned logistics center.');
        }
    }
}



