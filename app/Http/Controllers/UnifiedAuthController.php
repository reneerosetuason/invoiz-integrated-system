<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Seller;
use App\Models\User;
use App\Mail\OtpCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

        return view('auth.register', ['google' => session('google_signup')]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'sex' => 'required|in:male,female,other',
            'birthday' => 'required|date|before:today',
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

        // Google-verified email completing the form: skip OTP, verified instantly.
        $viaGoogle = ($request->session()->get('google_signup.email') === $data['email']);

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
            'email_verified_at' => $viaGoogle ? now() : null,
            'otp' => $viaGoogle ? null : $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'otp_expires_at' => $viaGoogle ? null : now()->addMinutes(10),
        ]);

        try {
            if (class_exists(Cart::class)) {
                Cart::headerFor($user->id);
            }
        } catch (\Throwable $e) {
            // cart table may not exist yet — login must still work
        }

        if ($viaGoogle) {
            $request->session()->forget('google_signup');
            Auth::login($user);
            $request->session()->regenerate();
            $this->putBuyerSession($request, $user);
            return redirect()->intended('/shop')->with('success', 'Welcome, ' . $data['first_name'] . '! Signed up with Google.');
        }

        try {
            Mail::to($user->email)->send(new OtpCodeMail($otp, $data['first_name']));
            $notice = 'We sent a 6-digit verification code to ' . $user->email . '.';
        } catch (\Throwable $e) {
            \Log::warning('OTP mail failed: ' . $e->getMessage());
            $notice = 'Your account was created, but the email failed to send — use Resend below.';
        }

        // Verify email first (like Shopee/Lazada), then straight to buyer shop.
        return redirect('/verify?email=' . urlencode($user->email))->with('success', $notice);
    }

    public function showVerify(Request $request)
    {
        $email = $request->query('email', '');
        if (Auth::check() && Auth::user()->email_verified_at) {
            return $this->redirectForRole(Auth::user());
        }
        return view('auth.verify', ['email' => $email]);
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['code' => 'Account not found.'])->withInput();
        }
        if ($user->email_verified_at) {
            Auth::login($user);
            $request->session()->regenerate();
            $this->putBuyerSession($request, $user);
            return $this->redirectForRole($user);
        }
        if (! $user->otp || $user->otp !== $data['code']) {
            return back()->withErrors(['code' => 'Wrong code — check and try again.'])->withInput();
        }
        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'Code expired — tap Resend for a new one.'])->withInput();
        }

        $user->update(['email_verified_at' => now(), 'otp' => null, 'otp_expires_at' => null]);

        Auth::login($user);
        $request->session()->regenerate();
        $this->putBuyerSession($request, $user);

        return redirect()->intended('/shop')->with('success', 'Email verified — welcome, ' . ($user->first_name ?? 'buyer') . '!');
    }

    public function resendCode(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'Account not found.']);
        }
        if ($user->email_verified_at) {
            return redirect('/login')->with('success', 'Already verified — you can log in.');
        }
        // Throttle: 60 seconds between sends.
        $key = 'otp_resend_' . $user->id;
        if (cache()->has($key)) {
            return back()->with('error', 'Please wait a minute before requesting a new code.');
        }
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update(['otp' => $otp, 'otp_expires_at' => now()->addMinutes(10)]);
        cache()->put($key, true, 60);
        try {
            Mail::to($user->email)->send(new OtpCodeMail($otp, $user->first_name ?? ''));
        } catch (\Throwable $e) {
            \Log::warning('OTP resend failed: ' . $e->getMessage());
            return back()->with('error', 'Could not send email right now — try again in a bit.');
        }
        return back()->with('success', 'New code sent to ' . $user->email . '.');
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

        if (! $user->email_verified_at) {
            $this->sendOtp($user);
            return redirect('/verify?email=' . urlencode($user->email))
                ->with('success', 'Please verify your email first — we sent a fresh 6-digit code to ' . $user->email . '.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->putBuyerSession($request, $user);

        return $this->redirectForRole($user);
    }

    protected function putBuyerSession(Request $request, User $user): void
    {
        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name ?? strtok($user->displayName(), ' '),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ]);
    }

    /** Issue (or reuse) a 10-minute OTP and email it. */
    protected function sendOtp(User $user): void
    {
        if (! $user->otp || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            $user->update([
                'otp' => $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'otp_expires_at' => now()->addMinutes(10),
            ]);
        } else {
            $otp = $user->otp;
        }
        try {
            Mail::to($user->email)->send(new OtpCodeMail($otp, $user->first_name ?? ''));
        } catch (\Throwable $e) {
            \Log::warning('OTP mail failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Continue with Gmail (Google OAuth, no extra packages needed)
    // ---------------------------------------------------------------
    public function googleRedirect(Request $request)
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) {
            return redirect('/login')->withErrors(['email' => 'Continue with Google is not set up yet — ask the site owner to add the Google keys.']);
        }
        $state = bin2hex(random_bytes(16));
        $request->session()->put('google_oauth_state', $state);
        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);
        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    public function googleCallback(Request $request)
    {
        if ($request->input('state') !== $request->session()->pull('google_oauth_state')) {
            return redirect('/login')->withErrors(['email' => 'Google sign-in expired — please try again.']);
        }
        if (! $request->filled('code')) {
            return redirect('/login')->withErrors(['email' => 'Google sign-in was cancelled.']);
        }
        try {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->input('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ])->throw()->json();
            $info = Http::withToken($token['access_token'])
                ->get('https://www.googleapis.com/oauth2/v3/userinfo')->throw()->json();
        } catch (\Throwable $e) {
            \Log::warning('Google sign-in failed: ' . $e->getMessage());
            return redirect('/login')->withErrors(['email' => 'Google sign-in failed — please try again.']);
        }
        if (empty($info['email'])) {
            return redirect('/login')->withErrors(['email' => 'Google did not share an email address.']);
        }

        $user = User::where('email', $info['email'])->first();
        if ($user) {
            if (! $user->isActive()) {
                return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
            }
            // Google proved ownership of this email.
            $user->update(['email_verified_at' => $user->email_verified_at ?? now(), 'otp' => null, 'otp_expires_at' => null]);
        } else {
            // New user: prefill the register form (Google gives no sex /
            // birthday, which our accounts require) and finish sign-up there.
            $parts = preg_split('/\s+/', trim((string) ($info['name'] ?? '')), 2);
            $request->session()->put('google_signup', [
                'first_name' => $parts[0] ?? strtok($info['email'], '@'),
                'last_name' => $parts[1] ?? '',
                'email' => $info['email'],
            ]);
            return redirect('/register')->with('success', 'Almost done — complete your profile to finish Google sign-up.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->putBuyerSession($request, $user);

        return $this->redirectForRole($user);
    }

    public function logout(Request $request)
    {
        // Single logout: kill this account's sessions in every app
        // (integrated + exact seller app share the sessions table).
        if (Auth::check()) {
            try {
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', Auth::id())->delete();
            } catch (\Throwable $e) {
            }
        }
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

        // Pure sellers go straight to their dashboard — no choosing needed.
        if ($user->role === 'seller' && $seller && $seller->isApproved()) {
            session()->put('seller_id', $seller->id);
            return redirect()->intended('/seller/dashboard');
        }

        // Buyer-role accounts with an approved store use both sides,
        // so only they must choose first.
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
        // Pure sellers never see the chooser — straight to their dashboard.
        if ($user->role === 'seller') {
            session()->put('seller_id', $seller->id);
            return redirect('/seller/dashboard');
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
        // Straight to the seller center (exact original seller pages).
        return redirect()->intended('/seller/dashboard');
    }
}
