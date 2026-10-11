<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'rider_id',
        'reason',
        'notes',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}