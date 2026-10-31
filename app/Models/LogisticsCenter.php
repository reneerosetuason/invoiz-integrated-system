<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsCenter extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'city', 'province', 'phone', 'is_active', 'logistics_company_id', 'ownership_verified_at', 'ownership_verified_by'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ownership_verified_at' => 'datetime',
        ];
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(ServiceArea::class);
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class, 'center_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'center_id')->whereIn('role', ['staff', 'logistics_staff']);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'center_id');
    }

    public function destinationDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'destination_center_id');
    }

    public function company()
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function originTransfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'origin_center_id');
    }

    public function destinationTransfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'destination_center_id');
    }
}
