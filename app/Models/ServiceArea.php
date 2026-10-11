<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceArea extends Model
{
    use HasFactory;

    protected $fillable = ['logistics_center_id', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function logisticsCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class);
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class, 'service_area_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'service_area_id');
    }
}
