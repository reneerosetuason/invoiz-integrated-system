<?php

namespace App\Http\Controllers\Logistics\Auth;

use App\Http\Controllers\Logistics\CentralEntryController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Delivery;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('logistics.auth.login', [
            'stats' => [
                'activeRiders' => Rider::where('status', '!=', 'inactive')->count(),
                'deliveries' => Delivery::whereNull('archived_at')->count(),
                'completed' => Delivery::where('status', 'delivered')->count(),
            ],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->forget(['buyer', 'active_role', 'seller_id', 'url.intended']);

        cookie()->queue(cookie()->forget(Auth::guard('web')->getRecallerName()));

        $destination = CentralEntryController::destinationFor($request->user())
            ?? '/logistics/login';

        if (! $request->user()->hasVerifiedEmail()) {
            $user = $request->user();
            if (! $user->otp || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
                $user->update(['otp' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                    'otp_expires_at' => now()->addMinutes(10)]);
                cache()->forget('otp_verify_attempts_'.$user->id);
            }
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $error) {
                report($error);
                return redirect()->route('verification.notice', ['email' => $user->email])
                    ->with('error', 'Could not send the verification email. Please use Resend code to try again.');
            }
            if ($user->isDesignatedLogisticsAdminAccount()) return redirect()->route('logistics.verification.notice');
            return redirect()->route('verification.notice', ['email' => $user->email]);
        }
        return redirect($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/logistics/login');
    }
}
