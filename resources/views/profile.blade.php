@extends('layouts.website')
@section('content')
@php
  $nameParts = preg_split('/\s+/', trim($user->displayName()), 2);
  $firstName = $user->first_name ?: ($nameParts[0] ?? '');
  $lastName = $user->last_name ?: ($nameParts[1] ?? '');
  $displayName = trim($firstName.' '.$lastName);
  $profilePictureUrl = $user->profile_picture ? asset('storage/'.ltrim($user->profile_picture, '/')) : null;
@endphp
<style>
  .profile-input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;margin-top:5px;background:#fff}
  .profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  @media(max-width:600px){.profile-grid{grid-template-columns:1fr}}
</style>
<div style="max-width:760px;margin:20px auto;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden">
  <div style="height:120px;background:linear-gradient(135deg,#0F766E,#0D9488);position:relative">
    <div style="position:absolute;left:50%;bottom:-32px;transform:translateX(-50%);width:72px;height:72px;border-radius:50%;border:4px solid #fff;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.12)">
      <img src="{{ $profilePictureUrl ?: 'https://ui-avatars.com/api/?name='.urlencode($displayName).'&background=16697A&color=fff&size=72' }}" alt="Profile picture" style="width:100%;height:100%;object-fit:cover">
    </div>
  </div>
  <div style="padding:44px 24px 24px;text-align:center">
    <h2 style="margin:0;font-size:18px">{{ $displayName }}</h2>
    <div style="color:var(--text-secondary);font-size:12px">{{ $user->email }} · {{ $user->role }} · {{ $user->approval_status ?? $user->account_status ?? 'active' }}</div>
  </div>
  @if(session('success'))<div style="margin:0 24px 12px;background:#E8F5EE;color:#1D6B43;padding:10px;border-radius:8px;border:1px solid #BFE3D0;font-size:13px">{{ session('success') }}</div>@endif
  @if(session('error'))<div style="margin:0 24px 12px;background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;border:1px solid #FECACA;font-size:13px">{{ session('error') }}</div>@endif
  @if($errors->any())<div style="margin:0 24px 12px;background:#FDECEC;color:#B91C1C;padding:10px;border-radius:8px;border:1px solid #FECACA;font-size:13px">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('buyer.profile.update') }}" enctype="multipart/form-data" style="padding:0 24px 24px">
    @csrf
    <div class="profile-grid">
      <div><label style="font-size:11px;font-weight:700">First name</label><input class="profile-input" type="text" name="first_name" value="{{ old('first_name', $firstName) }}" required maxlength="100" autocomplete="given-name"></div>
      <div><label style="font-size:11px;font-weight:700">Last name</label><input class="profile-input" type="text" name="last_name" value="{{ old('last_name', $lastName) }}" required maxlength="100" autocomplete="family-name"></div>
      <div>
        <label style="font-size:11px;font-weight:700">Phone</label>
        <input class="profile-input" type="tel" name="phone" value="{{ old('phone', $user->phone ?? '') }}" inputmode="tel" maxlength="16" placeholder="09171234567 or +639171234567" autocomplete="tel">
        <small style="display:block;color:var(--text-secondary);font-size:11px;margin-top:4px">Use 11 digits starting with 09, or the equivalent +63 number.</small>
      </div>
      <div>
        <label style="font-size:11px;font-weight:700">Profile picture</label>
        <input class="profile-input" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
        <small style="display:block;color:var(--text-secondary);font-size:11px;margin-top:4px">Photo only: JPG, PNG, or WEBP. Maximum 5 MB.</small>
      </div>
    </div>

    <div style="margin-top:16px;padding:14px;border:1px solid var(--border);border-radius:12px">
      <h3 style="margin:0 0 10px;font-size:13px">Change password</h3>
      <div class="profile-grid">
        <div><label style="font-size:11px;font-weight:700">Current password</label><input class="profile-input" type="password" name="current_password" autocomplete="current-password"></div>
        <div><label style="font-size:11px;font-weight:700">New password</label><input class="profile-input" type="password" name="new_password" minlength="8" autocomplete="new-password"></div>
        <div><label style="font-size:11px;font-weight:700">Confirm new password</label><input class="profile-input" type="password" name="new_password_confirmation" minlength="8" autocomplete="new-password"></div>
      </div>
      <small style="display:block;color:var(--text-secondary);font-size:11px;margin-top:8px">At least 8 characters, including uppercase and lowercase letters, a number, and a symbol. Enter your current password to change it.</small>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px">
      <button type="submit" style="flex:1;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer">Save changes</button>
      <a href="{{ url('/') }}" style="flex:1;text-align:center;padding:11px;background:#fff;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text-primary);font-weight:700">Cancel</a>
    </div>
  </form>
