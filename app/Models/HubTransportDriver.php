<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class HubTransportDriver extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = [
        'user_id',
        'logistics_company_id',
        'logistics_truck_id',
        'name',
        'email',
        'password',
        'phone',
        'license_number',
        'status',
        'is_online',
        'remarks',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_online' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function ownedTruck(): HasOne
    {
        return $this->hasOne(LogisticsTruck::class, 'hub_transport_driver_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'driver_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(HubTransferEarning::class, 'hub_transport_driver_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(VehicleIncident::class, 'hub_transport_driver_id');
    }

    public function effectiveTruck(): ?LogisticsTruck
    {
        return $this->truck ?: $this->ownedTruck;
    }
}
