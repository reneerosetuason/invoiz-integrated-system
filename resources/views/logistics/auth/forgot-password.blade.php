@extends('shared.layouts.auth-split')

@section('title', 'Forgot Password — Logistics Hub')
@section('wordmark_extra')<span class="mt-1 block text-[10px] font-bold uppercase text-teal-dark" style="letter-spacing:.22em">Logistics Hub</span>@endsection

@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Secure access</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">to your hub.</span></span>
@endsection
@section('brand_plain', 'Secure access to your hub.')
@section('brand_copy', 'Reset your Logistics Hub credentials quickly and securely to keep operations moving without interruption.')

@section('form_eyebrow', 'Logistics password recovery')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Forgot password?</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Enter the email associated with your Logistics Hub account and we'll send you a password reset link.</p>

@if(session('status') || session('success'))
<div class="rv-r mt-5 rounded-[14px] px-4 py-3 text-sm font-semibold" role="status" style="border:1px solid rgba(15,76,92,.25);background:rgba(234,244,243,.75);color:#0E4A57;transition-delay:170ms">
<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>{{ session('status') ?? session('success') }}</span></p>
</div>
@endif

@if($errors->has('email'))
<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" aria-live="assertive" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">
<p class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg><span>{{ $errors->first('email') }}</span></p>
</div>
@endif
@endsection

@section('form')
<form method="POST" action="/logistics/forgot-password" class="rv-r mt-6 space-y-5" style="transition-delay:210ms" novalidate>
@csrf
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Account email</label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@company.com" autocomplete="email" inputmode="email" autofocus @if($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif class="@if($errors->has('email')) field-error @endif">
</div>
<span class="bar" aria-hidden="true"></span>
@if($errors->has('email'))
<p id="email-error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $errors->first('email') }}</span></p>
@endif
</div>

<button type="submit" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span class="inline-flex items-center gap-2">Send reset link <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg></span>
</button>
</form>
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
