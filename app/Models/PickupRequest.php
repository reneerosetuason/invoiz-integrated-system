<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'center_id',
        'status',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Create the single pickup request for a delivery, if it does not exist
     * yet, and notify staff once. Used by every delivery-creation path so an
     * order placed through the marketplace or seller center cannot silently
     * skip the pickup review queue.
     */
    public static function openForDelivery(Delivery $delivery, bool $notify = true): self
    {
        $pickup = static::firstOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'center_id' => $delivery->center_id,
                'status' => 'pending',
                'requested_at' => now(),
            ]
        );

        if ($notify && $pickup->wasRecentlyCreated) {
            try {
                Notification::create([
                    'type' => 'new_delivery_request',
                    'title' => $delivery->center_id
                        ? 'Pickup Request Needs Review'
                        : 'Pickup Request Needs Routing',
                    'message' => $delivery->center_id
                        ? "Pickup for {$delivery->tracking_number} is waiting for center review."
                        : "Pickup for {$delivery->tracking_number} has no logistics center yet and needs routing.",
                    'icon' => 'pickup',
                    'priority' => 'high',
                    'center_id' => $delivery->center_id,
                    'audience' => $delivery->center_id ? 'center' : 'admin',
                    'link' => route('pickup-requests.show', $pickup),
                ]);
            } catch (\Throwable $e) {
                // Operational records must not fail because a notification
                // could not be written; the pickup queue remains the source
                // of truth and the badge still surfaces it.
                report($e);
            }
        }

        return $pickup;
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Requests the given account is allowed to see in the Logistics UI.
     *
     * Global oversight is role-based: the admin and logistics_admin roles see
     * every center. A legacy is_admin flag alone does not widen pickup scope.
     * Center-scoped staff see only their assigned center. Accounts without a
     * logistics scope see nothing. This is the single source of truth shared
     * by the index and the sidebar badge so the two can never drift apart.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isLogisticsAdmin()) {
            return $query;
        }

        if ($user->isLogisticsStaff() && $user->center_id) {
            return $query->whereNotNull('center_id')->where('center_id', $user->center_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Pickup requests that still require a review decision.
     *
     * A request whose parcel was already picked up, is in transit, archived,
     * cancelled, failed or delivered is not actionable: the operational
     * workflow has moved past it even if a stale row is still marked
     * "pending". Such rows are excluded from the badge and the pending filter
     * without rewriting history.
     */
    public function scopePendingReview(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user)
            ->where('status', 'pending')
            ->whereHas('delivery', function (Builder $delivery): void {
                $delivery->whereNull('picked_up_at')
                    ->whereNull('archived_at')
                    ->whereNotIn('status', ['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'cancelled', 'delivery_failed', 'lost', 'damaged', 'returned_to_seller']);
            });
    }

    /**
     * Actionable pending requests that still have no handling center.
     *
     * Only global administrators can act on these because routing chooses a
     * center for the whole parcel lifecycle. The delivery must also be
     * centerless: a stale pickup row on an already-received parcel is a data
     * mismatch, not a routing task.
     */
    public function scopeNeedsRouting(Builder $query, User $user): Builder
    {
        return $query->pendingReview($user)
            ->whereNull('center_id')
            ->whereHas('delivery', fn (Builder $delivery) => $delivery->whereNull('center_id'));
    }
}
