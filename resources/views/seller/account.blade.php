@extends('seller.layout')

@section('content')
<style>
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; letter-spacing: -.3px; }
  label { display: block; font-size: 12px; font-weight: 700; letter-spacing: 1px; color: #374151; margin-bottom: 6px; text-transform: uppercase; }
  input, textarea { width: 100%; background: #fff; border: 1px solid #D8DBDF; border-radius: 10px; padding: 11px 14px; font-size: 14px; font-family: inherit; margin-bottom: 14px; outline: none; }
  input:focus, textarea:focus { border-color: #116B78; box-shadow: 0 0 0 3px rgba(17,107,120,.12); }
  textarea { resize: vertical; }
  .btn { background: #116B78; color: #fff; border: none; border-radius: 10px; padding: 12px 24px; font-weight: 700; font-size: 14px; cursor: pointer; transition: transform .18s ease; }
  .btn:hover { background: #0C555F; transform: scaleX(1.06); }
  .status-msg { background: #E6F5EE; color: #1E7A43; border: 1px solid #BFE3D0; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
  .store-preview { display: flex; align-items: center; gap: 14px; background: #F8F9FA; border: 1px solid #EEEFF1; border-radius: 14px; padding: 16px; margin-bottom: 18px; }
  .preview-logo { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 24px; background: {{ $seller->primary_color }}; }
  .preview-name { font-weight: 800; font-size: 17px; }
  .preview-sub { font-size: 13px; color: #6B7280; }
  .color-row { display: flex; gap: 12px; }
  .color-row div { flex: 1; }
  .swatch-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
  .swatch { width: 26px; height: 26px; border-radius: 8px; border: 1px solid #E5E5E5; display: inline-block; }
  .hint { font-size: 12px; color: #9CA3AF; margin: -8px 0 14px; }
  @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
</style>

@if(session('status'))
  <div class="status-msg">{{ session('status') }}</div>
@endif

<div class="store-preview">
  <div class="preview-logo">{{ strtoupper(mb_substr($seller->store_name, 0, 1)) }}</div>
  <div>
    <div class="preview-name">{{ $seller->store_name }}</div>
    <div class="preview-sub">{{ $storeSubtitle }} · {{ $seller->status }}</div>
    <div class="swatch-row" style="margin-top:8px;margin-bottom:0;">
      <span class="swatch" style="background:{{ $seller->primary_color }}"></span>
      <span class="swatch" style="background:{{ $seller->accent_color }}"></span>
      <span style="font-size:12px;color:#6B7280;">Primary & accent</span>
    </div>
  </div>
</div>

<form method="POST" action="{{ url('/seller/account') }}">
  @csrf
  <div class="grid-2">
    <div class="panel">
      <h3 class="panel-title">Store Information</h3>
      <label>Store Name</label>
      <input name="store_name" value="{{ old('store_name', $seller->store_name) }}" required />

      <label>Business Type</label>
      <input name="business_info" value="{{ old('business_info', $profile->business_info ?? '') }}" placeholder="e.g. General Merchandise" />

      <label>Description</label>
      <textarea name="description" rows="4" placeholder="Short store description">{{ old('description', $profile->description ?? '') }}</textarea>

      <label>Operating Hours</label>
      <input name="operating_hours" value="{{ old('operating_hours', $profile->operating_hours ?? '') }}" placeholder="e.g. Mon–Sat 9AM–6PM" />
    </div>

    <div class="panel">
      <h3 class="panel-title">Contact & Theme</h3>
      <label>Contact Email</label>
      <input name="contact_email" type="email" value="{{ old('contact_email', $profile->contact_email ?? '') }}" />

      <label>Contact Phone</label>
      <input name="contact_phone" value="{{ old('contact_phone', $profile->contact_phone ?? '') }}" />

      <label>Address</label>
      <input name="address" value="{{ old('address', $profile->address ?? '') }}" />

      <div class="color-row">
        <div>
          <label>Primary Color</label>
          <input name="primary_color" value="{{ old('primary_color', $seller->primary_color) }}" placeholder="#116B78" />
        </div>
        <div>
          <label>Accent Color</label>
          <input name="accent_color" value="{{ old('accent_color', $seller->accent_color) }}" placeholder="#F0A202" />
        </div>
      </div>
      <div class="hint">Hex format, e.g. #116B78. Used in your storefront, avatars, and chat.</div>

      <label>Logo Path</label>
      <input name="logo" value="{{ old('logo', $seller->logo ?? '') }}" placeholder="logos/my-store.png" />
      <div class="hint">File under storage/app/public/ (served at /storage/...). Leave blank for the letter avatar.</div>

      <button class="btn" type="submit" style="width:100%;">Save Changes</button>
    </div>
  </div>
</form>
@endsection