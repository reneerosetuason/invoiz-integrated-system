<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Live operational alerts for the Logistics Center dashboard.
 *
 * Alerts are computed from current rows (no queue, cron, or alert table), so
 * they are idempotent, clear themselves when the condition is resolved, and
 * do not require a worker to be running. Thresholds live in
 * config/logistics.php under `alerts` and are operational defaults, not
 * contractual SLAs.
 */
class LogisticsAlerts
{
    public static function forUser(User $user): array
    {
        $isCenterScoped = $user->isLogisticsStaff()
            && !$user->isAdmin()
            && !$user->isLogisticsAdmin();

        // Null means global; 0 means a scoped staff account with no center,
        // which must produce no alerts rather than every center's alerts.
        $centerId = $isCenterScoped ? (int) ($user->center_id ?? 0) : null;

        $base = Delivery::query()->notArchived();
        if ($centerId !== null) {
            if ($centerId === 0) {
                $base->whereRaw('1 = 0');
            } else {
                $base->where('center_id', $centerId);
            }
        }

        $alerts = [];

        $unassignedAfter = now()->subMinutes((int) config('logistics.alerts.unassigned_after_minutes', 120));
        $unassigned = (clone $base)
            ->where('status', 'waiting_for_rider')
            ->where('created_at', '<=', $unassignedAfter)
            ->whereNotIn('status', ['cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'delivered']);

        self::push($alerts, 'unassigned', 'warning', 'Unassigned deliveries',
            'Deliveries have waited too long for a rider.',
            'Assign a rider or cancel the delivery.',
            route('deliveries.index', ['status' => 'waiting_for_rider']),
            $unassigned->count(),
            (clone $unassigned)->min('created_at'));

        $incomingAfter = now()->subMinutes((int) config('logistics.alerts.incoming_after_minutes', 120));
        $incoming = (clone $base)
            ->where('parcel_status', 'pending_arrival')
            ->where('created_at', '<=', $incomingAfter)
            ->whereNotIn('status', ['cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'delivered']);

        self::push($alerts, 'incoming', 'warning', 'Incoming parcels not received',
            'Parcels are still marked pending arrival at a center.',
            'Receive the parcel at the correct logistics center.',
            route('sorting-center.index', ['tab' => 'incoming']),
            $incoming->count(),
            (clone $incoming)->min('created_at'));

        $pickupAfter = now()->subMinutes((int) config('logistics.alerts.pickup_review_after_minutes', 120));
        $pickups = PickupRequest::query()
            ->pendingReview($user)
            ->where('requested_at', '<=', $pickupAfter);

        self::push($alerts, 'pickups', 'warning', 'Pickup reviews overdue',
            'Pickup requests are waiting for a review decision.',
            'Approve or reject the pickup request.',
            route('pickup-requests.index', ['status' => 'pending']),
            $pickups->count(),
            (clone $pickups)->min('requested_at'));

        $sortingAfter = now()->subMinutes((int) config('logistics.alerts.sorting_after_minutes', 240));
        $sorting = (clone $base)
            ->whereIn('parcel_status', ['received', 'scanned', 'sorted'])
            ->whereNotIn('status', ['cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller', 'delivered'])
            ->where(function (Builder $q) use ($sortingAfter) {
                $q->where('received_at', '<=', $sortingAfter)
                    ->orWhere(function (Builder $inner) use ($sortingAfter) {
                        $inner->whereNull('received_at')->where('created_at', '<=', $sortingAfter);
                    });
            });

        self::push($alerts, 'sorting', 'info', 'Sorting backlog',
            'Parcels have been at a center without being dispatched.',
            'Scan, sort, and dispatch the parcel.',
            route('sorting-center.index', ['tab' => 'received']),
            $sorting->count(),
            (clone $sorting)->min('received_at'));

        $failedAfter = now()->subMinutes((int) config('logistics.alerts.failed_review_after_minutes', 60));
        $failed = (clone $base)
            ->whereIn('status', ['delivery_failed', 'damaged'])
            ->where('updated_at', '<=', $failedAfter)
            ->whereNull('returned_at')
            ->whereNull('reattempt_requested_at');

        self::push($alerts, 'failed', 'danger', 'Failed deliveries need review',
            'Failed or damaged deliveries have not been returned or queued for retry.',
            'Request a re-attempt or process the return.',
            route('deliveries.index', ['status' => 'exceptions']),
            $failed->count(),
            (clone $failed)->max('updated_at'));

        return $alerts;
    }

    private static function push(
        array &$alerts,
        string $key,
        string $severity,
        string $title,
        string $description,
        string $action,
        string $url,
        int $count,
        ?string $oldest,
    ): void {
        if ($count < 1) {
            return;
        }

        $alerts[] = [
            'key' => $key,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'action' => $action,
            'url' => $url,
            'count' => $count,
            'oldest' => $oldest ? Carbon::parse($oldest) : null,
        ];
    }
}
