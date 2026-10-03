@extends('admin.layout')

@section('content')
<style>
  .seller-hero { background: linear-gradient(135deg, #16697A 0%, #0E4A57 100%); border-radius:18px; padding:22px 24px; color:#fff; display:flex; align-items:center; gap:18px; margin-bottom:18px; position:relative; overflow:hidden; }
  .seller-hero::after { content:''; position:absolute; right:-40px; top:-40px; width:160px; height:160px; background:rgba(255,255,255,.08); border-radius:50%; }
  .hero-logo { width:64px; height:64px; border-radius:16px; background:#fff; color:#16697A; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:26px; flex-shrink:0; box-shadow:0 8px 24px rgba(0,0,0,.15); overflow:hidden; border:3px solid rgba(255,255,255,.3); }
  .hero-logo img { width:100%; height:100%; object-fit:cover; }
  .hero-info { min-width:0; }
  .hero-name { font-size:22px; font-weight:800; letter-spacing:-.5px; line-height:1.2; }
  .hero-meta { font-size:13px; color:rgba(255,255,255,.8); margin-top:4px; display:flex; gap:12px; flex-wrap:wrap; }
  .hero-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid rgba(255,255,255,.3); backdrop-filter:blur(6px); }
  .hero-actions { margin-left:auto; display:flex; gap:8px; flex-shrink:0; }
  .grid-2 { display:grid; grid-template-columns: 1fr 1.1fr; gap:18px; }
  .info-card { background:#fff; border:1px solid #E8E6E0; border-radius:16px; padding:20px; }
  .info-card h2 { font-size:15px; font-weight:800; letter-spacing:-.3px; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
  .info-card h2 svg { width:18px; height:18px; stroke:#16697A; fill:none; stroke-width:1.8; }
  .info-row { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #F1F2F4; align-items:flex-start; }
  .info-row:last-child { border-bottom:none; }
  .info-icon { width:36px; height:36px; border-radius:10px; background:#F0FAFA; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .info-icon svg { width:16px; height:16px; stroke:#16697A; fill:none; stroke-width:1.7; }
  .info-label { font-size:11px; font-weight:700; color:#9CA3AF; text-transform:uppercase; letter-spacing:.7px; }
  .info-value { font-size:14px; font-weight:600; color:#1B1B1E; margin-top:2px; word-break:break-all; }
  .theme-preview { background:linear-gradient(180deg,#F8F9FA 0%,#fff 100%); border:1px solid #E8E6E0; border-radius:12px; padding:14px; display:flex; align-items:center; gap:14px; margin-top:16px; }
  .theme-swatch { width:20px; height:20px; border-radius:6px; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.1); display:inline-block; }
  .product-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
  .prod-card { background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow:hidden; transition:transform .15s, box-shadow .15s; }
  .prod-card:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(16,24,40,.08); }
  .prod-thumb { height:110px; background:linear-gradient(135deg,#E6F1F2 0%,#F0FAFA 100%); display:flex; align-items:center; justify-content:center; position:relative; }
  .prod-thumb-letter { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; }
  .prod-status { position:absolute; top:8px; right:8px; font-size:10px; font-weight:700; padding:4px 8px; border-radius:999px; }
  .prod-body { padding:12px; }
  .prod-name { font-size:13.5px; font-weight:700; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .prod-cat { font-size:11px; color:#9CA3AF; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .prod-price { font-size:15px; font-weight:800; color:#16697A; margin-top:8px; }
  .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px; }
  .stat-mini { background:#F8F9FA; border:1px solid #F1F2F4; border-radius:12px; padding:12px; text-align:center; }
  .stat-mini strong { font-size:18px; font-weight:800; display:block; }
  .stat-mini span { font-size:11px; color:#6E6E73; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  @media (max-width: 900px) { .grid-2 { grid-template-columns:1fr; } .seller-hero { flex-direction:column; align-items:flex-start; } .hero-actions { margin-left:0; } }
</style>

<div class="seller-hero">
  <div class="hero-logo">
    @if($seller->logo)
      <img src="{{ url('storage/'.ltrim($seller->logo,'/')) }}" alt="logo">
    @else
      {{ strtoupper(mb_substr($seller->store_name,0,1)) }}
    @endif
  </div>
  <div class="hero-info">
    <div class="hero-name">{{ $seller->store_name }}</div>
    <div class="hero-meta">
      <span>{{ $seller->user->email ?? 'No email' }}</span>
      <span>·</span>
      <span>Registered {{ $seller->created_at->format('M d, Y') }}</span>
      <span>·</span>
      <span>ID #{{ $seller->id }}</span>
    </div>
  </div>
  <span class="hero-badge" style="background:{{ $seller->status === 'approved' ? 'rgba(46,139,87,.15)' : ($seller->status === 'rejected' ? 'rgba(224,90,51,.15)' : 'rgba(255,255,255,.15)') }}; color:#fff; border-color:{{ $seller->status === 'approved' ? '#2E8B57' : ($seller->status === 'rejected' ? '#E05A33' : 'rgba(255,255,255,.3)') }};">
    {{ ucfirst($seller->status) }}
  </span>
  @if($seller->status === 'pending')
  <div class="hero-actions">
    <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}">@csrf<button type="submit" style="padding:10px 18px;background:#2E8B57;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;">Approve</button></form>
    <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reject') }}" data-confirm="This seller application will be rejected. Continue?" data-confirm-title="Reject Application?" data-confirm-ok="Reject" data-confirm-class="btn-danger">@csrf<input type="hidden" name="reason" value="Registration requirements not met" /><button type="submit" style="padding:10px 18px;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;">Reject</button></form>
  </div>
  @endif
</div>

@if(session('status'))
<div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin-bottom:16px;border:1px solid #BFE3D0;">{{ session('status') }}</div>
@endif

<div class="grid-2">
  <!-- Left: Seller Information -->
  <div class="info-card">
    <h2>
      <svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
      Seller Information
    </h2>

    <div class="stat-row">
      <div class="stat-mini"><strong>{{ $seller->products->count() }}</strong><span>Products</span></div>
      <div class="stat-mini"><strong style="color:#16697A;">{{ $seller->status === 'approved' ? 'Verified' : ucfirst($seller->status) }}</strong><span>Status</span></div>
      <div class="stat-mini"><strong>{{ $seller->created_at->diffForHumans(null, true) }}</strong><span>On platform</span></div>
    </div>

    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg></div>
      <div>
        <div class="info-label">Store Name</div>
        <div class="info-value">{{ $seller->store_name }}</div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg></div>
      <div>
        <div class="info-label">Owner</div>
        <div class="info-value">{{ $seller->user->name ?? 'N/A' }}</div>
        <div style="font-size:12px; color:#6E6E73; margin-top:1px;">{{ $seller->user->email ?? '' }}</div>
      </div>
    </div>
    @if($seller->profile)
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.6 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.6c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.7 2z"/></svg></div>
      <div>
        <div class="info-label">Contact</div>
        <div class="info-value">{{ $seller->profile->contact_phone ?? '—' }}</div>
        <div style="font-size:12px; color:#6E6E73; margin-top:1px;">{{ $seller->profile->contact_email ?? $seller->user->email ?? '' }}</div>
      </div>
    </div>
    <div class="info-row">
      <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg></div>
      <div>
        <div class="info-label">Address</div>
        <div class="info-value" style="font-weight:500; line-height:1.5;">{{ $seller->profile->address ?? '—' }}</div>
      </div>
    </div>
    @endif

    <div class="theme-preview">
      <div style="width:44px; height:44px; border-radius:10px; background:{{ $seller->primary_color }}; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; flex-shrink:0;">Aa</div>
      <div style="flex:1; min-width:0;">
        <div style="font-size:13px; font-weight:700;">Store Theme</div>
        <div style="display:flex; gap:10px; margin-top:6px; align-items:center;">
          <span style="display:inline-flex; align-items:center; gap:6px; font-size:12px; background:#fff; border:1px solid #E8E6E0; padding:4px 8px; border-radius:999px;"><span class="theme-swatch" style="background:{{ $seller->primary_color }};"></span> {{ $seller->primary_color }}</span>
          <span style="display:inline-flex; align-items:center; gap:6px; font-size:12px; background:#fff; border:1px solid #E8E6E0; padding:4px 8px; border-radius:999px;"><span class="theme-swatch" style="background:{{ $seller->accent_color }};"></span> {{ $seller->accent_color }}</span>
        </div>
      </div>
    </div>

    @if($seller->profile && $seller->profile->description)
      <div style="margin-top:16px; padding:14px; background:#F8F9FA; border:1px solid #F1F2F4; border-radius:12px;">
        <div class="info-label" style="margin-bottom:6px;">About the Store</div>
        <div style="font-size:13.5px; color:#374151; line-height:1.6;">{{ $seller->profile->description }}</div>
      </div>
    @endif
  </div>

  <!-- Right: Products -->
  <div class="info-card">
    <h2>
      <svg viewBox="0 0 24 24"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Zm2 2v6h12v-6H6Z"/></svg>
      Products
      <span style="margin-left:auto; background:#F0FAFA; color:#374151; font-size:12px; font-weight:700; padding:4px 10px; border-radius:999px; border:1px solid #E8E6E0;">{{ $seller->products->count() }} items</span>
    </h2>

    @if($seller->products->count())
      <div class="product-grid">
        @foreach($seller->products as $product)
          <div class="prod-card">
            <div class="prod-thumb">
              <div class="prod-thumb-letter">{{ strtoupper(mb_substr($product->name,0,1)) }}</div>
              <span class="prod-status" style="background:{{ $product->status === 'active' ? '#E8F5EE' : '#F3F4F6' }}; color:{{ $product->status === 'active' ? '#166534' : '#6E6E73' }}; border:1px solid {{ $product->status === 'active' ? '#BFE3D0' : '#E5E7EB' }};">{{ ucfirst($product->status) }}</span>
            </div>
            <div class="prod-body">
              <div class="prod-name" title="{{ $product->name }}">{{ $product->name }}</div>
              <div class="prod-cat">{{ $product->category->name ?? 'General' }}</div>
              <div class="prod-price">₱{{ number_format($product->price, 2) }}</div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <div style="text-align:center; padding:40px 20px; color:#9CA3AF;">
        <div style="width:56px; height:56px; border-radius:50%; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
          <svg width="22" height="22" viewBox="0 0 24 24" stroke="#9CA3AF" fill="none" stroke-width="1.6"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
        </div>
        <div style="font-weight:600; color:#6B7280;">No products yet</div>
        <div style="font-size:13px; margin-top:4px;">This seller hasn't listed any products.</div>
      </div>
    @endif
  </div>
</div>

<div style="margin-top:18px;">
  <a href="{{ url('/admin/sellers') }}" style="display:inline-flex; align-items:center; gap:6px; color:#16697A; text-decoration:none; font-weight:600; font-size:13px;">
    <svg width="16" height="16" viewBox="0 0 24 24" stroke="#16697A" fill="none" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back to Sellers
  </a>
</div>
@endsection
