<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'receiver_id',
        'body',
        'is_read',
    ];

    protected static function booted(): void
    {
        // Attach buyer threads so buyer-app users can read admin DMs.
        static::creating(function (Message $message) {
            \App\Support\ChatBridge::link($message);
        });
    }

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    protected static array $roleCache = [];

    /**
     * Role of the sender in this thread (seller/buyer/admin/rider).
     * Corresponds across buyer chat, seller chat and admin chat:
     * admin senders are always labelled admin; otherwise the
     * conversation's seller_id/buyer_id decides, falling back to the
     * sender's account role.
     */
    public function senderRole(): string
    {
        $sender = User::find($this->sender_id);
        if ($sender && $sender->role === 'admin') {
            return 'admin';
        }
        try {
            $sid = $this->conversation?->seller_id;
            $bid = $this->conversation?->buyer_id ?? null;
            if ($sid && (int) $this->sender_id === (int) $sid) return 'seller';
            if ($bid && (int) $this->sender_id === (int) $bid) return 'buyer';
        } catch (\Throwable $e) {}
        if (! isset(static::$roleCache[$this->sender_id])) {
            if (Seller::where('user_id', $this->sender_id)->exists()) {
                static::$roleCache[$this->sender_id] = 'seller';
            } else {
                $u = User::find($this->sender_id);
                static::$roleCache[$this->sender_id] = $u?->role ?? 'buyer';
            }
        }
        return static::$roleCache[$this->sender_id];
    }

    public static function roleBadge(string $role): array
    {
        return match ($role) {
            'seller' => ['Seller', '#0E4A57', '#e0f2f1'],
            'admin' => ['Admin', '#92400e', '#fef3c7'],
            'rider' => ['Rider', '#1e40af', '#dbeafe'],
            default => ['Buyer', '#4b5563', '#f3f4f6'],
        };
    }
}
