<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Mail\RiderPasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Self-service rider password reset (public API, throttled).
 *
 * Uses Laravel's password broker (config/auth.php: expire 60 minutes,
 * throttle 60 seconds) over the password_reset_tokens table, so tokens
 * are cryptographically secure, hashed at rest, expiring, and single-use
 * (deleted on success). Plaintext tokens only ever travel inside the
 * reset email and are never stored or logged.
 *
 * Anti-enumeration: every outcome returns the identical generic message
 * and HTTP 200, whether the email belongs to an active rider, another
 * role, an inactive account, or nobody at all. Mail failures also stay
 * silent (reported, not surfaced).
 *
 * The existing Logistics web reset stays intentionally disabled and is
 * untouched by this controller.
 */
class RiderPasswordResetController extends Controller
{
    /**
     * Request a reset link/code for a rider email address.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::where('email', $email)->where('role', 'rider')->first();

        if ($user && $user->isActive()) {
            $this->sendResetLink($user);
        }

        return response()->json([
            'message' => 'If an active rider account exists for that email address, a password reset link has been sent.',
        ]);
    }

    /**
     * Consume a reset token and set a new password.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::where('email', $email)->where('role', 'rider')->first();

        // One generic failure for: unknown email, non-rider, inactive
        // account, wrong/expired/used token. Never reveal which one.
        if (! $user || ! $user->isActive()) {
            return $this->invalidToken();
        }

        $broker = Password::broker();
        if (! $broker->tokenExists($user, $validated['token'])) {
            return $this->invalidToken();
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        $broker->deleteToken($user);
        $user->tokens()->delete();

        event(new PasswordReset($user));

        return response()->json([
            'message' => 'Password reset successfully. You may now log in with your new password.',
        ]);
    }

    /**
     * Create a broker token (honoring the broker's resend throttle) and
     * email the deep link + printable code. Mail failures are reported
     * but never surfaced, preserving the generic response.
     */
    private function sendResetLink(User $user): void
    {
        $broker = Password::broker();

        $recent = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->where('created_at', '>', now()->subSeconds(config('auth.passwords.users.throttle', 60)))
            ->exists();
        if ($recent) {
            return;
        }

        $token = $broker->createToken($user);

        $deepLink = 'invoizrider://reset-password?token=' . urlencode($token)
            . '&email=' . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new RiderPasswordResetMail(
                riderName: $user->name,
                riderEmail: $user->email,
                resetToken: $token,
                resetDeepLink: $deepLink,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function invalidToken(): JsonResponse
    {
        return response()->json([
            'message' => 'This password reset link is invalid or has expired. Please request a new one.',
            'errors' => [
                'token' => ['This password reset link is invalid or has expired.'],
            ],
        ], 422);
    }
}
