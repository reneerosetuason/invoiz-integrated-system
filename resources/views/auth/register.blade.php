@extends('layouts.auth-split')

@section('title', 'Sign Up — INVOIZ')
@section('brand_eyebrow', 'New to Invoiz')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Shop local.</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">Pay on delivery.</span></span>
@endsection
@section('brand_plain', 'Shop local. Pay on delivery.')
@section('brand_copy', 'One account for shopping, tracking orders, and selling later.')

@section('form_eyebrow', 'Create account')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Create your account</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Always a <b>buyer</b> account — sell later from <a href="{{ url('/sell') }}" class="lnk font-bold text-teal-dark">/sell</a>.</p>

@if(!empty($google['email']))
<div class="rv-r mt-5 flex items-center gap-2.5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold text-teal-dark" style="border:1px solid rgba(15,76,92,.2);transition-delay:150ms">
<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
<span>Google-verified email — no code needed, just complete your profile.</span>
</div>
@endif

@if($errors->any())
<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">
<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg><span>{{ $errors->first() }}</span></p>
</div>
@endif
@endsection

@section('form')
<form method="POST" action="{{ url('/register') }}" id="register-form" data-guard class="rv-r mt-6 space-y-5 @if($errors->any()) shake-once @endif" style="transition-delay:210ms" novalidate>
@csrf
<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="first_name">First name</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
<input id="first_name" name="first_name" value="{{ old('first_name', $google['first_name'] ?? '') }}" required maxlength="50" placeholder="Juan" autocomplete="given-name" @if($errors->has('first_name')) aria-describedby="first_name-error" aria-invalid="true" @endif class="@if($errors->has('first_name')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('first_name'))
<p id="first_name-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('first_name') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="last_name">Last name</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
<input id="last_name" name="last_name" value="{{ old('last_name', $google['last_name'] ?? '') }}" required maxlength="50" placeholder="Dela Cruz" autocomplete="family-name" @if($errors->has('last_name')) aria-describedby="last_name-error" aria-invalid="true" @endif class="@if($errors->has('last_name')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('last_name'))
<p id="last_name-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('last_name') }}</span></p>
@endif
</div>
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Email address</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email', $google['email'] ?? '') }}" required placeholder="you@example.com" autocomplete="email" inputmode="email" @if(!empty($google['email'])) readonly @endif @if($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif class="@if($errors->has('email')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('email'))
<p id="email-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('email') }}</span></p>
@endif
</div>
<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="sex">Sex</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5c-4.5 0-7.5 3-7.5 7s3 7 7.5 7 7.5-3 7.5-7-3-7-7.5-7zm0 0v13"/></svg>
<select id="sex" name="sex" required @if($errors->has('sex')) aria-describedby="sex-error" aria-invalid="true" @endif class="@if($errors->has('sex')) field-error @endif"><option value="">Select...</option><option value="male" @selected(old('sex')==='male')>Male</option><option value="female" @selected(old('sex')==='female')>Female</option><option value="other" @selected(old('sex')==='other')>Other</option></select>
<svg class="pointer-events-none absolute right-1 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('sex'))
<p id="sex-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('sex') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="birthday">Birthday</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
<input id="birthday" type="date" name="birthday" value="{{ old('birthday') }}" required max="{{ date('Y-m-d', strtotime('-1 day')) }}" @if($errors->has('birthday')) aria-describedby="birthday-error" aria-invalid="true" @endif class="@if($errors->has('birthday')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
<p class="mt-1 text-[13px] text-muted">Must be a past date.</p>
@if($errors->has('birthday'))
<p id="birthday-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('birthday') }}</span></p>
@endif
</div>
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="phone">Phone <span class="font-normal text-muted">(optional)</span></label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.3a1 1 0 01.9.7l1.5 4a1 1 0 01-.3 1.1L8.1 10a16 16 0 006 6l1.2-2.3a1 1 0 011.1-.3l4 1.5a1 1 0 01.7.9V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z"/></svg>
<input id="phone" name="phone" value="{{ old('phone') }}" placeholder="09xx xxx xxxx" autocomplete="tel" inputmode="tel" @if($errors->has('phone')) aria-describedby="phone-error" aria-invalid="true" @endif class="@if($errors->has('phone')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('phone'))
<p id="phone-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('phone') }}</span></p>
@endif
</div>
<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password">Password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
<input id="password" type="password" name="password" required placeholder="Min. 8 characters" autocomplete="new-password" @if($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif class="@if($errors->has('password')) field-error @endif" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
<p class="mt-1 text-[13px] text-muted">Min. 8 characters, with uppercase, lowercase, number, and symbol.</p>
@if($errors->has('password'))
<p id="password-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('password') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password_confirmation">Confirm password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
<input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Repeat it" autocomplete="new-password" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password_confirmation" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
</div>
</div>
<label class="flex cursor-pointer items-start gap-2.5 text-[13px] leading-relaxed text-muted">
<input type="checkbox" name="terms" id="buyer-terms" value="1" {{ old('terms') ? 'checked' : '' }} class="mt-0.5 h-4 w-4 shrink-0 accent-[#0E4A57]">
<span>I agree to the <a href="{{ url('/help') }}" class="lnk font-bold text-teal-dark">Terms of Service</a> and <a href="{{ url('/help') }}" class="lnk font-bold text-teal-dark">Privacy Policy</a>.</span>
</label>
@if($errors->has('terms'))
<p id="terms-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('terms') }}</span></p>
@endif
<button type="submit" id="buyer-register-btn" class="press mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal disabled:opacity-60 disabled:cursor-not-allowed" style="height:44px;border-radius:12px" disabled>
<span class="inline-flex items-center gap-2">Create My Buyer Account <svg data-arrow class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg></span>
<svg data-spin class="hidden h-4 w-4 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
</button>
<script>
(function () {
var box = document.getElementById('buyer-terms');
var btn = document.getElementById('buyer-register-btn');
function sync() { if (btn) btn.disabled = !(box && box.checked); }
if (box && btn) { box.addEventListener('change', sync); sync(); }
})();
</script>
</form>
@endsection

@section('after_form')
<div class="rv-r my-5 flex items-center gap-3 text-xs font-bold text-muted" style="transition-delay:280ms" aria-hidden="true"><span class="h-px flex-1" style="background:rgba(15,76,92,.12)"></span><span>OR</span><span class="h-px flex-1" style="background:rgba(15,76,92,.12)"></span></div>
<a href="{{ url('/auth/google') }}" class="rv-r flex w-full items-center justify-center gap-2.5 rounded-xl bg-white text-sm font-bold text-ink transition hover:border-teal" style="height:44px;border-radius:12px;border:1px solid rgba(15,76,92,.2);transition-delay:320ms">
<svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
Continue with Google
</a>

<p class="rv-r mt-5 text-center text-sm text-muted" style="transition-delay:360ms">Already have an account? <a href="{{ url('/login') }}" class="lnk font-bold text-teal-dark">Log in</a></p>
@endsection

@section('right-scroll', 'min-[900px]:max-h-[100dvh] min-[900px]:overflow-y-auto min-[900px]:py-10')
