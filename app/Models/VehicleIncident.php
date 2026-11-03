<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleIncident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'logistics_company_id',
        'logistics_truck_id',
        'hub_transport_driver_id',
        'hub_transfer_id',
        'incident_type',
        'severity',
        'status',
        'incident_date',
        'location',
        'description',
        'damage_details',
        'investigation_notes',
        'resolution_notes',
        'vehicle_action_taken',
        'evidence_photos',
        'reported_by_id',
        'investigated_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'datetime',
            'resolved_at' => 'datetime',
            'evidence_photos' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'hub_transport_driver_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function investigatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function maintenanceRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class, 'vehicle_incident_id');
    }
}
