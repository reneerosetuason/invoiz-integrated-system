<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $table = 'logistics_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'body',
        'is_read',
        'read_at',
        'edited_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('id');
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Can the given user (logistics staff or the participant rider) edit this message?
     */
    public function canBeEditedBy(\Illuminate\Foundation\Auth\User $user): bool
    {
        if ($this->isDeleted()) {
            return false;
        }

        if (in_array($user->role, ['admin', 'staff'])) {
            return $this->sender_type === 'logistics' && (int) $this->sender_id === (int) $user->id;
        }

        if ($user->role === 'rider') {
            return $this->sender_type === 'rider' && (int) $this->sender_id === (int) $user->id;
        }

        return false;
    }

    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }
}
