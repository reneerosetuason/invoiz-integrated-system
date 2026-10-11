<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsCenter extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'city', 'province', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
        return $this->hasMany(User::class, 'center_id')->where('role', 'staff');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'center_id');
    }

    public function destinationDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'destination_center_id');
    }
}
