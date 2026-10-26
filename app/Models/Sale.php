<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'type',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ProductSale::class);
    }

    public function isRunning(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        $now = now();
        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }
}
