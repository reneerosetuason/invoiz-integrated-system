<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'logistics_conversations';

    protected $fillable = [
        'order_id',
        'rider_id',
        'participant_type',
        'participant_id',
        'participant_name',
        'subject',
        'last_message_preview',
        'last_message_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * The delivery behind an order-linked thread.
     *
     * NOTE: conversations store the shared ORDER id (order_id), while the
     * delivery row references it via its own order_id column — so the owner
     * key here is deliveries.order_id, not deliveries.id. Joining on the
     * wrong key silently drops every tracking reference and breaks
     * tracking-number search.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'order_id', 'order_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeForParticipant($query, string $type, int $id)
    {
        return $query->where('participant_type', $type)->where('participant_id', $id);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('participant_name', 'like', "%{$term}%")
              ->orWhere('subject', 'like', "%{$term}%")
              ->orWhere('last_message_preview', 'like', "%{$term}%");
        });
    }
}
