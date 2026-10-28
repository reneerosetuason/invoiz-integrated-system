@extends('buyer.layouts.website')
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
<link rel="stylesheet" href="{{ asset('assets/buyer/css/profile.css') }}">

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
                  @include('buyer.profile.address-fields', ['address' => $address, 'fieldPrefix' => 'addresses['.$address->id.']', 'oldPrefix' => 'addresses.'.$address->id, 'makeDefault' => $address->is_default])
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
          @include('buyer.profile.address-fields', ['address' => null, 'fieldPrefix' => 'new_address', 'oldPrefix' => 'new_address', 'makeDefault' => $addresses->isEmpty()])
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

<script src="{{ asset('assets/buyer/js/profile.js') }}"></script>
@endsection
