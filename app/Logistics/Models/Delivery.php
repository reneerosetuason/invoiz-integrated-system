<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    use HasFactory;

    public const ACTIVE_STATUSES = [
        'assigned',
        'accepted',
        'going_to_pickup',
        'arrived_at_shop',
        'picked_up',
        'out_for_delivery',
        'arrived_at_customer',
    ];

    protected $fillable = [
        'tracking_number',
        'order_id',
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

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'amount_to_collect' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'sender_lat' => 'decimal:7',
            'sender_lng' => 'decimal:7',
            'recipient_lat' => 'decimal:7',
            'recipient_lng' => 'decimal:7',
            'estimated_delivery_at' => 'datetime',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'returned_at' => 'datetime',
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
        // Auto-generate a unique tracking number (TRK-YYYYMMDD-XXXX).
        // Independent of rider assignment so a delivery can always be created.
        static::creating(function (Delivery $delivery) {
            if (empty($delivery->tracking_number)) {
                do {
                    $candidate = 'TRK-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
                } while (self::where('tracking_number', $candidate)->exists());

                $delivery->tracking_number = $candidate;
            }
        });

        // Auto-complete the linked pickup request once the parcel is picked up.
        // Runs on every save so both the web status update and the rider API
        // pickup flow complete the request without touching either controller.
        static::saving(function (Delivery $delivery) {
            if ($delivery->isDirty('status') && $delivery->status === 'picked_up') {
                $pickup = $delivery->pickupRequest()->first();
                if ($pickup && $pickup->status !== 'completed') {
                    $pickup->update([
                        'status' => 'completed',
                        'reviewed_at' => now(),
                    ]);
                }
            }
        });
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

    public function pickupRequest()
    {
        return $this->hasOne(PickupRequest::class);
    }

    /**
     * Read-only link to the originating buyer order (shared `orders` table).
     *
     * Used only to present seller/order/customer information on the
     * shipping waybill. Adds no writes and does not modify the
     * Buyer/Seller/Order systems.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
        return $query->whereNull('archived_at');
    }
}
