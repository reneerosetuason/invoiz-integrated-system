@extends('layouts.website')
@section('content')
@php
  $nameParts = preg_split('/\s+/', trim($user->displayName()), 2);
  $firstName = $user->first_name ?: ($nameParts[0] ?? '');
  $lastName = $user->last_name ?: ($nameParts[1] ?? '');
  $displayName = trim($firstName.' '.$lastName);
  $profilePictureUrl = $user->profile_picture ? asset('storage/'.ltrim($user->profile_picture, '/')) : null;
  $accountStatus = $user->approval_status ?? $user->account_status ?? 'active';
  $initials = strtoupper(substr($firstName, 0, 1).substr($lastName, 0, 1)) ?: 'B';
  $isActive = strtolower((string) $accountStatus) === 'active';
@endphp
<style>
  .buyer-profile {
    --profile-paper: #FBF9F6;
    --profile-mist: #EAF1F0;
    --profile-ink: #1B1B1E;
    --profile-muted: #56696B;
    --profile-error: #8F3F38;
    width: min(100%, 960px);
    margin: 0 auto;
    padding: 28px 24px 48px;
    box-sizing: border-box;
    background: var(--profile-paper);
    color: var(--profile-ink);
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; /* taste-skill-ignore: Inter is the buyer site's loaded brand text face */
  }
  .buyer-profile *, .buyer-profile *::before, .buyer-profile *::after { box-sizing: border-box; }
  .buyer-profile h1, .buyer-profile h2, .buyer-profile h3, .buyer-profile p { margin-top: 0; }
  .buyer-profile h1, .buyer-profile h2, .buyer-profile h3 {
    color: var(--primary-dark, #0E4A57);
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-weight: 560;
  }
  .profile-header h1 { margin-bottom: 16px; font-size: 28px; line-height: 1.1; letter-spacing: -.02em; }
  .profile-identity { display: flex; align-items: center; gap: 16px; min-width: 0; }
  .profile-avatar {
    display: grid;
    place-items: center;
    width: 72px;
    height: 72px;
    flex: 0 0 72px;
    overflow: hidden;
    border-radius: 50%;
    background: var(--profile-mist);
    color: var(--primary-dark, #0E4A57);
    font-size: 18px;
    font-weight: 700;
  }
  .profile-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
  .profile-identity-copy { min-width: 0; }
  .profile-identity-name { margin: 0; color: var(--primary-dark, #0E4A57); font-family: 'Fraunces', Georgia, serif; font-size: 23px; font-weight: 560; line-height: 1.2; overflow-wrap: anywhere; }
  .profile-identity-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 7px; }
  .profile-email { max-width: 100%; color: var(--profile-muted); font-size: 13px; overflow-wrap: anywhere; }
  .profile-chip { display: inline-flex; align-items: center; gap: 7px; min-height: 24px; padding: 3px 9px; border-radius: 999px; background: var(--profile-mist); color: var(--primary-dark, #0E4A57); font-size: 11.5px; font-weight: 600; line-height: 1.2; }
  .profile-state-dot { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 50%; background: rgba(14,74,87,.55); }
  .profile-state-dot.is-active { background: var(--primary-dark, #0E4A57); }
  .profile-index {
    position: sticky;
    top: 60px;
    z-index: 20;
    display: flex;
    gap: 24px;
    width: 100%;
    margin: 18px 0 8px;
    padding: 4px 0;
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    background: var(--profile-paper);
  }
  .profile-index::-webkit-scrollbar { display: none; }
  .profile-index a {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    flex: 0 0 auto;
    scroll-snap-align: start;
    color: var(--primary-dark, #0E4A57);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
  }
  .profile-index a::after {
    position: absolute;
    right: 0;
    bottom: 4px;
    left: 0;
    height: 2px;
    background: var(--primary-dark, #0E4A57);
    content: '';
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .2s ease;
  }
  .profile-index a:hover::after, .profile-index a:focus-visible::after { transform: scaleX(1); }
  .buyer-profile a:focus-visible, .buyer-profile button:focus-visible, .buyer-profile input:focus-visible, .buyer-profile select:focus-visible, .buyer-profile summary:focus-visible {
    outline: 2px solid var(--primary-dark, #0E4A57);
    outline-offset: 3px;
  }
  .profile-messages { display: grid; gap: 10px; margin: 12px 0 0; }
  .profile-message { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; color: var(--primary-dark, #0E4A57); font-size: 13px; line-height: 1.5; }
  .profile-message-success { background: var(--profile-mist); }
  .profile-message-error { padding-left: 0; color: var(--profile-error); }
  .profile-message svg, .profile-error svg { width: 16px; height: 16px; flex: 0 0 16px; margin-top: 1px; fill: none; stroke: currentColor; stroke-width: 1.6; }
  .profile-section {
    display: grid;
    grid-template-columns: 240px minmax(0, 1fr);
    gap: 40px;
    align-items: start;
    min-width: 0;
    padding: 60px 0;
    scroll-margin-top: 132px;
  }
  .profile-section--first { padding-top: 32px; }
  .profile-copy { min-width: 0; }
  .profile-copy h2 { margin: 0; font-size: 20px; line-height: 1.2; letter-spacing: -.01em; }
  .profile-copy p { max-width: 32ch; margin: 11px 0 0; color: var(--profile-muted); font-size: 14px; line-height: 1.65; }
  .profile-body { min-width: 0; }
  .profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 18px; min-width: 0; }
  .profile-password-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; min-width: 0; }
  .profile-field { position: relative; min-width: 0; padding-bottom: 1px; }
  .profile-field::after { position: absolute; right: 0; bottom: 0; left: 0; height: 2px; background: var(--primary-dark, #0E4A57); content: ''; transform: scaleX(0); transform-origin: left; transition: transform .2s ease; }
  .profile-field:focus-within::after { transform: scaleX(1); }
  .profile-phone-field::after { bottom: calc(100% - 68px); }
  .profile-field > label, .profile-field > .profile-label { display: block; color: var(--profile-ink); font-size: 13px !important; font-weight: 600 !important; line-height: 1.4; }
  .profile-input {
    display: block;
    width: 100%;
    min-width: 0;
    height: 44px;
    margin-top: 5px;
    padding: 9px 0;
    border: 0;
    border-bottom: 1px solid rgba(15,76,92,.22); /* taste-skill-ignore: underline on a real form control */
    border-radius: 0;
    background: transparent;
    color: var(--profile-ink);
    font: inherit;
    font-size: 14px;
    line-height: 1.4;
  }
  .profile-input::placeholder { color: #637477; opacity: 1; }
  .profile-input:disabled { color: #667477; opacity: 1; }
  .profile-helper { display: block; margin-top: 6px; color: var(--profile-muted); font-size: 12.5px; line-height: 1.5; }
  .profile-default-checkbox { min-height: 44px; cursor: pointer; }
  .profile-default-checkbox input { width: 18px; height: 18px; flex: 0 0 18px; margin: 0; accent-color: var(--primary-dark, #0E4A57); }
  .profile-photo-field::after { display: none; }
  .profile-photo-picker { display: flex; align-items: center; gap: 12px; min-height: 48px; margin-top: 5px; }
  .profile-photo-thumb { display: grid; place-items: center; width: 48px; height: 48px; flex: 0 0 48px; overflow: hidden; border-radius: 50%; background: var(--profile-mist); color: var(--primary-dark, #0E4A57); font-size: 13px; font-weight: 700; }
  .profile-photo-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
  .profile-photo-status { min-height: 18px; margin-top: 6px; color: var(--profile-muted); font-size: 12px; line-height: 1.5; }
  .profile-file-input { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
  .profile-photo-trigger, .profile-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 18px;
    border: 0;
    border-radius: 999px;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: transform .18s ease, opacity .18s ease;
  }
  .profile-photo-trigger, .profile-button-secondary { background: var(--profile-mist); color: var(--primary-dark, #0E4A57); }
  .profile-button-primary { background: var(--primary, #16697A); color: #fff; }
  .profile-button-quiet { background: transparent; color: var(--profile-muted); }
  .profile-photo-trigger:hover, .profile-button:hover { opacity: .86; }
  .profile-button-primary:hover { background: var(--primary-dark, #0E4A57); }
  .profile-button-secondary:hover { background: var(--profile-mist); }
  .profile-photo-trigger:active, .profile-button:active { transform: scale(.98); }
  .profile-photo-trigger svg, .profile-button svg { width: 16px; height: 16px; flex: 0 0 16px; fill: none; stroke: currentColor; stroke-width: 1.6; }
  .profile-file-input:focus-visible + .profile-photo-trigger { outline: 2px solid var(--primary-dark, #0E4A57); outline-offset: 3px; }
  .profile-crop-dialog { width: min(440px, calc(100vw - 28px)); max-width: none; max-height: calc(100dvh - 28px); overflow-y: auto; padding: 0; border: 1px solid rgba(15,76,92,.16); border-radius: 22px; background: var(--profile-paper); color: var(--profile-ink); box-shadow: 0 18px 54px rgba(15,76,92,.2); }
  .profile-crop-dialog::backdrop { background: rgba(15,76,92,.42); }
  .profile-crop-panel { padding: 24px; }
  .profile-crop-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
  .profile-crop-heading h2 { margin: 0; color: var(--primary-dark, #0E4A57); font-family: 'Fraunces', Georgia, serif; font-size: 22px; font-weight: 560; line-height: 1.2; }
  .profile-crop-heading p { margin: 7px 0 0; color: var(--profile-muted); font-size: 13px; line-height: 1.5; }
  .profile-crop-close { display: grid; place-items: center; width: 40px; height: 40px; flex: 0 0 40px; margin: -5px -5px 0 0; border: 0; border-radius: 50%; background: transparent; color: var(--primary-dark, #0E4A57); cursor: pointer; }
  .profile-crop-close:hover { background: var(--profile-mist); }
  .profile-crop-close svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; }
  .profile-crop-stage { display: grid; place-items: center; margin: 20px 0 16px; }
  .profile-crop-canvas { display: block; width: min(100%, 320px); aspect-ratio: 1; border-radius: 50%; background: var(--profile-mist); cursor: grab; touch-action: none; }
  .profile-crop-canvas:focus-visible { outline: 3px solid var(--primary-dark, #0E4A57); outline-offset: 4px; }
  .profile-crop-canvas.is-dragging { cursor: grabbing; }
  .profile-crop-help { margin: 0; color: var(--profile-muted); font-size: 12px; line-height: 1.5; text-align: center; }
  .profile-crop-zoom { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 12px; margin-top: 20px; color: var(--primary-dark, #0E4A57); font-size: 13px; font-weight: 600; }
  .profile-crop-zoom input { width: 100%; margin: 0; accent-color: var(--primary-dark, #0E4A57); }
  .profile-crop-zoom output { min-width: 42px; color: var(--profile-muted); font-size: 12px; font-variant-numeric: tabular-nums; text-align: right; }
  .profile-crop-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
  .profile-crop-actions .profile-button { min-width: 116px; }
  .profile-crop-status:empty { display: none; }
  .profile-crop-status { margin: 12px 0 0; color: var(--profile-error); font-size: 12.5px; line-height: 1.5; }
  .profile-password-note { margin: 12px 0 0; color: var(--profile-muted); font-size: 12.5px; line-height: 1.55; }
  .profile-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; padding-top: 8px; }
  .profile-actions .profile-button { min-width: 136px; }
  .profile-inline-form { display: flex; align-items: flex-end; gap: 16px; min-width: 0; }
  .profile-inline-form .profile-field { flex: 1 1 auto; min-width: 0; }
  .profile-inline-form .profile-button { flex: 0 0 auto; min-width: 138px; }
  .profile-confirm-form { margin-top: 24px; }
  .profile-error { display: flex; align-items: flex-start; gap: 8px; margin: 14px 0 0; color: var(--profile-error); font-size: 12.5px; line-height: 1.5; }
  .address-list { display: grid; gap: 30px; min-width: 0; }
  .address-entry { min-width: 0; padding: 0; }
  .address-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; min-width: 0; }
  .address-content { min-width: 0; flex: 1 1 auto; }
  .address-name-row { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
  .address-name { color: var(--profile-ink); font-size: 14px; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
  .address-default { display: inline-flex; align-items: center; min-height: 24px; padding: 3px 9px; border-radius: 999px; background: var(--profile-mist); color: var(--primary-dark, #0E4A57); font-size: 11.5px; font-weight: 600; }
  .address-detail { margin: 6px 0 0; color: var(--profile-muted); font-size: 14px; line-height: 1.55; overflow-wrap: anywhere; }
  .address-note { display: flex; align-items: flex-start; gap: 7px; margin-top: 8px; color: var(--profile-muted); font-size: 12px; line-height: 1.5; }
  .address-note svg { width: 15px; height: 15px; flex: 0 0 15px; margin-top: 1px; fill: none; stroke: currentColor; stroke-width: 1.5; }
  .address-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; flex: 0 0 auto; flex-wrap: wrap; }
  .address-action { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; padding: 9px 12px; border: 0; border-radius: 999px; background: var(--profile-mist); color: var(--primary-dark, #0E4A57); font: inherit; font-size: 12.5px; font-weight: 600; text-decoration: none; cursor: pointer; transition: transform .18s ease, opacity .18s ease; }
  .address-action:hover { opacity: .86; }
  .address-action:active { transform: scale(.98); }
  .address-action svg { width: 15px; height: 15px; flex: 0 0 15px; fill: none; stroke: currentColor; stroke-width: 1.5; }
  .address-delete { background: transparent; color: var(--profile-muted); }
  .address-delete:hover, .address-delete:focus-visible { color: var(--profile-error); opacity: 1; }
  .address-edit { margin-top: 14px; }
  .address-edit summary, .address-add summary { display: flex; align-items: center; gap: 8px; min-height: 44px; color: var(--primary-dark, #0E4A57); font-size: 13px; font-weight: 600; cursor: pointer; list-style: none; }
  .address-edit summary::-webkit-details-marker, .address-add summary::-webkit-details-marker { display: none; }
  .address-edit summary::marker, .address-add summary::marker { content: ''; }
  .address-chevron { width: 15px; height: 15px; flex: 0 0 15px; fill: none; stroke: currentColor; stroke-width: 1.6; transition: transform .18s ease; }
  .address-edit[open] .address-chevron { transform: rotate(180deg); }
  .address-editor { margin: 8px 0 0 12px; padding: 4px 0 0 16px; }
  .address-editor .profile-grid { gap: 18px 16px; }
  .address-editor .profile-button { margin-top: 16px; }
  .address-add { margin-top: 20px; }
  .address-add summary { min-height: 48px; gap: 10px; padding: 2px 0; }
  .address-add summary > svg { width: 17px; height: 17px; flex: 0 0 17px; fill: none; stroke: currentColor; stroke-width: 1.6; }
  .address-add[open] > summary { margin-bottom: 12px; }
  .address-empty { margin: 0; color: var(--profile-muted); font-size: 13px; line-height: 1.55; }
  .seller-status { display: flex; align-items: flex-start; gap: 10px; padding: 13px 14px; background: var(--profile-mist); color: var(--primary-dark, #0E4A57); font-size: 13px; line-height: 1.5; }
  .seller-status svg { width: 17px; height: 17px; flex: 0 0 17px; margin-top: 1px; fill: none; stroke: currentColor; stroke-width: 1.6; }
  .seller-status-error { padding-left: 0; background: transparent; color: var(--profile-error); }
  .seller-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
  .seller-actions form { min-width: 0; }
  .seller-actions .profile-button { min-width: 172px; }
  @media (min-width: 1024px) {
    .buyer-profile { padding-right: 32px; padding-left: 32px; }
  }
  @media (max-width: 1023.98px) {
    .buyer-profile { padding: 24px 20px 48px; }
    .profile-section { grid-template-columns: minmax(0, 1fr); gap: 18px; padding: 38px 0; }
    .profile-section--first { padding-top: 28px; }
    .profile-copy p { max-width: 60ch; }
    .profile-password-grid { grid-template-columns: minmax(0, 1fr); }
    .profile-inline-form { align-items: stretch; flex-direction: column; gap: 8px; }
    .profile-inline-form .profile-button { width: 100%; }
  }
  @media (max-width: 600px) {
    .buyer-profile { padding: 22px 16px calc(112px + env(safe-area-inset-bottom)); }
    .profile-header h1 { margin-bottom: 14px; font-size: 24px; }
    .profile-identity { align-items: flex-start; gap: 12px; }
    .profile-avatar { font-size: 16px; }
    .profile-identity-name { font-size: 21px; }
    .profile-index { gap: 20px; margin-top: 14px; }
    .profile-section { padding: 32px 0; }
    .profile-section--first { padding-top: 26px; }
    .profile-grid { grid-template-columns: minmax(0, 1fr); }
    .profile-actions { align-items: stretch; flex-direction: column; }
    .profile-actions .profile-button { width: 100%; }
    .address-row { flex-direction: column; gap: 8px; }
    .address-actions { justify-content: flex-start; width: 100%; }
    .address-actions form { flex: 0 0 auto; }
    .address-editor { margin-left: 0; padding-left: 12px; }
    .seller-actions { flex-direction: column; }
    .seller-actions form, .seller-actions .profile-button { width: 100%; }
    .profile-crop-panel { padding: 20px 18px; }
    .profile-crop-actions { flex-direction: column-reverse; }
    .profile-crop-actions .profile-button { width: 100%; }
  }
  @media (prefers-reduced-motion: no-preference) {
    .buyer-profile .profile-section { opacity: 0; animation: profile-section-in .48s cubic-bezier(.22,1,.36,1) forwards; }
    .buyer-profile .section-password { animation-delay: .06s; }
    .buyer-profile .section-email { animation-delay: .12s; }
    .buyer-profile .section-addresses { animation-delay: .18s; }
    .buyer-profile .section-seller { animation-delay: .24s; }
    @keyframes profile-section-in { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
  }
</style>

<div class="buyer-profile">
  <header class="profile-header">
    <h1>My profile</h1>
    <div class="profile-identity">
      <div class="profile-avatar">
        @if($profilePictureUrl)
          <img src="{{ $profilePictureUrl }}" alt="Profile picture">
        @else
          <span aria-hidden="true">{{ $initials }}</span>
        @endif
      </div>
      <div class="profile-identity-copy">
        <p class="profile-identity-name">{{ $displayName }}</p>
        <div class="profile-identity-meta">
          <span class="profile-email">{{ $user->email }}</span>
          <span class="profile-chip">{{ $user->role }}</span>
          <span class="profile-chip"><span class="profile-state-dot{{ $isActive ? ' is-active' : '' }}" aria-hidden="true"></span>{{ $accountStatus }}</span>
        </div>
      </div>
    </div>
  </header>

  <nav class="profile-index" aria-label="Profile sections">
    <a href="#profile">Profile</a>
    <a href="#password">Password</a>
    <a href="#email">Email</a>
    <a href="#addresses">Addresses</a>
    <a href="#seller">Seller</a>
  </nav>

  @if(session('success') || session('error') || $errors->any())
    <div class="profile-messages">
      @if(session('success'))
        <div class="profile-message profile-message-success" role="status">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg>
          <span>{{ session('success') }}</span>
        </div>
      @endif
      @if(session('error'))
        <div class="profile-message profile-message-error" role="alert">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>
          <span>{{ session('error') }}</span>
        </div>
      @endif
      @if($errors->any())
        <div class="profile-message profile-message-error" role="alert">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif
    </div>
  @endif

  <form method="POST" action="{{ route('buyer.profile.update') }}" enctype="multipart/form-data" class="profile-form">
    @csrf
    <section class="profile-section profile-section--first section-profile" id="profile" aria-labelledby="profile-heading">
      <div class="profile-copy">
        <h2 id="profile-heading">Profile</h2>
      </div>
      <div class="profile-body">
        <div class="profile-grid">
          <div class="profile-field">
            <label for="first_name">First name</label>
            <input class="profile-input" id="first_name" type="text" name="first_name" value="{{ old('first_name', $firstName) }}" required maxlength="100" autocomplete="given-name">
          </div>
          <div class="profile-field">
            <label for="last_name">Last name</label>
            <input class="profile-input" id="last_name" type="text" name="last_name" value="{{ old('last_name', $lastName) }}" required maxlength="100" autocomplete="family-name">
          </div>
          <div class="profile-field profile-phone-field">
            <label for="phone">Phone</label>
            <input class="profile-input" id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}" inputmode="tel" maxlength="16" placeholder="09171234567 or +639171234567" autocomplete="tel" aria-describedby="phone-helper">
            <small class="profile-helper" id="phone-helper">Use 11 digits starting with 09, or the equivalent +63 number.</small>
          </div>
          <div class="profile-field profile-photo-field">
            <label class="profile-label" for="profile_picture">Profile picture</label>
            <div class="profile-photo-picker">
              <span class="profile-photo-thumb">
                @if($profilePictureUrl)
                  <img src="{{ $profilePictureUrl }}" alt="Profile picture thumbnail">
                @else
                  <span aria-hidden="true">{{ $initials }}</span>
                @endif
              </span>
              <input class="profile-input profile-file-input" id="profile_picture" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="photo-helper">
              <label class="profile-photo-trigger btn-p btn-line" for="profile_picture">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3l1.5-2h7L17 7h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                Change photo
              </label>
            </div>
            <small class="profile-helper" id="photo-helper">Photo only: JPG, PNG, or WEBP. Maximum 5 MB.</small>
            <small class="profile-photo-status" id="profile-photo-status" role="status" aria-live="polite"></small>
          </div>
        </div>
      </div>
    </section>

    <section class="profile-section section-password" id="password" aria-labelledby="password-heading">
      <div class="profile-copy">
        <h2 id="password-heading">Change password</h2>
      </div>
      <div class="profile-body">
        <div class="profile-password-grid">
          <div class="profile-field">
            <label for="current_password">Current password</label>
            <input class="profile-input" id="current_password" type="password" name="current_password" autocomplete="current-password" aria-describedby="password-rules">
          </div>
          <div class="profile-field">
            <label for="new_password">New password</label>
            <input class="profile-input" id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password" aria-describedby="password-rules">
          </div>
          <div class="profile-field">
            <label for="new_password_confirmation">Confirm new password</label>
            <input class="profile-input" id="new_password_confirmation" type="password" name="new_password_confirmation" minlength="8" autocomplete="new-password" aria-describedby="password-rules">
          </div>
        </div>
        <p class="profile-password-note" id="password-rules">At least 8 characters, including uppercase and lowercase letters, a number, and a symbol. Enter your current password to change it.</p>
        <div class="profile-actions">
          <button type="submit" class="profile-button profile-button-primary btn-p btn-solid">Save changes</button>
          <a href="{{ url('/') }}" class="profile-button profile-button-quiet btn-p">Cancel</a>
        </div>
      </div>
    </section>
  </form>

  <section class="profile-section section-email" id="email" aria-labelledby="email-heading">
    <div class="profile-copy">
      <h2 id="email-heading">Change email address</h2>
      <p id="email-description">We will send a 6-digit code to the new address. Your email changes only after you enter the matching code.</p>
    </div>
    <div class="profile-body">
      <form method="POST" action="{{ route('profile.email.request-code') }}" class="profile-inline-form">
        @csrf
        <div class="profile-field">
          <label for="new_email">New email</label>
          <input class="profile-input" id="new_email" type="email" name="email" value="{{ old('email', $pendingEmail ?: $user->email) }}" required autocomplete="email" aria-describedby="email-description">
        </div>
        <button type="submit" class="profile-button profile-button-secondary btn-p btn-line">Send code</button>
      </form>
      @if($pendingEmail)
        <form method="POST" action="{{ route('profile.email.confirm') }}" class="profile-inline-form profile-confirm-form">
          @csrf
          <input type="hidden" name="email" value="{{ $pendingEmail }}">
          <div class="profile-field">
            <label for="email_code">Verification code sent to {{ $pendingEmail }}</label>
            <input class="profile-input" id="email_code" type="text" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="6-digit code">
          </div>
          <button type="submit" class="profile-button profile-button-secondary btn-p btn-line">Verify and change email</button>
        </form>
      @endif
    </div>
  </section>

  <section class="profile-section section-addresses" id="addresses" aria-labelledby="addresses-heading">
    <div class="profile-copy">
      <h2 id="addresses-heading">Saved delivery addresses</h2>
      <p>Save multiple addresses and choose which one to use by default at checkout.</p>
    </div>
    <div class="profile-body" id="saved-addresses">
      <div class="address-list">
        @forelse($addresses as $address)
          @php
            $addressErrors = $errors->getBag('address_edit_'.$address->id);
            $addressParts = array_filter([$address->address_line, $address->barangay, $address->city, $address->province, $address->region, $address->postal_code], fn($part) => filled($part));
          @endphp
          <article class="address-entry">
            <div class="address-row">
              <div class="address-content">
                <div class="address-name-row">
                  <strong class="address-name">{{ $address->recipient_name }}</strong>
                  @if($address->is_default)<span class="address-default">Default</span>@endif
                </div>
                <p class="address-detail">{{ implode(', ', $addressParts) }}</p>
                <p class="address-detail">{{ $address->phone }}</p>
                @if($address->is_default)
                  <small class="address-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>
                    <span>Set another address as default before deleting this one.</span>
                  </small>
                @endif
              </div>
              <div class="address-actions">
                @unless($address->is_default)
                  <form method="POST" action="{{ route('buyer.addresses.default', $address->id) }}">
                    @csrf
                    <button type="submit" class="address-action">Set as default</button>
                  </form>
                @endunless
                <form method="POST" action="{{ route('buyer.addresses.delete', $address->id) }}" onsubmit="return confirm('Delete this saved address?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="address-action address-delete">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                    Delete
                  </button>
                </form>
              </div>
            </div>
            <details class="address-edit" @if($addressErrors->any()) open @endif>
              <summary>
                Edit address
                <svg class="address-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
              </summary>
              @if($addressErrors->any())
                <div class="profile-error" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg><span>{{ $addressErrors->first() }}</span></div>
              @endif
              <div class="address-editor">
                <form method="POST" action="{{ route('buyer.addresses.update', $address->id) }}">
                  @csrf
                  @method('PUT')
                  @include('profile.address-fields', ['address' => $address, 'fieldPrefix' => 'addresses['.$address->id.']', 'oldPrefix' => 'addresses.'.$address->id, 'makeDefault' => $address->is_default])
                  <button type="submit" class="profile-button profile-button-secondary btn-p btn-line">Save address</button>
                </form>
              </div>
            </details>
          </article>
        @empty
          <p class="address-empty">You have no saved delivery addresses yet.</p>
        @endforelse
      </div>

      @php $newAddressErrors = $errors->getBag('address_new') ?? new \Illuminate\Support\MessageBag(); @endphp
      <details class="address-add" @if($newAddressErrors->any()) open @endif>
        <summary>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          Add a new address
        </summary>
        @if($newAddressErrors->any())
          <div class="profile-error" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg><span>{{ $newAddressErrors->first() }}</span></div>
        @endif
        <form method="POST" action="{{ route('buyer.addresses.store') }}">
          @csrf
          @include('profile.address-fields', ['address' => null, 'fieldPrefix' => 'new_address', 'oldPrefix' => 'new_address', 'makeDefault' => $addresses->isEmpty()])
          <button type="submit" class="profile-button profile-button-secondary btn-p btn-line">Add address</button>
        </form>
      </details>
    </div>
  </section>

  @php
      $sellerApp = $sellerApp ?? null;
  @endphp
  <section class="profile-section section-seller" id="seller" aria-labelledby="seller-heading">
    <div class="profile-copy">
      <h2 id="seller-heading">Become a Seller</h2>
      @if(empty($sellerApp))
        <p>Open your own store: fill the application with your details, address, business info and documents.</p>
      @endif
    </div>
    <div class="profile-body">
      @if(empty($sellerApp))
        <a href="{{ url('/sell') }}" class="profile-button profile-button-secondary btn-p btn-line">Apply as Seller <span aria-hidden="true">&rarr;</span></a>
      @elseif($sellerApp->approval_status === 'pending')
        <div class="seller-status" role="status">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span><b>Application under review.</b><br>Please wait for the administrator's approval, which will be sent to <b>{{ $user->email }}</b>.</span>
        </div>
      @elseif($sellerApp->isApproved())
        <div class="seller-status" role="status">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
          <b>You're an approved seller ({{ $sellerApp->display_name }}).</b>
        </div>
        @if($user->canSell())
          <div class="seller-actions">
            <form method="POST" action="{{ route('role.switch') }}">
              @csrf
              <input type="hidden" name="role" value="seller">
              <button type="submit" class="profile-button profile-button-secondary btn-p btn-line">Switch to Seller <span aria-hidden="true">&rarr;</span></button>
            </form>
          </div>
        @endif
      @else
        <div class="seller-status seller-status-error" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg><span><b>Application not approved.</b> You may fix your details and <a href="{{ url('/sell') }}">apply again</a>.</span></div>
      @endif
    </div>
  </section>
<dialog class="profile-crop-dialog" id="profile-crop-dialog" aria-labelledby="profile-crop-title" aria-describedby="profile-crop-description">
  <div class="profile-crop-panel">
    <div class="profile-crop-heading">
      <div>
        <h2 id="profile-crop-title">Crop your photo</h2>
        <p id="profile-crop-description">Move and zoom the image to choose what appears in your profile.</p>
      </div>
      <button class="profile-crop-close" type="button" data-crop-cancel aria-label="Cancel photo crop">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <div class="profile-crop-stage">
      <canvas class="profile-crop-canvas" id="profile-crop-canvas" width="512" height="512" tabindex="0" aria-label="Photo crop area. Drag to move the image, or use the arrow keys."></canvas>
    </div>
    <p class="profile-crop-help" id="profile-crop-help">Drag to position. Use the zoom control or arrow keys to adjust.</p>
    <label class="profile-crop-zoom" for="profile-crop-zoom">
      <span>Zoom</span>
      <input id="profile-crop-zoom" type="range" min="1" max="3" step="0.01" value="1" aria-describedby="profile-crop-help">
      <output id="profile-crop-zoom-value" for="profile-crop-zoom">100%</output>
    </label>
    <p class="profile-crop-status" id="profile-crop-status" role="alert" aria-live="assertive"></p>
    <div class="profile-crop-actions">
      <button class="profile-button profile-button-quiet btn-p" type="button" data-crop-cancel>Cancel</button>
      <button class="profile-button profile-button-primary btn-p btn-solid" type="button" id="profile-crop-apply">Use this crop</button>
    </div>
  </div>
</dialog>
</div>

<script>
(() => {
  const input = document.getElementById('profile_picture');
  const dialog = document.getElementById('profile-crop-dialog');
  const canvas = document.getElementById('profile-crop-canvas');
  const zoomInput = document.getElementById('profile-crop-zoom');
  const zoomValue = document.getElementById('profile-crop-zoom-value');
  const applyButton = document.getElementById('profile-crop-apply');
  const cropStatus = document.getElementById('profile-crop-status');
  const photoStatus = document.getElementById('profile-photo-status');
  const context = canvas?.getContext('2d');

  if (!input || !dialog || !canvas || !context || !zoomInput || !applyButton) return;

  const previewTargets = [
    document.querySelector('.profile-photo-thumb'),
    document.querySelector('.profile-avatar'),
  ].filter(Boolean);
  const initialPreviews = previewTargets.map((element) =>
    Array.from(element.childNodes, (node) => node.cloneNode(true))
  );
  const cropSize = canvas.width;
  let image = null;
  let imageUrl = null;
  let imageFile = null;
  let baseScale = 1;
  let zoom = 1;
  let offsetX = 0;
  let offsetY = 0;
  let dragStart = null;
  let stagedFile = null;
  let stagedPreviewUrl = null;
  let cropWasApplied = false;

  function setStatus(message) {
    photoStatus.textContent = message;
  }

  function setCropError(message) {
    cropStatus.textContent = message;
  }

  function renderPreview(url) {
    previewTargets.forEach((element, index) => {
      if (!url) {
        element.replaceChildren(...initialPreviews[index].map((node) => node.cloneNode(true)));
        return;
      }

      const preview = document.createElement('img');
      preview.src = url;
      preview.alt = index === 0 ? 'Profile picture thumbnail' : 'Profile picture';
      element.replaceChildren(preview);
    });
  }

  function setInputFile(file) {
    try {
      const transfer = new DataTransfer();
      if (file) transfer.items.add(file);
      input.files = transfer.files;
      return true;
    } catch (error) {
      return false;
    }
  }

  function restoreStagedSelection() {
    if (stagedFile) {
      if (!setInputFile(stagedFile)) {
        stagedFile = null;
        if (stagedPreviewUrl) URL.revokeObjectURL(stagedPreviewUrl);
        stagedPreviewUrl = null;
        input.value = '';
        renderPreview(null);
        setStatus('Your browser could not restore the previous crop. Please choose the photo again.');
        return;
      }
      renderPreview(stagedPreviewUrl);
      return;
    }

    input.value = '';
    renderPreview(null);
  }

  function imageDimensions() {
    const scale = baseScale * zoom;
    return { width: image.naturalWidth * scale, height: image.naturalHeight * scale };
  }

  function keepImageInFrame() {
    const dimensions = imageDimensions();
    offsetX = Math.min(0, Math.max(cropSize - dimensions.width, offsetX));
    offsetY = Math.min(0, Math.max(cropSize - dimensions.height, offsetY));
  }

  function drawCrop() {
    if (!image) return;
    const dimensions = imageDimensions();
    context.clearRect(0, 0, cropSize, cropSize);
    context.drawImage(image, offsetX, offsetY, dimensions.width, dimensions.height);
  }

  function setZoom(nextZoom) {
    if (!image) return;
    const previous = imageDimensions();
    const centerX = (cropSize / 2 - offsetX) / previous.width;
    const centerY = (cropSize / 2 - offsetY) / previous.height;
    zoom = Number(nextZoom);
    const next = imageDimensions();
    offsetX = cropSize / 2 - centerX * next.width;
    offsetY = cropSize / 2 - centerY * next.height;
    keepImageInFrame();
    drawCrop();
    zoomValue.textContent = `${Math.round(zoom * 100)}%`;
  }

  function cleanupSourceImage() {
    image = null;
    imageFile = null;
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = null;
  }

  function loadImage(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const nextImage = new Image();
      nextImage.onload = () => resolve({ image: nextImage, url });
      nextImage.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('This image could not be opened. Choose a JPG, PNG, or WEBP image.'));
      };
      nextImage.src = url;
    });
  }

  input.addEventListener('change', async () => {
    const selectedFile = input.files?.[0];
    if (!selectedFile) return;

    setStatus('');
    setCropError('');
    const allowedMime = ['', 'image/jpeg', 'image/png', 'image/webp'].includes(selectedFile.type);
    const allowedExtension = /\.(jpe?g|png|webp)$/i.test(selectedFile.name);
    if (!allowedMime || !allowedExtension) {
      restoreStagedSelection();
      setStatus('Choose a JPG, PNG, or WEBP image.');
      return;
    }

    try {
      const loaded = await loadImage(selectedFile);
      image = loaded.image;
      imageUrl = loaded.url;
      imageFile = selectedFile;
      baseScale = Math.max(cropSize / image.naturalWidth, cropSize / image.naturalHeight);
      zoom = 1;
      const dimensions = imageDimensions();
      offsetX = (cropSize - dimensions.width) / 2;
      offsetY = (cropSize - dimensions.height) / 2;
      zoomInput.value = '1';
      drawCrop();
      dialog.showModal();
      window.requestAnimationFrame(() => canvas.focus({ preventScroll: true }));
    } catch (error) {
      restoreStagedSelection();
      cleanupSourceImage();
      setStatus(error.message || 'This image could not be opened. Choose another photo.');
    }
  });

  zoomInput.addEventListener('input', () => setZoom(zoomInput.value));

  canvas.addEventListener('pointerdown', (event) => {
    if (!image) return;
    dragStart = { pointerId: event.pointerId, x: event.clientX, y: event.clientY, offsetX, offsetY };
    canvas.setPointerCapture(event.pointerId);
    canvas.classList.add('is-dragging');
    event.preventDefault();
  });

  canvas.addEventListener('pointermove', (event) => {
    if (!dragStart || dragStart.pointerId !== event.pointerId) return;
    const bounds = canvas.getBoundingClientRect();
    offsetX = dragStart.offsetX + (event.clientX - dragStart.x) * cropSize / bounds.width;
    offsetY = dragStart.offsetY + (event.clientY - dragStart.y) * cropSize / bounds.height;
    keepImageInFrame();
    drawCrop();
  });

  function stopDragging(event) {
    if (!dragStart || (event && dragStart.pointerId !== event.pointerId)) return;
    dragStart = null;
    canvas.classList.remove('is-dragging');
  }

  canvas.addEventListener('pointerup', stopDragging);
  canvas.addEventListener('pointercancel', stopDragging);
  canvas.addEventListener('lostpointercapture', stopDragging);
  canvas.addEventListener('keydown', (event) => {
    const movement = event.shiftKey ? 24 : 10;
    if (event.key === 'ArrowLeft') offsetX += movement;
    else if (event.key === 'ArrowRight') offsetX -= movement;
    else if (event.key === 'ArrowUp') offsetY += movement;
    else if (event.key === 'ArrowDown') offsetY -= movement;
    else return;
    event.preventDefault();
    keepImageInFrame();
    drawCrop();
  });

  document.querySelectorAll('[data-crop-cancel]').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  dialog.addEventListener('close', () => {
    if (!cropWasApplied) {
      restoreStagedSelection();
      setStatus('Crop cancelled. Your previous selection is unchanged.');
    }
    cropWasApplied = false;
    cleanupSourceImage();
  });

  applyButton.addEventListener('click', () => {
    if (!image || !imageFile) return;
    setCropError('');
    const outputType = imageFile.type === 'image/png' ? 'image/png' : 'image/jpeg';
    try {
      canvas.toBlob((blob) => {
        if (!blob) {
          setCropError('The cropped photo could not be prepared. Please try another image.');
          return;
        }

        const extension = blob.type === 'image/png' ? 'png' : 'jpg';
        const croppedFile = new File([blob], `profile-picture.${extension}`, { type: blob.type, lastModified: Date.now() });
        if (!setInputFile(croppedFile)) {
          setCropError('Your browser could not prepare the cropped photo. Please try a current browser.');
          return;
        }

        if (stagedPreviewUrl) URL.revokeObjectURL(stagedPreviewUrl);
        stagedFile = croppedFile;
        stagedPreviewUrl = URL.createObjectURL(blob);
        renderPreview(stagedPreviewUrl);
        setStatus('Crop ready. Choose Save changes below to upload your photo.');
        cropWasApplied = true;
        dialog.close();
      }, outputType, 0.92);
    } catch (error) {
      setCropError('The cropped photo could not be prepared. Please try another image.');
    }
  });
})();
</script>
@endsection
