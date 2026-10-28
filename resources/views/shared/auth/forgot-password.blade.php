@extends('shared.layouts.auth-split')

@section('title', 'Forgot Password — INVOIZ')
@section('brand_eyebrow', 'Buyer · Seller · Admin')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Life's short.</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">Shop fast.</span></span>
@endsection
@section('brand_plain', "Life's short. Shop fast.")
@section('brand_copy', 'Everyday goods from local sellers, delivered cash on delivery.')

@section('form_eyebrow', 'Reset password')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Forgot your password?</h1>
<p class="rv-r mt-2 text-sm text-muted" style="transition-delay:140ms">Enter your account email and we&rsquo;ll send a reset link.</p>

@if(session('success'))<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold text-teal-dark" style="border:1px solid rgba(15,76,92,.2);transition-delay:170ms">{{ session('success') }}</div>@endif
@endsection

@section('form')
<form method="POST" action="{{ url('/forgot-password') }}" data-guard class="rv-r mt-6 space-y-5" style="transition-delay:210ms" novalidate>
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
<button type="submit" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span class="inline-flex items-center gap-2">Send reset link <svg data-arrow class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg></span>
<svg data-spin class="hidden h-4 w-4 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
</button>
</form>
@endsection

@section('after_form')
<p class="rv-r mt-5 text-center text-sm text-muted" style="transition-delay:360ms">Remembered it? <a href="{{ url('/login') }}" class="lnk font-bold text-teal-dark">Log in</a></p>
@endsection
