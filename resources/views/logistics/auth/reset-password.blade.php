@extends('shared.layouts.auth-split')

@section('title', 'Reset Password — Logistics Hub')
@section('wordmark_extra')<span class="mt-1 block text-[10px] font-bold uppercase text-teal-dark" style="letter-spacing:.22em">Logistics Hub</span>@endsection

@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Create a new</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">secure password.</span></span>
@endsection
@section('brand_plain', 'Create a new secure password.')
@section('brand_copy', 'Update your Logistics Hub credentials to regain access to your delivery dashboard, assignments, and fleet operations.')

@section('form_eyebrow', 'Logistics security')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Reset your password</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Enter your email and choose a strong new password for your account.</p>

@if(session('status') || session('success'))
<div class="rv-r mt-5 rounded-[14px] px-4 py-3 text-sm font-semibold" role="status" style="border:1px solid rgba(15,76,92,.25);background:rgba(234,244,243,.75);color:#0E4A57;transition-delay:170ms">
<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>{{ session('status') ?? session('success') }}</span></p>
</div>
@endif

@if($errors->any())
<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" aria-live="assertive" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">
<ul class="space-y-1">
@foreach($errors->all() as $error)
<li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg><span>{{ $error }}</span></li>
@endforeach
</ul>
</div>
@endif
@endsection

@section('form')
<form method="POST" action="/logistics/reset-password" class="rv-r mt-6 space-y-5" style="transition-delay:210ms" novalidate>
@csrf
<input type="hidden" name="token" value="{{ $request->route('token') }}">

<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Account email</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required placeholder="you@company.com" autocomplete="email" inputmode="email" class="@if($errors->has('email')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
</div>

<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password">New password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
<input id="password" type="password" name="password" required placeholder="New password" autocomplete="new-password" class="@if($errors->has('password')) field-error @endif" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
</div>

<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="password_confirmation">Confirm new password</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
<input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Confirm new password" autocomplete="new-password" class="@if($errors->has('password_confirmation')) field-error @endif" style="padding-right:2.75rem">
<button type="button" data-pw-toggle="password_confirmation" class="absolute right-0 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-lg text-teal-dark hover:bg-teal-pale" aria-label="Show password" aria-pressed="false">
<span data-eye><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/></svg></span>
<span data-eye-off class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 10.1A3 3 0 0010 13.9M9.9 5.9A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17.6 17.6 0 01-3.2 3.9M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.9-.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg></span>
</button>
</div>
<span class="bar" aria-hidden="true"></span>
</div>

<button type="submit" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span class="inline-flex items-center gap-2">Reset password <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
</button>
</form>

<script>
(function() {
  document.querySelectorAll('[data-pw-toggle]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var fieldId = btn.getAttribute('data-pw-toggle');
      var input = document.getElementById(fieldId);
      if (!input) return;
      var isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      var eye = btn.querySelector('[data-eye]');
      var eyeOff = btn.querySelector('[data-eye-off]');
      if (eye) eye.classList.toggle('hidden', isPassword);
      if (eyeOff) eyeOff.classList.toggle('hidden', !isPassword);
      btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
    });
  });
})();
</script>
@endsection

@section('after_form')
<div class="rv-r my-4 flex items-center gap-3 text-xs font-bold text-muted min-[900px]:my-5" style="transition-delay:280ms" aria-hidden="true"><span class="h-px flex-1" style="background:rgba(15,76,92,.12)"></span></div>
<div class="text-center">
<a href="{{ url('/logistics/login') }}" class="lnk text-sm font-semibold text-teal-dark inline-flex items-center gap-1.5">
<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
Back to Logistics login
</a>
</div>
@endsection
