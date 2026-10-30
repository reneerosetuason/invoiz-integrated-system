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

    public function isIncomingTransfer(?int $centerId = null): bool
    {
        return $this->status === 'waiting_for_rider' && $this->parcel_status === 'dispatched' && $this->rider_id === null
            && $this->destination_center_id && (int) $this->destination_center_id !== (int) $this->center_id
            && ($centerId === null || (int) $this->destination_center_id === $centerId);
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

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery): void {
            if (empty($delivery->tracking_number)) {
                do {
                    $candidate = 'TRK-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                } while (static::where('tracking_number', $candidate)->exists());

                $delivery->tracking_number = $candidate;
            }
        });

        static::saving(function (Delivery $delivery): void {
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

    public function sortingCenterHandoffRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'sorting_center_handoff_rider_id');
    }

    public function sortingCenterPickupRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'sorting_center_pickup_rider_id');
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('deliveries.archived_at');
    }
}
