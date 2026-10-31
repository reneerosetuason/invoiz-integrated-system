<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class HubTransportDriver extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = ['logistics_company_id', 'logistics_truck_id', 'name', 'email', 'password', 'phone', 'license_number', 'status', 'is_online', 'remarks'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_online' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'driver_id');
    }
}
