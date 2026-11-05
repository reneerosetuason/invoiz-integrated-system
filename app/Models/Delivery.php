<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    public const ACTIVE_STATUSES = [
        'assigned',
        'accepted',
        'going_to_pickup',
        'arrived_at_shop',
        'picked_up',
        'out_for_delivery',
        'arrived_at_customer',
    ];

    public const EXCEPTION_STATUSES = ['lost', 'damaged'];

    public const RETURN_TO_SELLER_STATUSES = ['delivery_failed', 'damaged'];

    /**
     * Dashboard KPI buckets. Each key maps to the exact set of delivery
     * statuses counted for that bucket, so a dashboard count always equals
     * the number of rows the deliveries list shows for the same filter.
     */
    public const STATUS_GROUPS = [
        'pending'    => ['waiting_for_rider'],
        'in_transit' => self::ACTIVE_STATUSES,
        'delivered'  => ['delivered'],
        'failed'     => ['delivery_failed', ...self::EXCEPTION_STATUSES],
    ];

    /** Resolve a status-group key to its status list (null when not a group). */
    public static function statusGroup(string $key): ?array
    {
        return self::STATUS_GROUPS[$key] ?? null;
    }

    protected $fillable = [
        'tracking_number',
        'order_id',
        'seller_id',
        'rider_id',
        'center_id',
        'destination_center_id',
        'service_area_id',
        'sender_name',
        'sender_phone',
        'sender_address',
        'sender_lat',
        'sender_lng',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_lat',
        'recipient_lng',
        'status',
        'parcel_status',
        'weight',
        'notes',
        'package_type',
        'package_description',
        'priority',
        'payment_method',
        'amount_to_collect',
        'cod_amount_received',
        'cod_received_at',
        'delivery_fee',
        'pickup_pin',
        'estimated_delivery_at',
        'assigned_at',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'failed_at',
        'failure_reason',
        'reattempt_requested_at',
        'returned_at',
        'return_reason',
        'cancellation_reason',
        'created_by',
        'archived_at',
        'archived_by',
        'archive_note',
        'delivery_notes',
        'received_at',
        'scanned_at',
        'sorted_at',
        'dispatched_at',
        'sorting_center_handoff_at',
        'sorting_center_handoff_rider_id',
        'sorting_center_pickup_at',
        'sorting_center_pickup_rider_id',
        'origin_center_id',
        'inter_hub_rider_id',
        'inter_hub_dispatched_at',
    ];

    protected $hidden = [
        'delivery_otp',
        'delivery_otp_expires_at',
        'delivery_otp_attempts',
        'delivery_otp_consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'delivery_otp' => 'encrypted',
            'delivery_otp_expires_at' => 'datetime',
            'delivery_otp_attempts' => 'integer',
            'delivery_otp_consumed_at' => 'datetime',
            'amount_to_collect' => 'decimal:2',
            'cod_amount_received' => 'decimal:2',
            'cod_received_at' => 'datetime',
            'delivery_fee' => 'decimal:2',
            'sender_lat' => 'decimal:7',
            'sender_lng' => 'decimal:7',
            'recipient_lat' => 'decimal:7',
            'recipient_lng' => 'decimal:7',
            'status' => 'string',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'inter_hub_dispatched_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'reattempt_requested_at' => 'datetime',
            'returned_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'archived_at' => 'datetime',
            'received_at' => 'datetime',
            'scanned_at' => 'datetime',
            'sorted_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'sorting_center_handoff_at' => 'datetime',
            'sorting_center_pickup_at' => 'datetime',
        ];
    }

    /** @var array{stage: string, center_id: ?int, service_area_id: ?int}|null */
    private ?array $riderAssignmentScopeCache = null;

    protected static function booted(): void
    {
        static::saved(function (Delivery $delivery): void {
            // The stage scope depends on attributes that change as the parcel
            // moves between hubs, so every persisted change invalidates it.
            $delivery->flushRiderAssignmentScope();
        });
        static::creating(function (Delivery $delivery): void {
            if (empty($delivery->tracking_number)) {
                do {
                    $candidate = 'TRK-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                } while (static::where('tracking_number', $candidate)->exists());

                $delivery->tracking_number = $candidate;
            }
        });

        static::saving(function (Delivery $delivery): void {
            if ($delivery->order_id && $delivery->isDirty('status') && ! in_array($delivery->status, ['waiting_for_rider', 'cancelled', 'failed'], true)) {
                if ($order = $delivery->order()->first()) {
                    \App\Services\GcashEscrowService::assertFunded($order);
                }
            }
            // The pickup request is operationally complete as soon as the
            // parcel has been picked up. Checking the timestamp/status (not
            // only the transition to "picked_up") also repairs rows saved
            // directly into a later status, which previously left a stale
            // "pending" request driving the sidebar badge forever.
            $reachedPickup = $delivery->picked_up_at !== null
                || in_array($delivery->status, ['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered'], true);

            // Cancellation closes only an unreviewed pickup: there is no
            // parcel left to review. Approved, rejected and completed
            // decisions are preserved, including after pickup.
            $closedByCancellation = $delivery->status === 'cancelled';

            if (! $reachedPickup && ! $closedByCancellation) {
                return;
            }

            $pickupRequest = $delivery->pickupRequest()->first();

            // Reaching pickup may complete pending or approved requests, but
            // must never overwrite a reviewed rejection or an existing
            // completion. Cancellation only closes a pending request.
            $completableStatuses = $closedByCancellation ? ['pending'] : ['pending', 'approved'];

            if ($pickupRequest && in_array($pickupRequest->status, $completableStatuses, true)) {
                $pickupRequest->update([
                    'status' => 'completed',
                    'reviewed_at' => now(),
                ]);
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(DeliveryStatusLog::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class)->latestOfMany();
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(DeliveryProof::class);
    }

    public function failure(): HasOne
    {
        return $this->hasOne(DeliveryFailure::class)->latestOfMany();
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function logisticsCenter()
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function destinationCenter()
    {
        return $this->belongsTo(LogisticsCenter::class, 'destination_center_id');
    }

    public function serviceArea()
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function remittance(): HasOne
    {
        return $this->hasOne(Remittance::class);
    }

    public function pickupRequest()
    {
        return $this->hasOne(PickupRequest::class);
    }

    public function hubTransferParcels(): HasMany
    {
        return $this->hasMany(HubTransferParcel::class, 'delivery_id');
    }

    /** Parcel is inside an active fleet transfer toward the given hub. */
    public function isIncomingTransferTo(int $centerId): bool
    {
        return (int) $this->center_id !== $centerId
            && $this->hubTransferParcels()
                ->where('loading_status', '!=', 'removed')
                ->whereHas('transfer', fn ($query) => $query
                    ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                    ->where('destination_center_id', $centerId))
                ->exists();
    }

    /** Latest manifest entry while an active fleet transfer still holds the parcel. */
    public function incomingTransferParcel(): HasOne
    {
        return $this->hasOne(HubTransferParcel::class, 'delivery_id')
            ->where('loading_status', '!=', 'removed')
            ->whereHas('transfer', fn ($query) => $query->whereIn('status', HubTransfer::ACTIVE_STATUSES))
            ->orderByDesc('id');
    }

    /** Center's own parcels plus parcels the fleet is bringing to it. */
    public function scopeAtOrIncomingToHub($query, int $centerId)
    {
        return $query->where(function ($inner) use ($centerId): void {
            $inner->where('deliveries.center_id', $centerId)
                ->orWhere(function ($incoming) use ($centerId): void {
                    $incoming->whereHas('hubTransferParcels', fn ($parcel) => $parcel
                        ->where('loading_status', '!=', 'removed')
                        ->whereHas('transfer', fn ($transfer) => $transfer
                            ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                            ->where('destination_center_id', $centerId)));
                });
        });
    }

    /**
     * Incoming parcels for the receiving screens.
     *
     * Two sources feed a hub's incoming queue: pickups the seller has handed
     * to a delivery rider (still pending_arrival) and parcels an active fleet
     * transfer is bringing to the hub. Both are hub-scoped when a hub is
     * supplied so staff never see another hub's expected parcels.
     */
    public function scopeIncomingViaHub($query, ?int $centerId = null)
    {
        return $query->where(function ($outer) use ($centerId): void {
            $outer->where(function ($expected) use ($centerId): void {
                $expected->where('deliveries.parcel_status', 'pending_arrival')
                    // A seller-ready order is not an inbound parcel. Until the
                    // pickup rider records the hub handoff it stays with the
                    // seller and must not appear in a hub's incoming queue.
                    ->where(function ($custody): void {
                        $custody->whereNull('deliveries.order_id')
                            ->orWhere(function ($handedOff): void {
                                $handedOff->whereNotNull('deliveries.picked_up_at')
                                    ->whereNotNull('deliveries.sorting_center_handoff_at');
                            });
                    })
                    ->when($centerId !== null, fn ($hub) => $hub->where('deliveries.center_id', $centerId));
            })->orWhere(function ($incoming) use ($centerId): void {
                $incoming->whereHas('hubTransferParcels', fn ($parcel) => $parcel
                    ->where('loading_status', '!=', 'removed')
                    ->whereHas('transfer', fn ($transfer) => $transfer
                        ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
                        ->when($centerId !== null, fn ($dest) => $dest->where('destination_center_id', $centerId))));
            });
        });
    }

    public function sortingCenterHandoffRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'sorting_center_handoff_rider_id');
    }

    public function sortingCenterPickupRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'sorting_center_pickup_rider_id');
    }

    public function originCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'origin_center_id');
    }

    public function interHubRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'inter_hub_rider_id');
    }

    public function escrowRemittances(): HasMany
    {
        return $this->hasMany(EscrowRemittance::class);
    }

    public function isIncomingTransfer(?int $centerId = null): bool
    {
        return $this->status === 'waiting_for_rider' && $this->parcel_status === 'dispatched' && $this->rider_id === null
            && $this->destination_center_id && (int) $this->destination_center_id !== (int) $this->center_id
            && ($centerId === null || (int) $this->destination_center_id === $centerId);
    }

    public function isInterHubRoute(): bool
    {
        $origin = $this->routeOriginId();
        return $origin !== null && $this->destination_center_id && (int) $this->destination_center_id !== $origin;
    }

    public function routeOriginId(): ?int
    {
        $id = $this->origin_center_id ?: $this->center_id;
        return $id ? (int) $id : null;
    }

    public function scopeAtOrIncomingTo($query, int $centerId)
    {
        return $query->where(fn ($q) => $q->where('deliveries.center_id', $centerId)->orWhere(fn ($incoming) => $incoming
            ->where('deliveries.destination_center_id', $centerId)->whereColumn('deliveries.destination_center_id', '!=', 'deliveries.center_id')
            ->where('deliveries.status', 'waiting_for_rider')->where('deliveries.parcel_status', 'dispatched')->whereNull('deliveries.rider_id')));
    }

    public function scopeIncoming($query, ?int $centerId = null)
    {
        return $query->where(fn ($q) => $q->where('deliveries.parcel_status', 'pending_arrival')->orWhere(fn ($transfer) => $transfer
            ->where('deliveries.status', 'waiting_for_rider')->where('deliveries.parcel_status', 'dispatched')->whereNull('deliveries.rider_id')->whereColumn('deliveries.destination_center_id', '!=', 'deliveries.center_id')
            ->when($centerId !== null, fn ($dest) => $dest->where('deliveries.destination_center_id', $centerId))));
    }

    public function suggestedDestination(?\Illuminate\Support\Collection $areas = null): array
    {
        $address = mb_strtolower((string) $this->recipient_address);
        if ($address === '') {
            return ['center_id' => null, 'service_area_id' => null];
        }
        $areas ??= ServiceArea::where('is_active', true)->get();
        $match = self::matchServiceArea($areas, $address);

        return [
            'center_id' => $match?->logistics_center_id ? (int) $match->logistics_center_id : null,
            'service_area_id' => $match ? (int) $match->id : null,
        ];
    }

    /**
     * Workflow stage that decides which hub and service area a Delivery Rider
     * is evaluated against.
     *
     * - pickup: the parcel is still with the seller, so the first-mile rider
     *   serves the seller's pickup hub and area.
     * - hub_processing: the parcel is in hub custody (awaiting receipt, scan,
     *   sort, or its next fleet leg). No rider may take custody.
     * - last_mile: the parcel reached its final hub and was processed, so the
     *   rider serves the buyer's destination hub and area.
     *
     * Custody evidence (pickup handoff / hub receipt), the parcel pipeline and
     * the recipient address are used together; no single timestamp decides it.
     */
    public function riderAssignmentStage(): string
    {
        // Standalone logistics shipments have no seller-pickup leg.
        if ($this->order_id === null) {
            return 'last_mile';
        }

        if ($this->sorting_center_handoff_at === null && $this->received_at === null) {
            return 'pickup';
        }

        $finalCenterId = $this->finalDestinationScope()['center_id'];
        if ($finalCenterId !== null
            && $this->center_id !== null
            && (int) $this->center_id !== (int) $finalCenterId) {
            return 'hub_processing';
        }

        // At the final hub the parcel still has to be scanned and sorted
        // before a last-mile rider may take custody.
        if ($this->received_at !== null
            && ! in_array($this->parcel_status, ['sorted', 'dispatched'], true)) {
            return 'hub_processing';
        }

        return 'last_mile';
    }

    /**
     * The reviewed destination is authoritative across transfer legs. Address
     * suggestions are a fallback, never a way to reroute an already routed
     * parcel when two hubs have identically named service areas.
     */
    public function finalDestinationScope(): array
    {
        $centerId = $this->destination_center_id !== null ? (int) $this->destination_center_id : null;
        if ($centerId !== null) {
            $areas = ServiceArea::where('is_active', true)->where('logistics_center_id', $centerId)->get();
            $area = $this->service_area_id !== null ? $areas->firstWhere('id', (int) $this->service_area_id) : null;
            $area ??= self::matchServiceArea($areas, mb_strtolower((string) $this->recipient_address));

            return ['center_id' => $centerId, 'service_area_id' => $area ? (int) $area->id : null];
        }
        $suggested = $this->suggestedDestination();

        return [
            'center_id' => $suggested['center_id']
                ?: ($this->destination_center_id !== null ? (int) $this->destination_center_id : null)
                ?: ($this->center_id !== null ? (int) $this->center_id : null),
            'service_area_id' => $suggested['service_area_id']
                ?: ($this->service_area_id !== null ? (int) $this->service_area_id : null),
        ];
    }

    /** Hub the pickup rider collects for: the reviewed routing or origin hub. */
    public function pickupHubId(): ?int
    {
        $requested = $this->pickupRequest()->value('center_id');
        if ($requested !== null && (int) $requested > 0) {
            return (int) $requested;
        }

        return $this->routeOriginId();
    }

    /**
     * Service area implied by the seller's pickup address inside the pickup
     * hub. Null means the address could not be matched, which keeps legacy
     * rows assignable on the hub rule alone.
     */
    public function pickupServiceAreaId(): ?int
    {
        $address = mb_strtolower((string) $this->sender_address);
        if ($address === '') {
            return null;
        }

        $hubId = $this->pickupHubId();
        $areas = ServiceArea::query()
            ->where('is_active', true)
            ->when($hubId !== null, fn ($query) => $query->where('logistics_center_id', $hubId))
            ->get();

        $match = self::matchServiceArea($areas, $address);

        return $match ? (int) $match->id : null;
    }

    /**
     * Hub/area a rider must belong to for the delivery's current stage,
     * resolved once per model instance because the eligibility list compares
     * every candidate rider against the same scope.
     *
     * @return array{stage: string, center_id: ?int, service_area_id: ?int}
     */
    public function riderAssignmentScope(): array
    {
        if ($this->riderAssignmentScopeCache !== null) {
            return $this->riderAssignmentScopeCache;
        }

        $stage = $this->riderAssignmentStage();

        if ($stage === 'pickup') {
            return $this->riderAssignmentScopeCache = [
                'stage' => 'pickup',
                'center_id' => $this->pickupHubId(),
                'service_area_id' => $this->pickupServiceAreaId(),
            ];
        }

        $destination = $this->finalDestinationScope();

        return $this->riderAssignmentScopeCache = [
            'stage' => $stage,
            'center_id' => $destination['center_id'],
            'service_area_id' => $destination['service_area_id'],
        ];
    }

    public function refresh()
    {
        $this->flushRiderAssignmentScope();

        return parent::refresh();
    }

    private function flushRiderAssignmentScope(): void
    {
        $this->riderAssignmentScopeCache = null;
    }

    private static function matchServiceArea(?\Illuminate\Support\Collection $areas, string $address): ?ServiceArea
    {
        return $areas?->sortByDesc(fn ($area) => mb_strlen((string) $area->name))
            ->first(fn ($area) => $area->name && str_contains($address, mb_strtolower((string) $area->name)));
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }
}
