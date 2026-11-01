<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'middle_initial',
        'sex',
        'birthday',
        'age',
        'email',
        'phone',
        'address',
        'house_number',
        'street',
        'barangay',
        'municipality',
        'province',
        'vehicle_type',
        'license_plate',
        'license_number',
        'license_expiry',
        'vehicle_registration',
        'documents',
        'status',
        'submitted_via',
        'rider_type',
        'application_type',
        'vehicle_name',
        'vehicle_model',
        'parcel_capacity',
        'capacity_kg',
        'vehicle_ownership',
        'notes',
        'reviewed_at',
        'center_id',
        'service_area_id',
        'logistics_truck_id',
        'hub_transport_driver_id',
        'approved_by',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'birthday' => 'date:Y-m-d',
            'license_expiry' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'documents' => 'array',
            'parcel_capacity' => 'integer',
            'capacity_kg' => 'decimal:2',
        ];
    }

    public function logisticsCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function hubTransportDriver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'hub_transport_driver_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RiderApplicationLog::class)->latest();
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(RiderApplicationDocument::class)->orderBy('document_type');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function isHubTransportDriver(): bool
    {
        return $this->application_type === 'hub_transport_driver';
    }

    public function isDeliveryRider(): bool
    {
        return ! $this->isHubTransportDriver();
    }

    public function applicationTypeLabel(): string
    {
        return $this->isHubTransportDriver() ? 'Hub Transport Driver' : 'Delivery Rider';
    }
}
