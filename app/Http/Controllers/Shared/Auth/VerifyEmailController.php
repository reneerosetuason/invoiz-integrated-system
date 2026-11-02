<?php

namespace App\Http\Controllers\Shared\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified from the signed link.
     */
    public function verify(Request $request, $id, $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'User account not found.']);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect('/verify?email=' . urlencode($user->email))
                ->withErrors(['code' => 'The verification link is invalid or does not match this account.']);
        }

        if ($user->hasVerifiedEmail()) {
            if (! Auth::check()) {
                Auth::login($user);
            }
            return $this->redirectForRole($user)->with('success', 'Your email is already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->update(['otp' => null, 'otp_expires_at' => null]);

        if ($user->hasBlockedMarketplaceApproval()) {
            return redirect('/login')->with('success', 'Email verified. Please wait for administrator approval before signing in.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->isBuyer()) {
            $request->session()->put('buyer', [
                'id' => $user->id,
                'first_name' => $user->first_name ?? strtok($user->displayName(), ' '),
                'last_name' => $user->last_name ?? '',
                'email' => $user->email,
            ]);
        }

        return $this->redirectForRole($user)->with('success', 'Email verified successfully! Welcome, ' . ($user->first_name ?? $user->name) . '!');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user && $request->filled('email')) {
            $user = User::where('email', trim($request->input('email')))->first();
        }

        if (! $user) {
            return back()->withErrors(['email' => 'Account not found.']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect('/login')->with('success', 'Your email is already verified.');
        }

        $key = 'otp_resend_' . $user->id;
        if (cache()->has($key)) {
            return back()->with('error', 'Please wait a minute before requesting another verification email.');
        }
        cache()->put($key, true, 60);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update(['otp' => $otp, 'otp_expires_at' => now()->addMinutes(10)]);

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'A fresh verification link has been sent to ' . $user->email . '.');
    }

    protected function redirectForRole(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        if ($user->isLogisticsAdmin() || $user->isStaff() || in_array($user->role, ['admin', 'staff', 'logistics_staff'], true)) {
            return redirect('/logistics/dashboard');
        }
        if ($user->canSell()) {
            return redirect('/choose');
        }

        return redirect('/shop');
    }
}
