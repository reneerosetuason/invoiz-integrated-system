<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rider extends Model
{
    use HasFactory;

    /** Maximum number of simultaneous active deliveries a rider may hold. */
    public const MAX_ACTIVE_DELIVERIES = 3;

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
        'license_number',
        'license_expiry',
        'vehicle_registration',
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
            'license_expiry' => 'date:Y-m-d',
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

    public function activeDeliveryCount(): int
    {
        return $this->activeDeliveries()->count();
    }

    public function canAcceptMoreDeliveries(): bool
    {
        return $this->activeDeliveryCount() < self::MAX_ACTIVE_DELIVERIES;
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocation::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(RiderLocation::class)->latestOfMany();
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

    public function licenseExpired(): bool
    {
        if ($this->license_expiry === null) {
            return false;
        }

        return $this->license_expiry->lt(now()->startOfDay());
    }

    public function licenseExpiringSoon(int $days = 30): bool
    {
        if ($this->license_expiry === null || $this->licenseExpired()) {
            return false;
        }

        return $this->license_expiry->lte(now()->startOfDay()->addDays($days));
    }

    public function licenseStatus(): string
    {
        if ($this->license_expiry === null) {
            return 'missing';
        }

        if ($this->licenseExpired()) {
            return 'expired';
        }

        return $this->licenseExpiringSoon() ? 'expiring' : 'valid';
    }

    /**
     * Reason the rider cannot take new work, or null when they may go online
     * and accept new assignments. A null licence expiry stays permissive for
     * legacy riders that predate the licence fields.
     */
    public function assignmentBlockReason(): ?string
    {
        if (! $this->isEligibilityApproved()) {
            return 'Rider application not approved';
        }

        if ($this->status === 'inactive') {
            return 'Account inactive';
        }

        if ($this->vehicle_verification !== 'verified') {
            return 'Vehicle not verified';
        }

        if (! $this->vehicleTypeIsActive()) {
            return 'Vehicle type deactivated';
        }

        if ($this->licenseExpired()) {
            return 'Driver\'s license expired on '.$this->license_expiry->format('M d, Y');
        }

        return null;
    }

    public function canGoOnline(): bool
    {
        return $this->assignmentBlockReason() === null;
    }

    /**
     * The rider must belong to the same logistics center as the delivery
     * destination. If the delivery has no destination yet the rule cannot
     * apply, so it does not block legacy or pre-sorting assignments.
     */
    public function matchesDestinationCenter(Delivery $delivery): bool
    {
        // Sorted parcels are matched against their destination. Before sorting,
        // the handling center is the only known scope, so use it as the fallback
        // instead of treating an unset destination as a wildcard.
        $targetCenterId = $delivery->destination_center_id ?? $delivery->center_id;

        if ($targetCenterId === null) {
            // A completely unrouted parcel must remain assignable during
            // onboarding; access to it is still restricted to global admins.
            return true;
        }

        if ($this->center_id === null) {
            return false;
        }

        return (int) $this->center_id === (int) $targetCenterId;
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
