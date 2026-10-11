<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'capacity_kg',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kg' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function riders()
    {
        return $this->hasMany(Rider::class, 'vehicle_type', 'name');
    }

    public static function capacityMap(): array
    {
        return static::query()
            ->get()
            ->mapWithKeys(fn ($t) => [strtolower($t->name) => (float) $t->capacity_kg])
            ->all();
    }

    public static function activeLabels(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'name')
            ->all();
    }
}
