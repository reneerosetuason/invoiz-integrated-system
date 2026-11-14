<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelLiability extends Model
{
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REVERSED = 'reversed';

    public const CUSTODY_LAST_MILE_RIDER = 'last_mile_rider';
    public const CUSTODY_LINEHAUL = 'linehaul';
    public const CUSTODY_ORIGIN_HUB = 'origin_hub';
    public const CUSTODY_UNDETERMINED = 'undetermined';

    protected $fillable = [
        'order_id',
        'delivery_id',
        'seller_id',
        'logistics_company_id',
        'logistics_center_id',
        'rider_id',
        'custody_classification',
        'merchandise_amount',
        'status',
        'damage_evidence_path',
        'proposed_at',
        'accepted_at',
        'settled_at',
        'settled_by',
        'settlement_reference',
        'disputed_at',
        'dispute_reason',
        'dispute_notes',
        'dispute_evidence_path',
        'resolved_at',
        'resolved_by',
        'resolution_outcome',
        'resolution_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'merchandise_amount' => 'decimal:2',
            'proposed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'settled_at' => 'datetime',
            'disputed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'user_id');
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'logistics_center_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isDisputed(): bool
    {
        return $this->status === self::STATUS_DISPUTED;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function canDispute(): bool
    {
        if (in_array($this->status, [self::STATUS_SETTLED, self::STATUS_DISPUTED, self::STATUS_RESOLVED, self::STATUS_REVERSED], true)) {
            return false;
        }

        $delivery = $this->delivery;
        if (! $delivery) {
            return false;
        }

        $receivedLog = DeliveryStatusLog::where('delivery_id', $delivery->id)
            ->where('status', ReturnToSellerService::RECEIVED)
            ->latest('id')
            ->first();

        if (! $receivedLog && $delivery->status !== 'returned_to_seller') {
            return false;
        }

        $confirmedAt = $receivedLog?->created_at ?: $delivery->returned_at ?: $delivery->updated_at;
        if (! $confirmedAt) {
            return false;
        }

        return now()->diffInDays($confirmedAt) <= 7;
    }
}
