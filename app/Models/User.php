<?php

namespace App\Models;

use App\Notifications\ResetPasswordMail;
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
        'region',
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
        'center_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expires_at',
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
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            }
            if ((empty($user->first_name) || empty($user->last_name)) && ! empty($user->name)) {
                $parts = preg_split('/\s+/', trim($user->name), 2);
                if (empty($user->first_name)) {
                    $user->first_name = $parts[0] ?? '';
                }
                if (empty($user->last_name)) {
                    $user->last_name = $parts[1] ?? '';
                }
            }
            if ($user->isDirty('first_name') || $user->isDirty('last_name')) {
                $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                if ($fullName !== '') {
                    $user->name = $fullName;
                }
            }
            if (empty($user->role)) {
                $user->role = 'buyer';
            }
            if (empty($user->status)) {
                $user->status = 'active';
            }
            if (empty($user->account_status)) {
                $user->account_status = 'active';
            }
        });
    }

    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    // ---------------------------------------------------------------
    // Logistics (shared invoizdb; used by the /logistics center)
    // ---------------------------------------------------------------
    public function rider()
    {
        return $this->hasOne(Rider::class);
    }

    public function logisticsCenter()
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function isStaff(): bool
    {
        // Both legacy 'staff' and 'logistics_staff' work the counter:
        // center-scoped lists, center checks, and own-center reviews.
        return in_array($this->role, ['staff', 'logistics_staff'], true);
    }

    /**
     * Human-readable role label used across the Logistics UI.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Logistics Manager',
            'logistics_admin' => 'Logistics Admin',
            'staff' => 'Logistics Staff',
            'logistics_staff' => 'Logistics Staff',
            'rider' => 'Rider',
            default => ucfirst((string) $this->role),
        };
    }

    public function isLogisticsAdmin(): bool
    {
        return in_array($this->role, ['admin', 'logistics_admin'], true);
    }

    public function isLogisticsStaff(): bool
    {
        // Role-based staff plus center-assigned accounts: a buyer/seller
        // approved for a center keeps its marketplace role (single role
        // column), and the center assignment carries logistics access.
        return in_array($this->role, ['admin', 'staff', 'logistics_admin', 'logistics_staff'], true)
            || $this->hasProvisionedCenterAssignment();
    }

    public function canManageRiders(): bool
    {
        // Marketplace administrators may inspect logistics records, but rider
        // accounts and assignments belong to authorized logistics personnel.
        return ! $this->isAdmin() && $this->isActive() && $this->isLogisticsStaff();
    }

    /**
     * True only when a non-staff marketplace account was assigned a center
     * through an approved, provisioned center application. A center_id alone
     * is not proof of logistics access.
     */
    public function hasProvisionedCenterAssignment(): bool
    {
        if (empty($this->center_id)) {
            return false;
        }

        if (in_array($this->role, ['staff', 'logistics_staff'], true)) {
            return true;
        }

        if (! in_array($this->role, ['buyer', 'seller'], true)) {
            return false;
        }

        return LogisticsCenterApplication::query()
            ->where('email', $this->email)
            ->where('status', 'approved')
            ->whereNotNull('provisioned_at')
            ->exists();
    }

    /**
     * A rider login is valid only while the linked Rider profile exists,
     * has been approved, and is not inactive. Marketplace roles are kept.
     */
    public function hasApprovedRiderProfile(): bool
    {
        $rider = $this->rider;

        return $rider !== null
            && $rider->isEligibilityApproved()
            && $rider->status !== 'inactive';
    }

    /**
     * True when approval/provisioning flows must NOT replace this account's
     * role or password (one email keeps working everywhere): marketplace
     * roles and real admins keep their own login; only role-less or
     * logistics-only accounts are converted.
     */
    public function keepsOwnLogin(): bool
    {
        return in_array($this->role, ['buyer', 'seller', 'admin'], true) || $this->isAdmin();
    }

    /**
     * Profile photo URL (stored per-user in logistics_settings),
     * or null so callers fall back to the initial-letter avatar.
     */
    public function profilePhotoUrl(): ?string
    {
        try {
            $path = LogisticsSetting::where('user_id', $this->id)->value('photo_path');
        } catch (\Throwable $e) {
            return null;
        }
        if (! $path || ! file_exists(public_path($path))) {
            return null;
        }

        return asset($path);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved' && $this->isActive();
    }

    public function canSell(): bool
    {
        return $this->isActive() && ($this->seller?->isApproved() ?? false);
    }

    public function hasBlockedMarketplaceApproval(): bool
    {
        if ($this->role === 'seller' && $this->seller !== null) {
            return false;
        }

        return in_array(strtolower((string) $this->approval_status), ['pending', 'rejected'], true);
    }

    public function canUseBuyerArea(): bool
    {
        if (! $this->isActive() || $this->hasBlockedMarketplaceApproval()) {
            return false;
        }

        if ($this->isBuyer() || $this->canSell()) {
            return true;
        }

        return $this->isSeller()
            && $this->seller !== null
            && ! $this->seller->isApproved();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class, 'buyer_id');
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

    public function sendPasswordResetNotification($token)
    {
        // Unified reset page for every role. (The framework default would
        // build route('password.reset'), which collides with the logistics
        // auth route names.)
        $this->notify(new ResetPasswordMail($token));
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
        $nameParts = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        if ($nameParts !== '') {
            return $nameParts;
        }
        if (! empty($this->name)) {
            return $this->name;
        }

        return '';
    }

    /** $user->full_name — used by store profile payloads. Same as displayName(). */
    public function getFullNameAttribute(): string
    {
        return $this->displayName();
    }

    /**
     * Chat badges for this account: always the account role, plus a Seller
     * badge when a buyer-role account also owns an approved store, so
     * nobody mistakes which hat they wear in a conversation.
     * Returns list of [label, foreground, background].
     */
    public function roleBadges(): array
    {
        $role = $this->role ?? 'buyer';
        $badges = [Message::roleBadge($role)];
        if ($role === 'buyer') {
            try {
                $hasStore = Seller::where('user_id', $this->id)
                    ->where(function ($q) {
                        $q->where('approval_status', 'approved')->orWhere('status', 'approved');
                    })->exists();
            } catch (\Throwable $e) {
                $hasStore = false;
            }
            if ($hasStore) {
                $badges[] = Message::roleBadge('seller');
            }
        }
        // Center-linked buyer/seller accounts keep their marketplace role,
        // while also having logistics staff access.
        if (in_array($role, ['buyer', 'seller'], true) && ! empty($this->center_id)) {
            $badges[] = Message::roleBadge('logistics_staff');
        }

        return $badges;
    }

    /** Contact role of another user as seen in a chat thread. */
    public static function chatRoleFor(?User $user): string
    {
        if (! $user) {
            return 'buyer';
        }

        $role = $user->role ?? 'buyer';
        if (in_array($role, ['admin', 'logistics_admin', 'logistics_staff', 'staff', 'rider'], true)) {
            return match ($role) {
                'staff' => 'logistics_staff',
                default => $role,
            };
        }
        if ($role === 'seller') {
            return 'seller';
        }

        try {
            $isSeller = Seller::where('user_id', $user->id)->exists();
        } catch (\Throwable $e) {
            $isSeller = false;
        }

        return $isSeller ? 'seller' : 'buyer';
    }
}
