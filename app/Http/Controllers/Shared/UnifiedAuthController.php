<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;

use App\Models\Cart;
use App\Models\Seller;
use App\Models\User;
use App\Mail\OtpCodeMail;
use App\Rules\PersonName;
use App\Support\NameNormalizer;
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
 * - Login sends admins to /admin/dashboard and buyer accounts to /shop.
 * - Accounts with an approved store can use both buyer and seller areas and
 *   choose which dashboard to enter at /choose.
 */
class UnifiedAuthController extends Controller
{
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_ATTEMPT_WINDOW_MINUTES = 10;

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('shared.auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('shared.auth.register', ['google' => session('google_signup')]);
    }

    public function register(Request $request)
    {
        // Trim the email before validating so padded addresses match the
        // stored canonical address instead of failing or duplicating.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => trim($request->input('email'))]);
        }

        // Normalize the phone before validating so +63 variants match the
        // stored 09 form for the duplicate check below. Empty stays null so
        // the optional field never collides on blank values.
        if (is_string($request->input('phone'))) {
            $p = trim($request->input('phone'));
            $request->merge(['phone' => $p === '' ? null : \App\Rules\PhilippinePhone::normalize($p)]);
        }

        $request->merge(NameNormalizer::cleanKeys($request->only(['first_name', 'last_name']), ['first_name', 'last_name']));

        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', new PersonName()],
            'last_name' => ['required', 'string', 'min:2', 'max:50', new PersonName()],
            'sex' => 'required|in:male,female,other',
            'birthday' => [
                'required', 'date', 'before:today',
                function ($attribute, $value, $fail) {
                    try {
                        if (\Carbon\Carbon::parse($value)->age < 18) {
                            $fail('You must be at least 18 years old to register.');
                        }
                    } catch (\Throwable $e) {
                        // The date rule above reports unparseable values.
                    }
                },
            ],
            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    $email = strtolower(trim((string) $value));
                    if (\App\Models\Rider::where('email', $email)->exists()
                        || \App\Models\RiderApplication::where('email', $email)->whereIn('status', ['pending', 'approved'])->exists()
                        || \App\Models\LogisticsCenterApplication::where('email', $email)->where('status', '!=', 'rejected')->exists()) {
                        $fail('This email is already registered with Logistics or Rider services and cannot be used for a buyer or seller account.');
                    }
                },
            ],
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                function ($attribute, $value, $fail) {
                    // One message per missing character type so the
                    // applicant knows exactly what to add.
                    $v = (string) $value;
                    if (! preg_match('/[A-Z]/', $v)) {
                        $fail('The password must contain at least one uppercase letter.');
                    }
                    if (! preg_match('/[a-z]/', $v)) {
                        $fail('The password must contain at least one lowercase letter.');
                    }
                    if (! preg_match('/[0-9]/', $v)) {
                        $fail('The password must contain at least one number.');
                    }
                    if (! preg_match('/[^A-Za-z0-9]/', $v)) {
                        $fail('The password must contain at least one special character (e.g., @, #, $, !).');
                    }
                },
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                function ($attribute, $value, $fail) {
                    // Optional field: empty stays valid. A filled value must
                    // be an 11-digit 09 mobile: digit strings of the wrong
                    // length name the length; anything else gets the invalid
                    // message. (+63 input is already normalized above.)
                    $v = trim((string) $value);
                    if ($v === '') {
                        return;
                    }
                    if (! preg_match('/^09\d{9}$/', $v)) {
                        if (preg_match('/^09\d+$/', $v)) {
                            $fail('The mobile number must be exactly 11 digits (e.g., 09171234567).');
                        } else {
                            $fail('Please enter a valid Philippine mobile number.');
                        }
                    }
                },
                'unique:users,phone',
            ],
            'terms' => 'accepted',
        ], [
            'phone.unique' => 'This mobile number is already in use. Please use a different number.',
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
        return view('shared.auth.verify', ['email' => $email]);
    }

    public function verifyCode(Request $request)
    {
        if (Auth::check() && Auth::user()->email_verified_at) {
            return $this->redirectForRole(Auth::user());
        }

        // Trim first so a padded address still identifies the account and
        // the code is checked against the stored canonical email.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => trim($request->input('email'))]);
        }

        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['code' => 'Account not found.'])->withInput();
        }
        if ($user->isDesignatedLogisticsAdminAccount()) {
            return back()->withErrors(['email' => 'This account uses the restricted Logistics authentication process.'])->withInput();
        }
        if (! $user->isActive()) {
            return back()->withErrors(['email' => 'Your account is not active.'])->withInput();
        }
        if ($user->email_verified_at) {
            return redirect('/login')->with('success', 'Already verified — you can log in.');
        }

        $attemptKey = $this->otpAttemptsKey($user);
        $attempts = (int) cache()->get($attemptKey, 0);

        if (! $user->otp) {
            return back()->withErrors(['code' => 'No active code — tap Resend for a new one.'])->withInput();
        }
        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'Code expired — tap Resend for a new one.'])->withInput();
        }
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['code' => 'Too many incorrect codes — tap Resend for a new code.'])->withInput();
        }
        if (! hash_equals((string) $user->otp, (string) $data['code'])) {
            $attempts++;
            cache()->put($attemptKey, $attempts, now()->addMinutes(self::OTP_ATTEMPT_WINDOW_MINUTES));

            if ($attempts >= self::OTP_MAX_ATTEMPTS) {
                return back()->withErrors(['code' => 'Too many incorrect codes — tap Resend for a new code.'])->withInput();
            }

            return back()->withErrors(['code' => 'Wrong code — check and try again.'])->withInput();
        }

        cache()->forget($attemptKey);

        $user->update(['email_verified_at' => now(), 'otp' => null, 'otp_expires_at' => null]);

        if ($user->hasBlockedMarketplaceApproval()) {
            return redirect('/login')->with('success', 'Email verified. Please wait for administrator approval before signing in.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $this->putBuyerSession($request, $user);

        return $this->redirectForRole($user)
            ->with('success', 'Email verified — welcome, ' . ($user->first_name ?? 'buyer') . '!');
    }

    private function otpAttemptsKey(User $user): string
    {
        return 'otp_verify_attempts_' . $user->id;
    }

    public function resendCode(Request $request)
    {
        // Trim first so the account lookup and the outgoing email both use
        // the clean address — a padded address must still receive its code.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => trim($request->input('email'))]);
        }

        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'Account not found.']);
        }
        if ($user->isDesignatedLogisticsAdminAccount()) {
            return back()->withErrors(['email' => 'This account uses the restricted Logistics authentication process.']);
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
        cache()->forget($this->otpAttemptsKey($user));
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

        if ($user->isDesignatedLogisticsAdminAccount()) {
            return back()->withErrors(['email' => 'This is a Logistics account. Please sign in on the Logistics login page.'])->withInput();
        }

        if ($user->hasBlockedMarketplaceApproval()) {
            return back()->withErrors(['email' => 'Your account is not approved.'])->withInput();
        }

        // Logistics and Rider accounts have their own dedicated login page / apps.
        if (in_array($user->role, ['logistics_admin', 'logistics_staff', 'staff', 'rider'], true) || $user->rider()->exists() || $user->isLogisticsAdmin() || $user->isStaff() || $user->isDesignatedLogisticsAdminAccount()) {
            return back()->withErrors(['email' => 'This account is registered for Logistics/Rider. Please use the Logistics/Rider portal.'])->withInput();
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
        abort_if(
            $user->isDesignatedLogisticsAdminAccount(),
            403,
            'The designated global Logistics Admin cannot use marketplace email verification.'
        );

        if (! $user->otp || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            $user->update([
                'otp' => $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'otp_expires_at' => now()->addMinutes(10),
            ]);
            cache()->forget($this->otpAttemptsKey($user));
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

        $redirectUri = config('services.google.redirect') ?: ($request->getSchemeAndHttpHost() . '/auth/google/callback');
        $request->session()->put('google_oauth_redirect_uri', $redirectUri);

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
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
        $redirectUri = $request->session()->pull('google_oauth_redirect_uri')
            ?: config('services.google.redirect')
            ?: ($request->getSchemeAndHttpHost() . '/auth/google/callback');

        try {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->input('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $redirectUri,
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
            if ($user->hasBlockedMarketplaceApproval()) {
                return redirect('/login')->withErrors(['email' => 'Your account is not approved.']);
            }
            // Logistics and Rider accounts have their own dedicated login page / apps.
            if (in_array($user->role, ['logistics_admin', 'logistics_staff', 'staff', 'rider'], true) || $user->rider()->exists() || $user->isLogisticsAdmin() || $user->isStaff() || $user->isDesignatedLogisticsAdminAccount()) {
                return redirect('/login')->withErrors(['email' => 'This account is registered for Logistics/Rider. Please use the Logistics/Rider portal.']);
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
        $request->session()->forget(['buyer', 'seller_id', 'checkout_single', 'checkout_seller_id', 'checkout_all', 'checkout_selected']);
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

        // Logistics company managers (`role=admin` without the platform
        // flag) stay inside their logistics company, never the platform.
        if ($user->isCompanyManager()) {
            return redirect()->intended('/logistics/dashboard');
        }

        $seller = Seller::where('user_id', $user->id)->first();

        if ($seller && ! $seller->isApproved()) {
            // Pending applications must not sign out an otherwise valid buyer account.
            if (strtolower((string) ($seller->approval_status ?? '')) === 'pending') {
                return redirect()->intended('/shop')->with('error', 'Your seller application is still pending approval.');
            }
        }

        if ($seller && $seller->isApproved()) {
            // Approval changes the single stored role to "seller", but the
            // account still has buyer access. Ask which side to enter first.
            session()->forget(['active_role', 'seller_id']);
            return redirect('/choose');
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
        if (! $user->isActive()) {
            Auth::logout();
            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }
        $seller = Seller::where('user_id', $user->id)->first();
        if (! $seller || ! $seller->isApproved()) {
            return $this->redirectForRole($user);
        }
        return view('shared.auth.choose', ['user' => $user, 'storeName' => $seller->display_name]);
    }

    public function chooseBuyer(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        if (! $user->isActive()) {
            Auth::logout();
            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }
        $request->session()->forget('seller_id');
        $request->session()->put('active_role', 'buyer');
        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name ?? strtok($user->displayName(), ' '),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ]);
        return redirect('/shop');
    }

    public function chooseSeller(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        if (! $user->isActive()) {
            Auth::logout();
            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }
        $seller = Seller::where('user_id', $user->id)->approved()->first();
        if (! $seller || ! $seller->isApproved()) {
            return redirect('/shop')->with('error', 'No approved seller store is linked to this email yet.');
        }
        $request->session()->put('active_role', 'seller');
        $request->session()->put('seller_id', $seller->id);
        return redirect('/seller/dashboard');
    }

    public function switchRole(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'in:buyer,seller'],
        ]);

        if (! Auth::check() || ! Auth::user()->canSell()) {
            return redirect('/shop')->with('error', 'Role switching is available only for approved sellers.');
        }

        return $data['role'] === 'seller'
            ? $this->chooseSeller($request)
            : $this->chooseBuyer($request);
    }
}
