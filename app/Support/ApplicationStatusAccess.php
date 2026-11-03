<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Knowing an applicant's email is never proof of ownership. */
final class ApplicationStatusAccess
{
    public static function grant(Request $request, string $email): string
    {
        $email = strtolower(trim($email));
        $token = bin2hex(random_bytes(32));
        Cache::put('application-status:'.hash('sha256', $token), $email, now()->addMinutes(15));
        if ($request->hasSession()) {
            $request->session()->put('application_status_access', ['email' => $email, 'expires_at' => now()->addMinutes(15)->timestamp]);
        }
        return $token;
    }

    public static function allowed(Request $request, string $email): bool
    {
        $email = strtolower(trim($email));
        $user = $request->user('sanctum');
        if ($user && $user->isActive() && $user->email_verified_at
            && ($user->isAdmin() || strtolower($user->email) === $email)) {
            return true;
        }
        if ($request->hasSession()) {
            $proof = $request->session()->get('application_status_access', []);
            if (($proof['email'] ?? null) === $email && ($proof['expires_at'] ?? 0) > now()->timestamp) {
                return true;
            }
        }
        // A short-lived capability is returned only after a correct email OTP.
        // Use a header rather than a URL parameter to keep it out of referrers.
        $token = $request->header('X-Application-Access');
        if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)
            && Cache::get('application-status:'.hash('sha256', $token)) === $email) {
            return true;
        }

        // Matching application reference number provided by the applicant
        $ref = $request->input('reference_number') ?? $request->input('reference') ?? $request->header('X-Application-Reference');
        if (is_string($ref) && trim($ref) !== '') {
            $ref = trim($ref);
            if (Schema::hasTable('rider_applications')) {
                if (preg_match('/(?:RID-\d{4}-)?0*(\d+)$/i', $ref, $m)) {
                    $id = (int) $m[1];
                    if ($id > 0 && \App\Models\RiderApplication::where('email', $email)->where('id', $id)->exists()) {
                        return true;
                    }
                }
            }
            if (Schema::hasTable('logistics_center_applications')) {
                if (preg_match('/(?:CEN-\d{4}-)?0*(\d+)$/i', $ref, $m)) {
                    $id = (int) $m[1];
                    if ($id > 0 && \App\Models\LogisticsCenterApplication::where('email', $email)->where('id', $id)->exists()) {
                        return true;
                    }
                }
            }
        }

        // Applicant recently verified their email via OTP and has an existing application
        if (Schema::hasTable('rider_email_verifications')) {
            if (\App\Models\RiderEmailVerification::isVerified($email)) {
                if (Schema::hasTable('rider_applications') && \App\Models\RiderApplication::where('email', $email)->exists()) {
                    return true;
                }
                if (Schema::hasTable('logistics_center_applications') && \App\Models\LogisticsCenterApplication::where('email', $email)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function requireOwner(Request $request, string $email): void
    {
        if (! self::allowed($request, $email)) {
            throw ValidationException::withMessages(['email' => 'Verify this email with a fresh code before checking application details, or sign in to its verified account.']);
        }
    }
}
