<?php

namespace App\Services;

use App\Models\Delivery;

class DeliveryOtpService
{
    public const ELIGIBLE_STATUSES = ['out_for_delivery', 'arrived_at_customer'];

    public function requiredFor(Delivery $delivery): bool
    {
        if ($delivery->order_id === null) {
            return false;
        }

        if ($delivery->relationLoaded('order')) {
            return $delivery->order?->buyer_id !== null;
        }

        return $delivery->order()->whereNotNull('buyer_id')->exists();
    }

    public function ensureFor(Delivery $delivery): string
    {
        if ($this->hasUsableCode($delivery)) {
            return (string) $delivery->delivery_otp;
        }

        return $this->issueFor($delivery);
    }

    public function forBuyer(Delivery $delivery): array
    {
        $otp = $this->ensureFor($delivery);

        return [
            'otp' => $otp,
            'expires_at' => $delivery->delivery_otp_expires_at?->toIso8601String(),
            'attempts_remaining' => $this->attemptsRemaining($delivery),
        ];
    }

    public function issueFor(Delivery $delivery): string
    {
        $length = max(6, (int) config('logistics.delivery_otp.length', 6));
        $otp = str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT,
        );

        $delivery->forceFill([
            'delivery_otp' => $otp,
            'delivery_otp_expires_at' => now()->addMinutes($this->ttlMinutes()),
            'delivery_otp_attempts' => 0,
            'delivery_otp_consumed_at' => null,
        ])->save();

        return $otp;
    }

    public function verify(Delivery $delivery, ?string $submitted): array
    {
        if (! $this->requiredFor($delivery)) {
            return ['ok' => true];
        }

        if (! $this->hasCode($delivery) || $delivery->delivery_otp_consumed_at !== null) {
            return [
                'ok' => false,
                'reason' => 'missing',
                'message' => 'A delivery OTP is required. Ask the buyer to provide the 6-digit code from their app.',
            ];
        }

        if ($delivery->delivery_otp_expires_at === null || $delivery->delivery_otp_expires_at->isPast()) {
            return [
                'ok' => false,
                'reason' => 'expired',
                'message' => 'The delivery OTP has expired. Ask the buyer to request a new code.',
            ];
        }

        $maxAttempts = $this->maxAttempts();
        $attempts = (int) $delivery->delivery_otp_attempts;

        if ($attempts >= $maxAttempts) {
            return [
                'ok' => false,
                'reason' => 'exhausted',
                'message' => 'Too many incorrect delivery OTP attempts. Ask the buyer to request a new code.',
            ];
        }

        $submitted = trim((string) $submitted);

        if ($submitted === '' || ! hash_equals((string) $delivery->delivery_otp, $submitted)) {
            $attempts++;
            $delivery->forceFill(['delivery_otp_attempts' => $attempts])->save();
            $remaining = max(0, $maxAttempts - $attempts);

            if ($remaining === 0) {
                return [
                    'ok' => false,
                    'reason' => 'exhausted',
                    'message' => 'Too many incorrect delivery OTP attempts. Ask the buyer to request a new code.',
                ];
            }

            return [
                'ok' => false,
                'reason' => 'invalid',
                'message' => 'Incorrect delivery OTP. '.$remaining.' '.($remaining === 1 ? 'attempt' : 'attempts').' remaining.',
                'attempts_remaining' => $remaining,
            ];
        }

        return ['ok' => true];
    }

    public function consume(Delivery $delivery): void
    {
        if (! $this->requiredFor($delivery)) {
            return;
        }

        $delivery->forceFill(['delivery_otp_consumed_at' => now()])->save();
    }

    public function attemptsRemaining(Delivery $delivery): int
    {
        return max(0, $this->maxAttempts() - (int) $delivery->delivery_otp_attempts);
    }

    private function hasCode(Delivery $delivery): bool
    {
        return is_string($delivery->delivery_otp) && $delivery->delivery_otp !== '';
    }

    private function hasUsableCode(Delivery $delivery): bool
    {
        return $this->hasCode($delivery)
            && $delivery->delivery_otp_consumed_at === null
            && $delivery->delivery_otp_expires_at !== null
            && $delivery->delivery_otp_expires_at->isFuture()
            && (int) $delivery->delivery_otp_attempts < $this->maxAttempts();
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('logistics.delivery_otp.ttl_minutes', 30));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('logistics.delivery_otp.max_attempts', 5));
    }
}
