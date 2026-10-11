<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'center_id',
        'service_area_id',
        'name',
        'email',
        'phone',
        'vehicle_type',
        'vehicle_capacity_kg',
        'license_plate',
        'status',
        'is_verified',
        'approved_at',
        'vehicle_verification',
        'vehicle_verification_note',
        'vehicle_verified_at',
        'vehicle_verified_by',
        'is_online',
        'avatar',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_verified' => 'boolean',
            'approved_at' => 'datetime',
            'vehicle_capacity_kg' => 'decimal:2',
            'vehicle_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logisticsCenter()
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function serviceArea()
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->whereIn('status', Delivery::ACTIVE_STATUSES);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocation::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(RiderNotification::class);
    }

    public function capacityLimit(): float
    {
        if ($this->vehicle_capacity_kg !== null) {
            return (float) $this->vehicle_capacity_kg;
        }

        $caps = LogisticsSetting::vehicleCapacities();
        $key = strtolower(trim((string) $this->vehicle_type));

        return (float) ($caps[$key] ?? 0);
    }

    public function vehicleTypeIsActive(): bool
    {
        $type = VehicleType::whereRaw('LOWER(name) = ?', [strtolower(trim((string) $this->vehicle_type))])->first();

        return $type ? (bool) $type->is_active : true;
    }

    public function isEligibilityApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * The rider must belong to the same logistics center as the delivery
     * destination. If the delivery has no destination yet the rule cannot
     * apply, so it does not block legacy or pre-sorting assignments.
     */
    public function matchesDestinationCenter(Delivery $delivery): bool
    {
        if ($delivery->destination_center_id === null) {
            return true;
        }

        if ($this->center_id === null) {
            return false;
        }

        return (int) $this->center_id === (int) $delivery->destination_center_id;
    }

    /**
     * The rider must match the delivery service area, but only when both
     * sides actually have a service area assigned.
     */
    public function matchesServiceArea(Delivery $delivery): bool
    {
        if ($this->service_area_id === null || $delivery->service_area_id === null) {
            return true;
        }

        return (int) $this->service_area_id === (int) $delivery->service_area_id;
    }
}
