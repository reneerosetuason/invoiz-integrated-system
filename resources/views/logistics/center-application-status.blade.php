@extends('layouts.auth-split')

@section('title', 'Check a Logistics Center Application Status')
@section('back_link')
<a href="/logistics/login" class="absolute right-4 top-5 z-10 inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink sm:right-8 sm:top-7"><span aria-hidden="true">←</span> Back to Login</a>
@endsection
@section('brand_eyebrow', 'Application status')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Track your</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">application.</span></span>
@endsection
@section('brand_plain', 'Track your application.')
@section('brand_copy', 'Enter the email address you used when applying to see the current status of your Logistics Center application.')

@section('left_bottom')
<p class="hidden text-xs text-muted min-[900px]:block">INVOIZ Logistics · v1.0</p>
@endsection

@section('form_eyebrow', 'Check status')
@section('form_head')
<h1 class="rv-r font-display mt-2 tracking-[-0.02em]" style="font-weight:560;font-size:clamp(1.6rem,2.4vw,1.875rem);line-height:1.1;transition-delay:70ms">Application status</h1>
@endsection

@section('form')
@if ($errors->any())
<div class="rv-r mt-5 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" aria-live="assertive" style="border:1px solid rgba(180,35,24,.35);color:#B42318;transition-delay:170ms">
<p class="font-semibold">We couldn&rsquo;t check your status:</p>
<ul class="mt-1.5 list-disc list-inside space-y-1">
@foreach ($errors->all() as $error)
<li class="break-words">{{ $error }}</li>
@endforeach
</ul>
</div>
@endif

<form method="POST" action="{{ route('center-application.status.check') }}" id="status-check-form" data-guard class="rv-r mt-6 space-y-5" style="transition-delay:210ms" novalidate>
@csrf
<div class="ufield">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Email address <span class="font-normal text-muted">(required)</span></label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" required maxlength="255" autocomplete="email" inputmode="email" placeholder="you@example.com" @error('email') aria-describedby="status_email_error" aria-invalid="true" @enderror class="@error('email') field-error @enderror">
</div>
<span class="bar" aria-hidden="true"></span>
<p id="status_email_hint" class="mt-1 text-[13px] text-muted">The email address you used when you applied.</p>
@error('email')<p id="status_email_error" class="err-msg mt-1.5 flex items-start gap-1.5 text-[13px] font-medium" style="color:#B42318"><svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>{{ $message }}</span></p>@enderror
</div>
<button type="submit" class="press flex w-full items-center justify-center gap-2 rounded-xl bg-teal-dark text-sm font-bold text-white transition hover:bg-teal" style="height:44px;border-radius:12px">
<span class="inline-flex items-center gap-2"><svg data-arrow class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>Check Status</span>
<svg data-spin class="hidden h-4 w-4 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
</button>
</form>

@if (($searched ?? false) && $application === null)
<div class="rv-r mt-6 rounded-[14px] bg-white px-4 py-4" role="status" aria-live="polite" style="border:1px solid rgba(15,76,92,.12);transition-delay:260ms">
<div class="flex items-start gap-3">
<svg class="mt-0.5 h-5 w-5 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
<div class="min-w-0">
<p class="text-[15px] font-bold">No application found</p>
<p class="mt-1 text-sm text-muted break-words">We could not find a Logistics Center application for <span class="font-semibold text-ink break-all">{{ $email }}</span>. Double-check the email address you registered with.</p>
</div>
</div>
</div>
@endif

@if (($searched ?? false) && $application !== null)
<div class="rv-r mt-6" role="status" aria-live="polite" aria-label="Application status result" style="transition-delay:260ms">
@php
$st = $application->status;
$stPill = $st === 'approved'
    ? 'background:#EAF4F3;color:#0E4A57;'
    : ($st === 'rejected'
        ? 'background:#FDECEC;color:#B42318;'
        : 'background:#F1EFE9;color:#1B1B1E;');
$stNotes = [
    'pending' => 'Your application is currently being reviewed. We will update this page once a decision has been made.',
    'approved' => 'Your Logistics Center has been approved. Login credentials were sent to your registered email address.',
    'rejected' => 'Your application was not approved at this time. The reason was emailed to you - you may correct the details and submit a new application.',
];
$stNote = $stNotes[$st] ?? null;
@endphp
<p><span class="inline-block rounded-full px-3 py-1 text-[13px] font-bold" style="border:1px solid rgba(15,76,92,.12);{{ $stPill }}">{{ ucwords(str_replace('_', ' ', $st)) }}</span></p>
<h2 class="font-display mt-3 tracking-[-0.02em] break-words" style="font-weight:560;font-size:clamp(1.4rem,2.4vw,1.75rem);line-height:1.15">{{ $application->business_name }}</h2>
<p class="mt-1 text-[15px] text-muted break-words">{{ $application->displayOwnerName() }}</p>
@if($stNote)
<p class="mt-3 text-sm leading-relaxed text-muted">{{ $stNote }}</p>
@endif
@if($st === 'rejected' && ($application->notes ?? '') !== '')
<div class="mt-4 rounded-[14px] bg-white px-4 py-4" style="border:1px solid rgba(180,35,24,.35)">
<p class="text-[13px] font-bold uppercase tracking-wider" style="color:#B42318">Reason</p>
<p class="mt-1 text-sm leading-relaxed text-ink break-words">{{ $application->notes }}</p>
</div>
@endif
<dl class="mt-5">
<div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-3" style="border-top:1px solid rgba(15,76,92,.12)"><dt class="w-36 flex-none text-[13px] font-medium text-muted">Submitted</dt><dd class="min-w-0 break-words text-sm font-semibold">{{ $application->created_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
<div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-3" style="border-top:1px solid rgba(15,76,92,.12)"><dt class="w-36 flex-none text-[13px] font-medium text-muted">Reviewed</dt><dd class="min-w-0 break-words text-sm font-semibold">{{ $application->reviewed_at?->format('M d, Y h:i A') ?? 'Not yet' }}</dd></div>
<div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-3" style="border-top:1px solid rgba(15,76,92,.12);border-bottom:1px solid rgba(15,76,92,.12)"><dt class="w-36 flex-none text-[13px] font-medium text-muted">Provisioned</dt><dd class="min-w-0 break-words text-sm font-semibold">{{ $application->provisioned_at?->format('M d, Y h:i A') ?? 'Not yet' }}</dd></div>
</dl>
</div>
@endif
@endsection

@section('after_form')
<p class="rv-r mt-5 text-center text-sm text-muted" style="transition-delay:360ms">Haven&rsquo;t applied yet? <a href="{{ route('center-application.apply') }}" class="lnk font-bold text-teal-dark">Open a Logistics Center</a></p>
@endsection
