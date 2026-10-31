<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Mail\RiderEmailVerificationMail;
use App\Models\RiderEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email-verification OTP for rider applications (pre-submission gate).
 *
 * The plaintext code exists only inside the approval... request lifecycle:
 * generated -> hashed for storage -> passed in memory to the Mailable ->
 * discarded. It is never returned, logged, notified, or stored plaintext.
 */
class RiderEmailVerificationController extends Controller
{
    /**
     * Generate and email a fresh code, invalidating previous unconsumed
     * codes for the address. Shared by send and resend.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $email = strtolower(trim($validated['email']));

        $marketplaceUser = \App\Models\User::where('email', $email)
            ->whereIn('role', ['buyer', 'seller'])
            ->first();

        if ($marketplaceUser || \App\Models\Seller::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'This email is already registered as a Buyer or Seller on INVOIZ and cannot be used for a Rider account. Please use a separate email address.',
                'errors' => [
                    'email' => ['This email is already registered as a Buyer or Seller on INVOIZ and cannot be used for a Rider account.'],
                ],
            ], 422);
        }

        $recent = RiderEmailVerification::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($recent && $recent->last_sent_at
            && $recent->last_sent_at->diffInSeconds(now()) < RiderEmailVerification::RESEND_COOLDOWN_SECONDS) {
            $wait = RiderEmailVerification::RESEND_COOLDOWN_SECONDS
                - $recent->last_sent_at->diffInSeconds(now());

            return response()->json([
                'message' => "Please wait {$wait} seconds before requesting another code.",
            ], 429);
        }

        // Previous active OTP becomes invalid the moment a new one is made.
        RiderEmailVerification::where('email', $email)
            ->whereNull('consumed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        RiderEmailVerification::create([
            'email' => $email,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(RiderEmailVerification::CODE_TTL_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        try {
            Mail::to($email)->send(new RiderEmailVerificationMail(
                $email,
                trim($validated['name'] ?? '') !== '' ? trim($validated['name']) : 'there',
                $code,
            ));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to send the verification code. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code sent. Please check your email.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
        ]);

        $email = strtolower(trim($validated['email']));

        $record = RiderEmailVerification::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'No active verification code for this email. Please request a new code.',
            ], 422);
        }

        if ($record->isExpired()) {
            $record->delete();

            return response()->json([
                'message' => 'Verification code expired. Please request a new code.',
            ], 422);
        }

        if ($record->attempts >= RiderEmailVerification::MAX_ATTEMPTS) {
            $record->delete();

            return response()->json([
                'message' => 'Too many verification attempts. Please request a new code.',
            ], 429);
        }

        if (! Hash::check($validated['code'], $record->otp_hash)) {
            $record->increment('attempts');

            return response()->json([
                'message' => 'Invalid verification code. Please try again.',
                'errors' => ['code' => ['The verification code is incorrect.']],
            ], 422);
        }

        // Single-use: consumed codes can never verify again.
        $record->update(['consumed_at' => now()]);

        return response()->json([
            'message' => 'Email verified successfully.',
            'application_access_token' => \App\Support\ApplicationStatusAccess::grant($request, $email),
            'application_access_expires_in' => 900,
            'email' => $email,
        ]);
    }
}