</div>

<div style="max-width:760px;margin:0 auto 20px;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden">
  <div style="padding:20px 24px">
    <h3 style="margin:0;font-size:15px">Change email address</h3>
    <p style="color:var(--text-secondary);font-size:12px;margin:6px 0 12px">We will send a 6-digit code to the new address. Your email changes only after you enter the matching code.</p>
    <form method="POST" action="{{ route('profile.email.request-code') }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
      @csrf
      <div style="flex:1;min-width:220px">
        <label style="font-size:11px;font-weight:700">New email</label>
        <input class="profile-input" type="email" name="email" value="{{ old('email', $pendingEmail ?: $user->email) }}" required autocomplete="email">
      </div>
      <button type="submit" style="padding:11px 14px;background:var(--primary);color:#fff;border:0;border-radius:10px;font-weight:700;cursor:pointer">Send code</button>
    </form>
    @if($pendingEmail)
      <form method="POST" action="{{ route('profile.email.confirm') }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:14px">
        @csrf
        <input type="hidden" name="email" value="{{ $pendingEmail }}">
        <div style="flex:1;min-width:180px">
          <label style="font-size:11px;font-weight:700">Verification code sent to {{ $pendingEmail }}</label>
          <input class="profile-input" type="text" name="email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="6-digit code">
        </div>
        <button type="submit" style="padding:11px 14px;background:var(--primary);color:#fff;border:0;border-radius:10px;font-weight:700;cursor:pointer">Verify and change email</button>
      </form>
    @endif
  </div>
