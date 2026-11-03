<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleMaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const RESPONSIBILITY_COMPANY = 'company';
    public const RESPONSIBILITY_DRIVER_OWNER = 'driver_owner';
    public const RESPONSIBILITY_INSURANCE = 'insurance';
    public const RESPONSIBILITY_THIRD_PARTY = 'third_party';
    public const RESPONSIBILITY_SHARED = 'shared';

    public const STATUS_PENDING_ASSESSMENT = 'pending_assessment';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_REPAIR = 'in_repair';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_ROUTINE = 'routine_maintenance';
    public const TYPE_ACCIDENT = 'accident_repair';
    public const TYPE_MECHANICAL = 'mechanical_repair';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_BODY_PAINT = 'body_paint';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'reference',
        'logistics_company_id',
        'logistics_truck_id',
        'vehicle_incident_id',
        'maintenance_type',
        'description',
        'service_provider',
        'estimated_cost',
        'actual_cost',
        'payment_responsibility',
        'responsibility_notes',
        'invoice_attachment_path',
        'status',
        'authorized_by_id',
        'started_at',
        'completed_at',
        'returned_to_service_at',
        'admin_remarks',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_to_service_at' => 'datetime',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(VehicleIncident::class, 'vehicle_incident_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
