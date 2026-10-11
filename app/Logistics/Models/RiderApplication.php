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
        'vehicle_registration',
        'documents',
        'status',
        'submitted_via',
        'rider_type',
        'vehicle_ownership',
        'notes',
        'reviewed_at',
        'center_id',
        'service_area_id',
        'approved_by',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'birthday' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'documents' => 'array',
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
}
