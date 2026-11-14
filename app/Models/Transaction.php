<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id', 'tracking_number', 'rider_id', 'logistics_center_id',
        'service_area_id', 'origin_center_id', 'destination_center_id', 'pickup_rider_id',
        'amount', 'rider_fee', 'logistics_fee', 'admin_commission',
        'origin_hub_fee', 'pickup_rider_fee', 'inter_hub_driver_fee',
        'destination_hub_fee', 'last_mile_rider_fee', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rider_fee' => 'decimal:2',
            'logistics_fee' => 'decimal:2',
            'admin_commission' => 'decimal:2',
            'origin_hub_fee' => 'decimal:2',
            'pickup_rider_fee' => 'decimal:2',
            'inter_hub_driver_fee' => 'decimal:2',
            'destination_hub_fee' => 'decimal:2',
            'last_mile_rider_fee' => 'decimal:2',
        ];
    }

    public const PICKUP_FEE_PER_PARCEL = 30.00;
    public const HUB_TO_DOOR_FEE_PER_PARCEL = 50.00;
    public const RIDER_FEE_PER_PARCEL = 50.00;
    public const ADMIN_COMMISSION_RATE = 0.10;

    public const ASSIGNMENT_PICKUP = 'pickup';
    public const ASSIGNMENT_HUB_TO_DOOR = 'hub_to_door';

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function pickupRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'pickup_rider_id');
    }

    public function logisticsCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class);
    }

    public function originCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'origin_center_id');
    }

    public function destinationCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'destination_center_id');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public static function calculatePickupFee(int $assignedParcels): float
    {
        return round(max(0, $assignedParcels) * self::PICKUP_FEE_PER_PARCEL, 2);
    }

    public static function calculateHubToDoorFee(int $assignedParcels): float
    {
        return round(max(0, $assignedParcels) * self::HUB_TO_DOOR_FEE_PER_PARCEL, 2);
    }

    public static function calculateManagementCommission(float $amount): float
    {
        return round(max(0.0, $amount) * self::ADMIN_COMMISSION_RATE, 2);
    }

    /**
     * Resolve the actual Pickup rider assigned to this parcel, if any.
     * Never assumes a last-mile rider is also a pickup rider.
     */
    public function pickupRiderId(): ?int
    {
        if ($this->pickup_rider_id !== null && (int) $this->pickup_rider_id > 0) {
            return (int) $this->pickup_rider_id;
        }

        $delivery = $this->relationLoaded('delivery') ? $this->delivery : $this->delivery()->first();
        if ($delivery) {
            if ($delivery->sorting_center_handoff_rider_id !== null && (int) $delivery->sorting_center_handoff_rider_id > 0) {
                return (int) $delivery->sorting_center_handoff_rider_id;
            }

            if (
                $delivery->rider_id !== null
                && (int) $delivery->rider_id > 0
                && $delivery->status !== 'delivered'
                && $delivery->dispatched_at === null
                && $delivery->riderAssignmentStage() === 'pickup'
            ) {
                return (int) $delivery->rider_id;
            }
        }

        if (
            $this->rider_id !== null
            && (int) $this->rider_id > 0
            && abs((float) $this->rider_fee - self::PICKUP_FEE_PER_PARCEL) < 0.01
            && (float) ($this->last_mile_rider_fee ?? 0) <= 0
        ) {
            return (int) $this->rider_id;
        }

        return null;
    }

    /**
     * Resolve the actual Hub-to-Door (last-mile) rider assigned to this parcel, if any.
     */
    public function hubToDoorRiderId(): ?int
    {
        if ($this->rider_id === null || (int) $this->rider_id <= 0) {
            return null;
        }

        $delivery = $this->relationLoaded('delivery') ? $this->delivery : $this->delivery()->first();
        if (
            $delivery
            && $delivery->status !== 'delivered'
            && $delivery->dispatched_at === null
            && $delivery->riderAssignmentStage() === 'pickup'
        ) {
            return null;
        }

        if (
            abs((float) $this->rider_fee - self::PICKUP_FEE_PER_PARCEL) < 0.01
            && (float) ($this->last_mile_rider_fee ?? 0) <= 0
            && $this->pickup_rider_id === null
            && (! $delivery || $delivery->sorting_center_handoff_rider_id === null)
        ) {
            return null;
        }

        return (int) $this->rider_id;
    }

    public function hasPickupAssignment(): bool
    {
        if ($this->pickupRiderId() !== null) {
            return true;
        }

        $delivery = $this->relationLoaded('delivery') ? $this->delivery : $this->delivery()->first();
        if (
            $this->pickup_rider_fee !== null
            && (float) $this->pickup_rider_fee > 0
            && (
                ($this->pickup_rider_id !== null && (int) $this->pickup_rider_id > 0)
                || ($delivery && ($delivery->sorting_center_handoff_rider_id !== null || $delivery->sorting_center_handoff_at !== null))
                || $this->rider_id === null
            )
        ) {
            return true;
        }

        return abs((float) $this->rider_fee - self::PICKUP_FEE_PER_PARCEL) < 0.01
            && (float) ($this->last_mile_rider_fee ?? 0) <= 0;
    }

    public function hasHubToDoorAssignment(): bool
    {
        if ($this->hubToDoorRiderId() !== null) {
            return true;
        }

        if ($this->last_mile_rider_fee !== null && (float) $this->last_mile_rider_fee > 0) {
            return true;
        }

        return ! $this->hasPickupAssignment()
            && abs((float) $this->rider_fee - self::HUB_TO_DOOR_FEE_PER_PARCEL) < 0.01;
    }

    public function pickupParcelsCount(?string $assignmentFilter = null): int
    {
        if ($assignmentFilter === self::ASSIGNMENT_HUB_TO_DOOR) {
            return 0;
        }

        return $this->hasPickupAssignment() ? 1 : 0;
    }

    public function hubToDoorParcelsCount(?string $assignmentFilter = null): int
    {
        if ($assignmentFilter === self::ASSIGNMENT_PICKUP) {
            return 0;
        }

        return $this->hasHubToDoorAssignment() ? 1 : 0;
    }

    public function pickupFee(?string $assignmentFilter = null): float
    {
        return self::calculatePickupFee($this->pickupParcelsCount($assignmentFilter));
    }

    public function hubToDoorFee(?string $assignmentFilter = null): float
    {
        return self::calculateHubToDoorFee($this->hubToDoorParcelsCount($assignmentFilter));
    }

    public function totalRiderFee(?string $assignmentFilter = null): float
    {
        return round($this->pickupFee($assignmentFilter) + $this->hubToDoorFee($assignmentFilter), 2);
    }

    public function managementCommission(): float
    {
        if ((float) $this->admin_commission > 0) {
            return round((float) $this->admin_commission, 2);
        }

        return self::calculateManagementCommission((float) $this->amount);
    }

    public function matchesAssignmentType(?string $assignmentType): bool
    {
        return match ($assignmentType) {
            self::ASSIGNMENT_PICKUP => $this->hasPickupAssignment(),
            self::ASSIGNMENT_HUB_TO_DOOR => $this->hasHubToDoorAssignment(),
            default => true,
        };
    }

    /**
     * Build the assignment leg breakdown for this transaction.
     *
     * @return array<int, array{type: string, label: string, rider_id: int|null, rider_name: string, hub_name: string, parcels: int, fee_per_parcel: float, total_fee: float}>
     */
    public function assignmentLegs(?string $assignmentFilter = null): array
    {
        $legs = [];
        $delivery = $this->relationLoaded('delivery') ? $this->delivery : null;

        if ($this->pickupParcelsCount($assignmentFilter) > 0) {
            $pickupRider = ($this->relationLoaded('pickupRider') ? $this->pickupRider : null)
                ?? ($delivery && $delivery->relationLoaded('sortingCenterHandoffRider') ? $delivery->sortingCenterHandoffRider : null)
                ?? ($this->pickupRiderId() ? Rider::find($this->pickupRiderId()) : null)
                ?? ($this->relationLoaded('rider') ? $this->rider : null);

            $originHub = ($this->relationLoaded('originCenter') ? $this->originCenter : null)
                ?? ($this->relationLoaded('logisticsCenter') ? $this->logisticsCenter : null);

            $legs[] = [
                'type' => self::ASSIGNMENT_PICKUP,
                'label' => 'Pickup',
                'rider_id' => $this->pickupRiderId(),
                'rider_name' => $pickupRider?->name ?? 'Unassigned',
                'hub_name' => $originHub?->name ?? '—',
                'parcels' => 1,
                'fee_per_parcel' => self::PICKUP_FEE_PER_PARCEL,
                'total_fee' => self::PICKUP_FEE_PER_PARCEL,
            ];
        }

        if ($this->hubToDoorParcelsCount($assignmentFilter) > 0) {
            $lastMileRider = ($this->relationLoaded('rider') ? $this->rider : null)
                ?? ($this->hubToDoorRiderId() ? Rider::find($this->hubToDoorRiderId()) : null);

            $destHub = ($this->relationLoaded('destinationCenter') ? $this->destinationCenter : null)
                ?? ($this->relationLoaded('logisticsCenter') ? $this->logisticsCenter : null);

            $legs[] = [
                'type' => self::ASSIGNMENT_HUB_TO_DOOR,
                'label' => 'Hub-to-Door',
                'rider_id' => $this->hubToDoorRiderId(),
                'rider_name' => $lastMileRider?->name ?? 'Unassigned',
                'hub_name' => $destHub?->name ?? '—',
                'parcels' => 1,
                'fee_per_parcel' => self::HUB_TO_DOOR_FEE_PER_PARCEL,
                'total_fee' => self::HUB_TO_DOOR_FEE_PER_PARCEL,
            ];
        }

        return $legs;
    }
}
