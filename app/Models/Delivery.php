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

    protected $fillable = [
        'order_id',
        'rider_id',
        'status',
        'delivery_fee',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
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
        return $query->whereNull('archived_at');
    }
}