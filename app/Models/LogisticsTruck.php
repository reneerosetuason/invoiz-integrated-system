<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsTruck extends Model
{
    use SoftDeletes;

    protected $fillable = ['logistics_company_id', 'plate_number', 'name', 'vehicle_type', 'model', 'parcel_capacity', 'capacity_kg', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['capacity_kg' => 'decimal:2', 'parcel_capacity' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function driver(): HasOne
    {
        return $this->hasOne(HubTransportDriver::class, 'logistics_truck_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'truck_id');
    }
}
