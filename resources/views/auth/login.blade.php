@extends('layouts.auth-split')

@section('title', 'Login — INVOIZ')
@section('brand_eyebrow', 'Buyer · Seller · Admin')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Life's short.</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">Shop fast.</span></span>
@endsection
@section('brand_plain', "Life's short. Shop fast.")
@section('brand_copy', 'Everyday goods from local sellers, delivered cash on delivery.')

@section('form_eyebrow', 'Log in')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Welcome back</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Log in to continue to your account.</p>

@if($errors->any())
<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">
<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg><span>{{ $errors->first() }}</span></p>
</div>
@endif
@if(session('success'))<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold text-teal-dark" style="border:1px solid rgba(15,76,92,.2);transition-delay:170ms">{{ session('success') }}</div>@endif
@if(session('error'))<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">{{ session('error') }}</div>@endif
@endsection

@section('form')
<form method="POST" action="{{ url('/login') }}" id="login-form" data-guard class="rv-r mt-6 space-y-5 @if($errors->any()) shake-once @endif" style="transition-delay:210ms" novalidate>
@csrf
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Email address</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" autocomplete="email" inputmode="email" @if($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif class="@if($errors->has('email')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('email'))
<p id="email-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('email') }}</span></p>
@endif
</div>
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password">Password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
<input id="password" type="password" name="password" required placeholder="Enter your password" autocomplete="current-password" @if($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif class="@if($errors->has('password')) field-error @endif" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('password'))
<p id="password-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('password') }}</span></p>
@endif
</div>
<div class="flex items-center justify-between gap-3 !mt-5">
<label class="flex min-h-[44px] cursor-pointer items-center gap-2.5 text-sm font-medium text-ink">
<input type="checkbox" name="remember" value="1" class="peer sr-only">
<span class="flex h-5 w-5 items-center justify-center rounded-md bg-transparent transition peer-checked:border-teal-dark peer-checked:bg-teal-dark peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-teal-dark" style="border:1px solid rgba(15,76,92,.3)" aria-hidden="true">
<svg class="h-3.5 w-3.5 text-white opacity-0 transition peer-checked:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
</span>
Remember me
</label>
<a href="{{ url('/forgot-password') }}" class="text-sm font-medium text-teal-dark"><span class="lnk">Forgot password?</span></a>
</div>
<button type="submit" id="login-btn" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span id="login-btn-label" class="inline-flex items-center gap-2" data-arrow-wrap>Log in <svg data-arrow class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg></span>
<svg data-spin class="hidden h-4 w-4 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
</button>
</form>
@endsection

@section('after_form')
<div class="rv-r my-5 flex items-center gap-3 text-xs font-bold text-muted" style="transition-delay:280ms" aria-hidden="true"><span class="h-px flex-1" style="background:rgba(15,76,92,.12)"></span><span>OR</span><span class="h-px flex-1" style="background:rgba(15,76,92,.12)"></span></div>
<a href="{{ url('/auth/google') }}" class="rv-r flex w-full items-center justify-center gap-2.5 rounded-xl bg-white text-sm font-bold text-ink transition hover:border-teal" style="height:44px;border-radius:12px;border:1px solid rgba(15,76,92,.2);transition-delay:320ms">
<svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a7.16 7.16 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
Continue with Google
</a>

<p class="rv-r mt-5 text-center text-sm text-muted" style="transition-delay:360ms">New to INVOIZ? <a href="{{ url('/register') }}" class="lnk font-bold text-teal-dark">Create an account</a></p>
@endsection
