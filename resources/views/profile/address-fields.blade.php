<div class="profile-grid">
  <div><label style="font-size:11px;font-weight:700">Recipient name</label><input class="profile-input" name="{{ $fieldPrefix }}[recipient_name]" value="{{ old($oldPrefix.'.recipient_name', $address?->recipient_name ?? $user->displayName()) }}" maxlength="100" required></div>
  <div><label style="font-size:11px;font-weight:700">Phone</label><input class="profile-input" type="tel" name="{{ $fieldPrefix }}[phone]" value="{{ old($oldPrefix.'.phone', $address?->phone ?? $user->phone) }}" maxlength="16" inputmode="tel" placeholder="09171234567 or +639171234567" required></div>
  <div style="grid-column:1/-1"><label style="font-size:11px;font-weight:700">House / street / building</label><input class="profile-input" name="{{ $fieldPrefix }}[address_line]" value="{{ old($oldPrefix.'.address_line', $address?->address_line ?? '') }}" maxlength="255" required></div>
  <div><label style="font-size:11px;font-weight:700">Barangay</label><input class="profile-input" name="{{ $fieldPrefix }}[barangay]" value="{{ old($oldPrefix.'.barangay', $address?->barangay ?? '') }}" maxlength="100" required></div>
  <div><label style="font-size:11px;font-weight:700">City / municipality</label><input class="profile-input" name="{{ $fieldPrefix }}[city]" value="{{ old($oldPrefix.'.city', $address?->city ?? '') }}" maxlength="100" required></div>
  <div><label style="font-size:11px;font-weight:700">Province</label><input class="profile-input" name="{{ $fieldPrefix }}[province]" value="{{ old($oldPrefix.'.province', $address?->province ?? '') }}" maxlength="100" required></div>
  <div><label style="font-size:11px;font-weight:700">Region (optional)</label><input class="profile-input" name="{{ $fieldPrefix }}[region]" value="{{ old($oldPrefix.'.region', $address?->region ?? '') }}" maxlength="100"></div>
  <div><label style="font-size:11px;font-weight:700">Postal code (optional)</label><input class="profile-input" name="{{ $fieldPrefix }}[postal_code]" value="{{ old($oldPrefix.'.postal_code', $address?->postal_code ?? '') }}" maxlength="20"></div>
</div>
<label style="display:flex;gap:8px;align-items:center;margin-top:12px;font-size:12px;font-weight:600">
  <input type="checkbox" name="{{ $fieldPrefix }}[is_default]" value="1" @checked(old($oldPrefix.'.is_default', $makeDefault))>
  Make this my default address
</label>
