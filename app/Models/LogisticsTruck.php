<?php

namespace App\Models;

use App\Support\LogisticsVehicleCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsTruck extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_INACTIVE = 'inactive';

    public const OWNERSHIP_COMPANY = 'company';
    public const OWNERSHIP_DRIVER = 'driver_owned';

    public const VERIFICATION_APPROVED = 'approved';
    public const VERIFICATION_PENDING = 'pending_review';
    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'logistics_company_id',
        'ownership_type',
        'hub_transport_driver_id',
        'plate_number',
        'vehicle_registration',
        'document_path',
        'name',
        'vehicle_type',
        'model',
        'parcel_capacity',
        'capacity_kg',
        'status',
        'verification_status',
        'verification_notes',
        'reviewed_by',
        'reviewed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kg' => 'decimal:2',
            'parcel_capacity' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function driver(): HasOne
    {
        return $this->hasOne(HubTransportDriver::class, 'logistics_truck_id');
    }

    public function ownerDriver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'hub_transport_driver_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'truck_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(VehicleIncident::class, 'logistics_truck_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class, 'logistics_truck_id');
    }

    public function isDriverOwned(): bool
    {
        return $this->ownership_type === self::OWNERSHIP_DRIVER;
    }

    public function isCompanyOwned(): bool
    {
        return $this->ownership_type === self::OWNERSHIP_COMPANY;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === self::VERIFICATION_APPROVED;
    }

    public function isPendingReview(): bool
    {
        return $this->verification_status === self::VERIFICATION_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === self::VERIFICATION_REJECTED;
    }

    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }

    public function isDamaged(): bool
    {
        return $this->status === self::STATUS_DAMAGED;
    }

    public function isAssignable(): bool
    {
        return LogisticsVehicleCategory::for($this->vehicle_type) === 'four_wheel'
            && $this->isApproved()
            && ! in_array($this->status, [self::STATUS_IN_TRANSIT, self::STATUS_MAINTENANCE, self::STATUS_DAMAGED, self::STATUS_RETIRED, self::STATUS_INACTIVE], true)
            && (int) $this->parcel_capacity > 0
            && (float) $this->capacity_kg > 0;
    }

    /**
     * A truck with an active transfer (other than an explicitly excluded one)
     * must never be paired with another driver or added to a second manifest.
     */
    public function hasActiveTransfer(?int $exceptTransferId = null): bool
    {
        return $this->transfers()
            ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
            ->when($exceptTransferId !== null, fn ($query) => $query->whereKeyNot($exceptTransferId))
            ->exists();
    }
}
