@php
  $uid = preg_replace('/[^A-Za-z0-9_]/', '_', $fieldPrefix);
  $addressValues = [
    'region' => old($oldPrefix.'.region', $address?->region ?? ''),
    'province' => old($oldPrefix.'.province', $address?->province ?? ''),
    'city' => old($oldPrefix.'.city', $address?->city ?? ''),
    'barangay' => old($oldPrefix.'.barangay', $address?->barangay ?? ''),
  ];
@endphp
<div class="profile-grid">
  <div class="profile-field"><label style="font-size:11px;font-weight:700" for="{{ $uid }}_recipient_name">Recipient name</label><input class="profile-input" id="{{ $uid }}_recipient_name" name="{{ $fieldPrefix }}[recipient_name]" value="{{ old($oldPrefix.'.recipient_name', $address?->recipient_name ?? $user->displayName()) }}" maxlength="100" required></div>
  <div class="profile-field"><label style="font-size:11px;font-weight:700" for="{{ $uid }}_phone">Phone</label><input class="profile-input" id="{{ $uid }}_phone" type="tel" name="{{ $fieldPrefix }}[phone]" value="{{ old($oldPrefix.'.phone', $address?->phone ?? $user->phone) }}" maxlength="16" inputmode="tel" autocomplete="tel" placeholder="09171234567 or +639171234567" required></div>

  <div class="profile-field" style="grid-column:1/-1">
    <label style="font-size:11px;font-weight:700" for="{{ $uid }}_address_line">House number / Street</label>
    <input class="profile-input" id="{{ $uid }}_address_line" name="{{ $fieldPrefix }}[address_line]" value="{{ old($oldPrefix.'.address_line', $address?->address_line ?? '') }}" maxlength="255" required placeholder="e.g., 123 Rizal Street, Unit 4B">
  </div>

  @include('partials.psgc-address-fields', [
    'prefix' => $fieldPrefix,
    'uid' => $uid,
    'values' => $addressValues,
    'controlClass' => 'profile-input',
    'labelStyle' => 'font-size:11px;font-weight:700',
    'colClass' => 'profile-field',
    'fields' => ['region', 'province', 'city', 'barangay'],
    'required' => ['region', 'province', 'city', 'barangay'],
  ])

  <div class="profile-field"><label style="font-size:11px;font-weight:700" for="{{ $uid }}_postal_code">Postal code (optional)</label><input class="profile-input" name="{{ $fieldPrefix }}[postal_code]" id="{{ $uid }}_postal_code" value="{{ old($oldPrefix.'.postal_code', $address?->postal_code ?? '') }}" maxlength="20"></div>
</div>

<label class="profile-default-checkbox" style="display:flex;gap:8px;align-items:center;margin-top:12px;font-size:12px;font-weight:600" for="{{ $uid }}_is_default">
  <input id="{{ $uid }}_is_default" type="checkbox" name="{{ $fieldPrefix }}[is_default]" value="1" @checked(old($oldPrefix.'.is_default', $makeDefault))>
  Make this my default address
</label>
