<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller {
  public function showLogin(){ return view('auth.login'); }
  public function showRegister(){ return view('auth.register', ['google' => session('google_signup')]); }

  private function nameParts($name){
    $parts = preg_split('/\s+/', trim($name), 2);
    return ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? ''];
  }

  private function setBuyerSession($r, $u){
    $np = $this->nameParts($u->name ?? $u->first_name.' '.$u->last_name);
    $r->session()->put('buyer',[
      'id'=>$u->id,
      'first_name'=>$np['first_name'],
      'last_name'=>$np['last_name'],
      'email'=>$u->email,
    ]);
  }

  public function login(Request $r){
    $r->validate(['email'=>'required|email','password'=>'required']);
    $u = User::where('email',$r->email)->first();
    if(!$u || !Hash::check($r->password, $u->password)) return back()->withErrors(['email'=>'Invalid email or password'])->withInput();
    if(!empty($u->status) && $u->status!=='active') return back()->withErrors(['email'=>'Your account is not active.'])->withInput();
    if(!$u->email_verified_at){
      $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $u->update(['otp'=>$otp,'otp_expires_at'=>now()->addMinutes(5)]);
      try{ \App\Services\PHPMailerService::sendOTP($u->email, $otp); } catch(\Throwable $e){}
      $r->session()->put('pending_login_id',$u->id);
      return redirect('/verify?email='.urlencode($u->email))->with('success','OTP sent to '.$u->email.' — enter code to complete login');
    }
    Auth::login($u);
    $this->setBuyerSession($r, $u);
    $np = $this->nameParts($u->name ?? '');
    return redirect('/')->with('success','Welcome back, '.$np['first_name'].'!');
  }

  public function register(Request $r){
    $r->validate([
      'first_name'=>'required|string|max:100',
      'last_name'=>'required|string|max:100',
      'sex'=>'required|in:male,female,other',
      'birthday'=>'required|date|before:today',
      'email'=>'required|email|max:150|unique:users,email',
      'password'=>'required|string|min:8|confirmed',
      'phone'=>'nullable|string|max:30',
      'address_line'=>'nullable|string|max:255',
    ]);
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $age = \Carbon\Carbon::parse($r->birthday)->diffInYears(now());
    // Google-verified email completing sign-up: skip OTP, verified instantly.
    $viaGoogle = ($r->session()->get('google_signup.email') === $r->email);
    $u = User::create([
      'first_name'=>$r->first_name,
      'last_name'=>$r->last_name,
      'sex'=>$r->sex,
      'email'=>$r->email,
      'password'=>Hash::make($r->password),
      'phone'=>$r->phone ?? '',
      'birthday'=>$r->birthday,
      'age'=>$age,
      'address_line'=>$r->address_line,
      'approval_status'=>'approved',
      'role'=>'buyer',
      'status'=>'active',
      'otp'=>$viaGoogle ? null : $otp,
      'otp_expires_at'=>$viaGoogle ? null : now()->addMinutes(5),
      'email_verified_at'=>$viaGoogle ? now() : null,
    ]);
    Cart::headerFor($u->id);
    if($viaGoogle){
      $r->session()->forget('google_signup');
      Auth::login($u);
      $this->setBuyerSession($r, $u);
      return redirect('/')->with('success','Welcome, '.$r->first_name.'! Signed up with Google.');
    }
    try{ \App\Services\PHPMailerService::sendOTP($u->email, $otp); } catch(\Throwable $e){}
    return redirect('/verify?email='.urlencode($u->email))->with('success','We sent a 6-digit code to '.$u->email);
  }

  public function showVerify(Request $r){
    $email = $r->query('email') ?? $r->session()->get('verify_email', 'your email');
    return view('auth.verify',['email'=>$email]);
  }

  public function resend(Request $r){
    $email = $r->query('email') ?? $r->input('email');
    $u = User::where('email',$email)->first();
    if(!$u) return back()->withErrors(['email'=>'Email not found']);
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $u->update(['otp'=>$otp,'otp_expires_at'=>now()->addMinutes(5)]);
    try{ \App\Services\PHPMailerService::sendOTP($u->email, $otp); } catch(\Throwable $e){ return back()->with('error','Failed to send OTP: '.$e->getMessage()); }
    return back()->with('success','New code sent to '.$email)->with('resent', true);
  }

  public function verifyCode(Request $r){
    $r->validate(['code'=>'required|digits:6','email'=>'required']);
    $u = User::where('email',$r->email)->first();
    if(!$u) return back()->withErrors(['code'=>'Email not found']);
    if(!$u->otp || $u->otp !== $r->code) return back()->withErrors(['code'=>'Wrong OTP'])->withInput();
    if($u->otp_expires_at && $u->otp_expires_at->isPast()) return back()->withErrors(['code'=>'OTP expired — click Resend OTP'])->withInput();
    $u->update(['email_verified_at'=>now(),'otp'=>null,'otp_expires_at'=>null]);
    if($r->session()->has('pending_login_id')){
      $uid = $r->session()->pull('pending_login_id');
      $uu = User::find($uid);
      if($uu){
        Auth::login($uu);
        $this->setBuyerSession($r, $uu);
        $np = $this->nameParts($uu->name ?? '');
        return redirect('/')->with('success','Verified — welcome, '.$np['first_name'].'!');
      }
    }
    return redirect('/login')->with('success','Email verified — you can now log in');
  }

  public function googleRedirect(){
    if(!config('services.google.client_id') || !config('services.google.client_secret')){
      return redirect('/login')->withErrors(['email' => 'Continue with Google is not set up yet — please log in with email instead.']);
    }
    return Socialite::driver('google')->redirect();
  }

  public function googleCallback(Request $r){
    try {
      $g = Socialite::driver('google')->user();
    } catch(\Throwable $e){
      \Log::warning('Google sign-in failed: '.$e->getMessage());
      return redirect('/login')->withErrors(['email' => 'Google sign-in failed — please try again or use email login.']);
    }
    $email = $g->getEmail();
    if(!$email){
      return redirect('/login')->withErrors(['email' => 'Google did not share an email address — please register with email instead.']);
    }

    // Existing account with the same email → Google proved ownership, log in.
    $u = User::where('email', $email)->first();
    if($u){
      if($u->status !== 'active'){
        return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
      }
      $u->update([
        'email_verified_at' => $u->email_verified_at ?? now(),
        'otp' => null,
        'otp_expires_at' => null,
      ]);
      Auth::login($u);
      $this->setBuyerSession($r, $u);
      Cart::headerFor($u->id);
      return redirect('/')->with('success','Signed in with Google — welcome back!');
    }

    // New user → prefill the register form (Google gives no sex/birthday,
    // which our accounts require), then finish sign-up there.
    $parts = preg_split('/\s+/', trim((string)$g->getName()), 2);
    $r->session()->put('google_signup', [
      'first_name' => $parts[0] ?? '',
      'last_name' => $parts[1] ?? '',
      'email' => $email,
    ]);
    return redirect('/register')->with('success','Almost done — complete your profile to finish Google sign-up.');
  }

  public function logout(Request $r){
    Auth::logout();
    $r->session()->forget('buyer');
    $r->session()->forget('checkout_single');
    $r->session()->invalidate();
    $r->session()->regenerateToken();
    return redirect('/')->with('success','Logged out successfully');
  }
}
