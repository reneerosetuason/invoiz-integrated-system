<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Email-verification OTP state for rider applications.
 *
 * Security rules enforced here and in the controller:
 * - only Hash::make() of the 6-digit code is persisted (never plaintext)
 * - codes expire after 5 minutes, are single-use, and a new request
 *   invalidates previous unconsumed codes for the same email
 * - a consumed verification authorizes POST /api/rider/apply for that
 *   exact email address for 24 hours (enough to finish the form)
 */
class RiderEmailVerification extends Model
{
    public const CODE_TTL_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const VERIFIED_VALID_HOURS = 24;

    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    /**
     * Whether the given email completed OTP verification recently enough
     * to authorize an application submission. Always scoped to the exact
     * submitted address — verification never transfers between emails.
     */
    public static function isVerified(string $email): bool
    {
        return self::where('email', strtolower(trim($email)))
            ->whereNotNull('consumed_at')
            ->where('created_at', '>', now()->subHours(self::VERIFIED_VALID_HOURS))
            ->exists();
    }
}
