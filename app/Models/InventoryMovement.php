<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'type',
        'quantity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
