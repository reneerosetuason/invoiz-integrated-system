<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'center_id',
        'status',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}