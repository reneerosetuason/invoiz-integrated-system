@extends('shared.layouts.auth-split')

@section('title', 'Reset Password — INVOIZ')
@section('brand_eyebrow', 'Buyer · Seller · Admin')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Life's short.</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">Shop fast.</span></span>
@endsection
@section('brand_plain', "Life's short. Shop fast.")
@section('brand_copy', 'Everyday goods from local sellers, delivered cash on delivery.')

@section('form_eyebrow', 'Reset password')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Choose a new password</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Enter your account email and pick a new password.</p>
@endsection

@section('form')
<form method="POST" action="{{ url('/reset-password') }}" data-guard class="rv-r mt-6 space-y-5" style="transition-delay:210ms" novalidate>
@csrf
<input type="hidden" name="token" value="{{ $token ?? '' }}">
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Email address</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" required placeholder="you@example.com" autocomplete="email" inputmode="email" @if($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif class="@if($errors->has('email')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('email'))
<p id="email-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('email') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password">New password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
<input id="password" type="password" name="password" required placeholder="Min. 8 characters" autocomplete="new-password" @if($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif class="@if($errors->has('password')) field-error @endif" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
<p class="mt-1 text-[13px] text-muted">At least 8 characters.</p>
@if($errors->has('password'))
<p id="password-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('password') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password_confirmation">Confirm new password</label>
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
<button type="submit" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span class="inline-flex items-center gap-2">Reset password <svg data-arrow class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg></span>
<svg data-spin class="hidden h-4 w-4 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
</button>
</form>
@endsection

@section('after_form')
<p class="rv-r mt-5 text-center text-sm text-muted" style="transition-delay:360ms">Remembered it? <a href="{{ url('/login') }}" class="lnk font-bold text-teal-dark">Log in</a></p>
@endsection
