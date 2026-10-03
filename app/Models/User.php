<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        // Unified schema: supports BOTH admin-style and buyer-style columns
        // against the shared `invoizdb` database.
        'name',
        'first_name',
        'last_name',
        'middle_initial',
        'sex',
        'email',
        'password',
        'is_admin',
        'role',
        'account_status',
        'status',
        'approval_status',
        'phone',
        'birthday',
        'age',
        'province',
        'municipality',
        'barangay',
        'city',
        'address_line',
        'postal_code',
        'id_image',
        'profile_picture',
        'bio',
        'otp',
        'otp_expires_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep every identity pair in sync so buyer/seller/admin
        // always show the same name, status and role.
        static::saving(function (User $user) {
            $mapToLegacy = ['active' => 'active', 'suspended' => 'suspended', 'deactivated' => 'inactive'];
            $mapToAdmin = ['active' => 'active', 'suspended' => 'suspended', 'inactive' => 'deactivated'];
            if ($user->isDirty('account_status') && isset($mapToLegacy[$user->account_status])) {
                $user->status = $mapToLegacy[$user->account_status];
            } elseif ($user->isDirty('status') && isset($mapToAdmin[$user->status])) {
                $user->account_status = $mapToAdmin[$user->status];
            }
            if (empty($user->name) && (! empty($user->first_name) || ! empty($user->last_name))) {
                $user->name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
            if ((empty($user->first_name) || empty($user->last_name)) && ! empty($user->name)) {
                $parts = preg_split('/\s+/', trim($user->name), 2);
                if (empty($user->first_name)) $user->first_name = $parts[0] ?? '';
                if (empty($user->last_name)) $user->last_name = $parts[1] ?? '';
            }
            if (empty($user->role)) $user->role = 'buyer';
            if (empty($user->status)) $user->status = 'active';
            if (empty($user->account_status)) $user->account_status = 'active';
        });
    }

    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function isAdmin()
    {
        return (bool) $this->is_admin || $this->role === 'admin';
    }

    public function isSeller()
    {
        return $this->role === 'seller';
    }

    public function isBuyer()
    {
        // Default role is buyer — new registrations always become buyers.
        return in_array($this->role, ['buyer', null, ''], true);
    }

    public function isActive()
    {
        if (! empty($this->account_status)) {
            return $this->account_status === 'active';
        }
        return ($this->status ?? 'active') === 'active';
    }

    public function displayName(): string
    {
        if (! empty($this->name)) {
            return $this->name;
        }
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /** $user->full_name — used by store profile payloads. Same as displayName(). */
    public function getFullNameAttribute(): string
    {
        return $this->displayName();
    }
}
