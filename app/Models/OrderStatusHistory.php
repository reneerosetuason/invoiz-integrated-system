<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'notes',
        'note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function setNoteAttribute($value): void
    {
        $this->attributes['notes'] = $value;
    }

    public function getNoteAttribute(): ?string
    {
        return $this->attributes['notes'] ?? $this->attributes['note'] ?? null;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->attributes['notes'] ?? $this->attributes['note'] ?? null;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
