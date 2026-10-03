<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Single unified auth for the integrated INVOIZ marketplace.
 *
 * - Register ALWAYS creates a buyer account and sends the user
 *   straight to the buyer shop (/shop).
 * - Login inspects role and redirects:
 *     admin  -> /admin/dashboard
 *     seller-only -> /seller/dashboard
 *     buyer-only  -> /shop
 *     BOTH buyer+sellers (approved store linked) -> /choose
 *       (user picks Continue as Buyer or Continue as Seller)
 */
class UnifiedAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'sex' => 'nullable|in:male,female,other',
            'birthday' => 'nullable|date|before:today',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:30',
        ]);

        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        $age = null;
        if (! empty($data['birthday'])) {
            try {
                $age = \Carbon\Carbon::parse($data['birthday'])->diffInYears(now());
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $user = User::create([
            'name' => $fullName,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'sex' => $data['sex'] ?? null,
            'birthday' => $data['birthday'] ?? null,
            'age' => $age,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? '',
            // Buyer defaults — registration is ALWAYS a buyer account.
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        try {
            if (class_exists(Cart::class)) {
                Cart::headerFor($user->id);
            }
        } catch (\Throwable $e) {
            // cart table may not exist yet — login must still work
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ]);

        // Straight to buyer account, like Shopee/Lazada.
        return redirect()->intended('/shop')->with('success', 'Welcome, ' . $data['first_name'] . '! You are signed in as a buyer.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        if (! $user->isActive()) {
            return back()->withErrors(['email' => 'Your account is not active.'])->withInput();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name ?? strtok($user->displayName(), ' '),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ]);

        return $this->redirectForRole($user);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->forget(['buyer', 'seller_id', 'checkout_single', 'checkout_seller_id', 'checkout_all']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Logged out successfully.');
    }

    /**
     * Role-based landing after login.
     */
    public function redirectForRole(User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->intended('/admin/dashboard');
        }

        $seller = Seller::where('user_id', $user->id)->first();

        if ($seller && method_exists($seller, 'isApproved') && ! $seller->isApproved()) {
            // Keep behaviour of original seller app: pending sellers cannot enter.
            if (strtolower((string) ($seller->approval_status ?? '')) === 'pending') {
                Auth::logout();
                return redirect('/login')->withErrors(['email' => 'Your seller application is still pending approval.']);
            }
        }

        // Both buyer + approved seller on one login: user must choose first.
        if ($seller && $seller->isApproved()) {
            return redirect()->intended('/choose');
        }

        if ($user->role === 'seller') {
            // Seller role but no seller row yet -> send to buyer shop with notice.
            return redirect()->intended('/shop')->with('error', 'No approved seller store is linked to this email yet.');
        }

        // Buyer, rider, and everything else -> buyer shop.
        return redirect()->intended('/shop');
    }

    /**
     * Choice screen for logins that are both buyer and seller.
     */
    public function showChoose()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller || ! $seller->isApproved()) {
            return $this->redirectForRole($user);
        }
        return view('auth.choose', ['user' => $user, 'storeName' => $seller->display_name]);
    }

    public function chooseBuyer()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }
        $request = request();
        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name ?? strtok($user->displayName(), ' '),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ]);
        return redirect()->intended('/shop');
    }

    public function chooseSeller()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }
        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller || ! $seller->isApproved()) {
            return redirect('/shop')->with('error', 'No approved seller store is linked to this email yet.');
        }
        session()->put('seller_id', $seller->id);
        return redirect()->intended('/seller/dashboard');
    }
}
