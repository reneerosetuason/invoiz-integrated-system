<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->canSell()) {
            return session('active_role') === 'seller'
                ? redirect()->route('seller.dashboard')
                : redirect()->route('choose');
        }

        // Live marketplace stats for the brand panel (whole system, not per seller).
        $stats = [
            'products' => \App\Models\Product::count(),
            'orders'   => \App\Models\Order::count(),
            'sales'    => (float) \App\Models\Order::where('status', '!=', 'cancelled')->sum('total_amount'),
        ];

        return view('auth.seller-login', compact('stats'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Logistics accounts have their own dedicated login page.
            // (Explicit redirect: the session is invalidated below, which
            // would wipe the "previous URL" that back() relies on.)
            if (in_array($user->role, ['logistics_admin', 'logistics_staff'], true)) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect('/seller/login')->withErrors([
                    'email' => 'Logistics accounts must use the Logistics login page.',
                ])->onlyInput('email');
            }

            if (! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();

                return back()->withErrors([
                    'email' => 'Your account is not active. Please contact the administrator.',
                ])->onlyInput('email');
            }

            if (! $user->seller) {
                Auth::logout();
                $request->session()->invalidate();

                return back()->withErrors([
                    'email' => 'No seller account is linked to this email. Apply as a seller in the Invoiz app first.',
                ])->onlyInput('email');
            }

            if ($user->seller->approval_status === 'pending') {
                Auth::logout();
                $request->session()->invalidate();

                return back()->withErrors([
                    'email' => 'Your seller application is still pending administrator approval.',
                ])->onlyInput('email');
            }

            if (! $user->seller->isApproved()) {
                Auth::logout();
                $request->session()->invalidate();

                return back()->withErrors([
                    'email' => 'Your seller account is not active. Please contact the administrator.',
                ])->onlyInput('email');
            }

            // Seller approval accounts also have buyer access. Clear any old
            // role selection so the user chooses which dashboard to enter.
            $request->session()->forget(['active_role', 'seller_id', 'buyer']);

            return redirect()->route('choose');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
