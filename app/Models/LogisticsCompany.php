<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsCompany extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'registration_number', 'status', 'created_by'];

    public function centers(): HasMany
    {
        return $this->hasMany(LogisticsCenter::class, 'logistics_company_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'logistics_company_id')->whereIn('role', ['staff', 'logistics_staff']);
    }

    public function trucks(): HasMany
    {
        return $this->hasMany(LogisticsTruck::class);
    }

    public function transportDrivers(): HasMany
    {
        return $this->hasMany(HubTransportDriver::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class);
    }
}