</div>
<div id="saved-addresses" style="max-width:760px;margin:0 auto 20px;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;scroll-margin-top:24px">
  <div style="padding:20px 24px;border-bottom:1px solid var(--border-light)">
    <h3 style="margin:0;font-size:15px">Saved delivery addresses</h3>
    <p style="color:var(--text-secondary);font-size:12px;margin:6px 0 0">Save multiple addresses and choose which one to use by default at checkout.</p>
  </div>
  <div style="padding:16px 24px">
    @forelse($addresses as $address)
      @php
        $addressErrors = $errors->getBag('address_edit_'.$address->id);
        $addressParts = array_filter([$address->address_line, $address->barangay, $address->city, $address->province, $address->region, $address->postal_code], fn($part) => filled($part));
      @endphp
      <article style="padding:14px;border:1px solid var(--border);border-radius:12px;margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
          <div style="min-width:0;flex:1">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <strong style="font-size:13px">{{ $address->recipient_name }}</strong>
              @if($address->is_default)<span style="font-size:10px;font-weight:700;color:#0F766E;background:#E6F4F1;padding:4px 8px;border-radius:999px">Default</span>@endif
            </div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:5px">{{ implode(', ', $addressParts) }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:3px">{{ $address->phone }}</div>
            @if($address->is_default)<small style="display:block;color:var(--text-secondary);font-size:11px;margin-top:6px">Set another address as default before deleting this one.</small>@endif
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            @unless($address->is_default)
              <form method="POST" action="{{ route('buyer.addresses.default', $address->id) }}">
                @csrf
                <button type="submit" style="padding:7px 10px;background:#fff;border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-size:11px;font-weight:700;cursor:pointer">Set as default</button>
              </form>
            @endunless
            <form method="POST" action="{{ route('buyer.addresses.delete', $address->id) }}" onsubmit="return confirm('Delete this saved address?')">
              @csrf
              @method('DELETE')
              <button type="submit" style="padding:7px 10px;background:#fff;border:1px solid #FECACA;border-radius:8px;color:#B91C1C;font-size:11px;font-weight:700;cursor:pointer">Delete</button>
            </form>
          </div>
        </div>
        <details style="margin-top:12px;border-top:1px solid var(--border-light);padding-top:10px" @if($addressErrors->any()) open @endif>
          <summary style="cursor:pointer;color:var(--primary);font-size:12px;font-weight:700">Edit address</summary>
          @if($addressErrors->any())<div style="margin-top:10px;background:#FDECEC;color:#B91C1C;padding:9px;border-radius:8px;font-size:12px">{{ $addressErrors->first() }}</div>@endif
          <form method="POST" action="{{ route('buyer.addresses.update', $address->id) }}" style="margin-top:10px">
            @csrf
            @method('PUT')
            @include('profile.address-fields', ['address' => $address, 'fieldPrefix' => 'addresses['.$address->id.']', 'oldPrefix' => 'addresses.'.$address->id, 'makeDefault' => $address->is_default])
            <button type="submit" style="margin-top:12px;padding:9px 14px;background:var(--primary);color:#fff;border:0;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Save address</button>
          </form>
        </details>
      </article>
    @empty
      <p style="margin:0 0 12px;color:var(--text-secondary);font-size:12px">You have no saved delivery addresses yet.</p>
    @endforelse

    @php($newAddressErrors = $errors->getBag('address_new'))
    <details style="padding:14px;border:1px dashed var(--border);border-radius:12px" @if($newAddressErrors->any()) open @endif>
      <summary style="cursor:pointer;color:var(--primary);font-size:13px;font-weight:700">Add a new address</summary>
      @if($newAddressErrors->any())<div style="margin-top:10px;background:#FDECEC;color:#B91C1C;padding:9px;border-radius:8px;font-size:12px">{{ $newAddressErrors->first() }}</div>@endif
      <form method="POST" action="{{ route('buyer.addresses.store') }}" style="margin-top:12px">
        @csrf
        @include('profile.address-fields', ['address' => null, 'fieldPrefix' => 'new_address', 'oldPrefix' => 'new_address', 'makeDefault' => $addresses->isEmpty()])
        <button type="submit" style="margin-top:12px;padding:9px 14px;background:var(--primary);color:#fff;border:0;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Add address</button>
      </form>
    </details>
  </div>
</div>
@php $sellerApp = $sellerApp ?? null; @endphp
<div style="max-width:720px;margin:0 auto 20px;background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden">
  <div style="padding:20px 24px">
    <h3 style="margin:0;font-size:15px">Become a Seller</h3>
    @if(empty($sellerApp))
      <p style="color:var(--text-secondary);font-size:12px;margin:6px 0 12px">Open your own store: fill the application with your details, address, business info and documents.</p>
      <a href="{{ url('/sell') }}" style="display:inline-block;padding:10px 18px;background:var(--primary);color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px">Apply as Seller →</a>
    @elseif($sellerApp->approval_status === 'pending')
      <div style="margin-top:10px;background:#FFF8E1;border:1px solid #F5D47A;border-radius:10px;padding:12px;font-size:12px;color:#8a5a00"><b>Application under review.</b><br>Please wait for the administrator's approval, which will be sent to <b>{{ $user->email }}</b>.</div>
    @elseif($sellerApp->isApproved())
      <div style="margin-top:10px;background:#E8F5EE;border:1px solid #BFE3D0;border-radius:10px;padding:12px;font-size:12px;color:#1D6B43"><b>You're an approved seller ({{ $sellerApp->display_name }}).</b></div>
      @if($user->canSell())
      <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
        <form method="POST" action="{{ route('role.switch') }}" style="flex:1;min-width:150px">
          @csrf
          <input type="hidden" name="role" value="seller">
          <button type="submit" style="width:100%;text-align:center;padding:11px;background:var(--primary);color:#fff;border:0;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">Switch to Seller →</button>
        </form>
      </div>
      @endif
    @else
      <div style="margin-top:10px;background:#FDECEC;border:1px solid #FECACA;border-radius:10px;padding:12px;font-size:12px;color:#B91C1C"><b>Application not approved.</b> You may fix your details and <a href="{{ url('/sell') }}" style="font-weight:700">apply again</a>.</div>
    @endif
  </div>
</div>
@endsection
