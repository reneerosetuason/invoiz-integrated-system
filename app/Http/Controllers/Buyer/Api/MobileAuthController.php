<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Mobile verification uses the same users/OTP state as the website. */
class MobileAuthController extends AuthController
{
    public function register(Request $request)
    {
        $this->normalizeEmail($request);
        $user = DB::transaction(function () use ($request) {
            $user = $this->createBuyer($request);
            $user->update(['approval_status' => 'pending']);
            $this->sendCode($user);

            return $user;
        });

        return response()->json([
            'message' => 'Enter the verification code sent to your email before logging in.',
            'verification_required' => true,
            'approval_required' => true,
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'approval_status']),
        ], 201);
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }
        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }
        if (! $user->email_verified_at) {
            if (! cache()->has('otp_resend_'.$user->id)) {
                $this->sendCode($user);
            }

            return response()->json([
                'message' => 'Please verify your email using the code we sent you.',
                'verification_required' => true,
            ], 403);
        }

        return parent::login($request);
    }

    public function verifyEmail(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email', 'code' => 'required|digits:6']);
        $approvalRequired = DB::transaction(function () use ($data) {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (! $user || ! $user->isActive() || ! $user->otp || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'No active verification code. Request a new code.']);
            }
            $key = 'otp_verify_attempts_'.$user->id;
            $attempts = (int) cache()->get($key, 0);
            if ($attempts >= 5) {
                throw new HttpException(429, 'Too many incorrect codes. Request a new code.');
            }
            if (! hash_equals((string) $user->otp, (string) $data['code'])) {
                cache()->put($key, $attempts + 1, now()->addMinutes(10));
                throw ValidationException::withMessages(['code' => 'Wrong verification code.']);
            }
            $user->update(['email_verified_at' => now(), 'otp' => null, 'otp_expires_at' => null]);
            cache()->forget($key);

            return $user->hasBlockedMarketplaceApproval();
        });

        return response()->json([
            'message' => $approvalRequired ? 'Email verified. Please wait for administrator approval.' : 'Email verified. You can now log in.',
            'approval_required' => $approvalRequired,
        ]);
    }

    public function resendCode(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isActive() || $user->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'No unverified active account found.']);
        }
        if (cache()->has('otp_resend_'.$user->id)) {
            throw new HttpException(429, 'Please wait a minute before requesting another code.');
        }
        $this->sendCode($user);

        return response()->json(['message' => 'A verification code was sent to your email.']);
    }

    private function sendCode(User $user): void
    {
        $code = $user->otp && $user->otp_expires_at?->isFuture()
            ? (string) $user->otp : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update(['otp' => $code, 'otp_expires_at' => now()->addMinutes(10)]);
        try {
            Mail::to($user->email)->send(new OtpCodeMail($code, $user->first_name ?? ''));
        } catch (\Throwable $e) {
            report($e);
            throw new HttpException(503, 'Verification email could not be sent. Please try again later.');
        }
        cache()->put('otp_resend_'.$user->id, true, 60);
        cache()->forget('otp_verify_attempts_'.$user->id);
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
    }
}
