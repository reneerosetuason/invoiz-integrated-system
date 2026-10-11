<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'middle_initial',
        'sex',
        'email',
        'password',
        'phone',
        'birthday',
        'age',
        'province',
        'municipality',
        'barangay',
        'address_line',
        'role',
        'status',
        'center_id',
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
            'birthday' => 'date',
            'password' => 'hashed',
        ];
    }

    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    /**
     * Store profile for seller-role users. Referenced by the rider
     * conversation discovery (store name on seller threads); absent rows
     * simply fall back to the user's personal name.
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class, 'user_id');
    }

    public function logisticsCenter()
    {
        return $this->belongsTo(LogisticsCenter::class, 'center_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Public URL for the user's profile photo (stored per-user in
     * logistics_settings.photo_path), or null so callers can fall back to
     * the initial-letter avatar. Read-only: never creates a settings row.
     * Returns null when the referenced file is missing on disk.
     */
    public function profilePhotoUrl(): ?string
    {
        $path = LogisticsSetting::where('user_id', $this->id)->value('photo_path');

        if (! $path || ! file_exists(public_path($path))) {
            return null;
        }

        return asset($path);
    }

    /**
     * Human-readable role label used across the Logistics UI.
     * The internal role values ('admin', 'staff', 'rider') are preserved for
     * authorization; this maps them to Logistics-specific display names.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Logistics Manager',
            'staff' => 'Logistics Staff',
            'rider' => 'Rider',
            default => ucfirst((string) $this->role),
        };
    }
}
