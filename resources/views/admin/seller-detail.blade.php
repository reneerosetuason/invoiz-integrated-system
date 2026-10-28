@extends('admin.layout')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/seller-detail.css') }}">

<div style="margin-bottom:14px;">
  <a href="{{ url('/admin/sellers') }}" style="display:inline-flex; align-items:center; gap:6px; color:#16697A; text-decoration:none; font-weight:700; font-size:13px;">
    <svg width="16" height="16" viewBox="0 0 24 24" stroke="#16697A" fill="none" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back to Sellers
  </a>
</div>

{{-- Top Hero Banner --}}
<div class="seller-hero">
  <div class="hero-logo">
    @if($seller->logo)
      <img src="{{ url('storage/'.ltrim($seller->logo,'/')) }}" alt="logo">
    @else
      {{ strtoupper(mb_substr($seller->display_name,0,1)) }}
    @endif
  </div>
  <div class="hero-info">
    <div class="hero-name">{{ $seller->display_name }}</div>
    <div class="hero-meta">
      <span>{{ $seller->user->email ?? 'No email' }}</span>
      <span>·</span>
      <span>Owner: <strong>{{ $seller->user ? $seller->user->displayName() : '—' }}</strong></span>
      <span>·</span>
      <span>Applied {{ $seller->created_at->format('M d, Y') }}</span>
      <span>·</span>
      <span>Seller #{{ $seller->id }}</span>
    </div>
  </div>

  @php
    $appStatus = $seller->approval_status ?? $seller->status;
    $statusColor = match($appStatus) {
      'approved' => '#10B981',
      'rejected' => '#EF4444',
      'pending' => '#F59E0B',
      default => '#6B7280'
    };
  @endphp
  <span class="hero-badge" style="background:rgba(255,255,255,.18); color:#fff; border-color:rgba(255,255,255,.35);">
    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:{{ $statusColor }};"></span>
    {{ ucfirst($appStatus) }} Application
  </span>

  @if($appStatus === 'pending')
  <div class="hero-actions">
    <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}">
      @csrf
      <button type="submit" style="padding:10px 18px;background:#10B981;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:800;font-size:13px;display:flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(16,185,129,.3);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        Approve Application
      </button>
    </form>
    <button type="button" onclick="document.getElementById('rejectModal').style.display='flex'" style="padding:10px 18px;background:rgba(239,68,68,.25);color:#fff;border:1px solid rgba(239,68,68,.6);border-radius:10px;cursor:pointer;font-weight:800;font-size:13px;display:flex;align-items:center;gap:6px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      Reject Application
    </button>
  </div>
  @endif
</div>

@if($appStatus === 'rejected' && $seller->rejection_reason)
  <div style="padding:14px 18px;background:#FEF2F2;color:#991B1B;border-radius:12px;margin-bottom:18px;border:1px solid #FECACA;">
    <strong>Rejection reason recorded:</strong> {{ $seller->rejection_reason }}
  </div>
@endif

@if(session('status'))
  <div style="padding:14px 18px;background:#E8F5EE;color:#166534;border-radius:12px;margin-bottom:18px;border:1px solid #BFE3D0;font-weight:700;display:flex;align-items:center;gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    {{ session('status') }}
  </div>
@endif

<div class="grid-2">
  {{-- Left Column: Personal, Business, and Address Info --}}
  <div>
    {{-- 1. Personal Information --}}
    <div class="info-card">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Applicant / Personal Information
      </h2>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div>
          <div class="info-label">First Name</div>
          <div class="info-value">{{ $seller->user->first_name ?: ($seller->user ? strtok($seller->user->name, ' ') : '—') }}</div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div>
          <div class="info-label">Last Name</div>
          <div class="info-value">{{ $seller->user->last_name ?: ($seller->user ? substr(strstr($seller->user->name, ' '), 1) : '—') }}</div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <div>
          <div class="info-label">Email</div>
          <div class="info-value">{{ $seller->user->email ?? '—' }}</div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.6 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.6c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.7 2z"/></svg></div>
        <div>
          <div class="info-label">Mobile Number</div>
          <div class="info-value">{{ $seller->user->phone ?? $seller->profile->contact_phone ?? '—' }}</div>
        </div>
      </div>
    </div>

    {{-- 2. Store & Business Details --}}
    <div class="info-card">
      <h2>
        <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Shop & Business Details
      </h2>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
        <div>
          <div class="info-label">Shop Name</div>
          <div class="info-value">{{ $seller->display_name }}</div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
        <div>
          <div class="info-label">Shop Category</div>
          <div class="info-value">
            <span class="badge-tag" style="background:#EBF5F5; color:#16697A; border:1px solid #D1EBEB;">
              {{ $seller->line_of_business ?: 'Home & Living' }}
            </span>
          </div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
        <div>
          <div class="info-label">Seller Type</div>
          <div class="info-value">
            <span class="badge-tag" style="background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;">
              {{ $seller->seller_type ?: 'Sole Proprietorship' }}
            </span>
          </div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div>
          <div class="info-label">TIN (Tax Identification Number)</div>
          <div class="info-value" style="font-family:monospace; font-size:15px; color:#0E4A57;">
            {{ $seller->tin ?: '—' }}
          </div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-icon"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div>
          <div class="info-label">Terms & Privacy Policy</div>
          <div class="info-value" style="color:#059669; display:flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Ticked & Accepted
          </div>
        </div>
      </div>
    </div>

    {{-- 3. Business Address --}}
    <div class="info-card">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M21 10c0 6-9 12-9 12S3 16 3 10a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
        Business Address
      </h2>
      <div class="info-row">
        <div>
          <div class="info-label">Full Address</div>
          <div class="info-value" style="font-weight:700; color:#1F2937; line-height:1.5;">
            {{ $seller->fullAddress() ?: '—' }}
          </div>
        </div>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">Street Address</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->address_line ?? $seller->user?->address_line ?? '—' }}</div>
        </div>
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">Barangay</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->barangay ?? $seller->user?->barangay ?? '—' }}</div>
        </div>
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">City / Municipality</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->city ?? $seller->user?->city ?? $seller->user?->municipality ?? '—' }}</div>
        </div>
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">Province</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->province ?? $seller->user?->province ?? '—' }}</div>
        </div>
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">Region</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->region ?? $seller->user?->region ?? '—' }}</div>
        </div>
        <div style="padding:10px 12px; background:#F9FAFB; border-radius:10px; border:1px solid #F3F4F6;">
          <div class="info-label">Postal Code</div>
          <div class="info-value" style="font-size:13px;">{{ $seller->postal_code ?? $seller->user?->postal_code ?? '—' }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Right Column: Uploaded Documents & Decision Actions --}}
  <div>
    {{-- Verification Documents Card --}}
    <div class="info-card">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Verification Documents (Submitted Uploads)
      </h2>

      @php
        $hasId = !empty($seller->id_image) || !empty($seller->user?->id_image);
        $hasDti = !empty($seller->dti_certificate) || !empty($seller->business_permit);
        $hasBir = !empty($seller->bir_2303);

        $idUrl = $hasId ? url('/admin/sellers/'.$seller->id.'/document/id') : null;
        $dtiUrl = $hasDti ? url('/admin/sellers/'.$seller->id.'/document/dti') : null;
        $birUrl = $hasBir ? url('/admin/sellers/'.$seller->id.'/document/bir') : null;
      @endphp

      {{-- 1. Government-issued ID --}}
      <div class="doc-card">
        <div class="doc-header">
          <div>
            <div class="doc-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Government-issued ID
            </div>
            <div style="font-size:11px; color:#6B7280; margin-top:2px;">
              {{ $seller->id_image ? basename($seller->id_image) : 'No file uploaded' }}
            </div>
          </div>
          @if($idUrl)
            <a href="{{ $idUrl }}" target="_blank" class="doc-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View Document
            </a>
          @else
            <span style="font-size:11px; color:#9CA3AF; font-weight:700;">Missing</span>
          @endif
        </div>
        @if($idUrl && !str_ends_with(strtolower($seller->id_image ?? ''), '.pdf'))
          <div style="margin-top:10px;">
            <a href="{{ $idUrl }}" target="_blank">
              <img src="{{ $idUrl }}" alt="Govt ID" class="doc-preview-img" onerror="this.style.display='none'">
            </a>
          </div>
        @elseif($idUrl)
          <div style="margin-top:8px; font-size:12px; color:#16697A; font-weight:600;">
            📄 PDF Document uploaded · <a href="{{ $idUrl }}" target="_blank" style="text-decoration:underline;">Click to open PDF</a>
          </div>
        @endif
      </div>

      {{-- 2. DTI Certificate --}}
      <div class="doc-card">
        <div class="doc-header">
          <div>
            <div class="doc-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              DTI Certificate
            </div>
            <div style="font-size:11px; color:#6B7280; margin-top:2px;">
              {{ $seller->dti_certificate ? basename($seller->dti_certificate) : ($seller->business_permit ? basename($seller->business_permit) : 'No file uploaded') }}
            </div>
          </div>
          @if($dtiUrl)
            <a href="{{ $dtiUrl }}" target="_blank" class="doc-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View Document
            </a>
          @else
            <span style="font-size:11px; color:#9CA3AF; font-weight:700;">Missing</span>
          @endif
        </div>
        @if($dtiUrl && !str_ends_with(strtolower($seller->dti_certificate ?? $seller->business_permit ?? ''), '.pdf'))
          <div style="margin-top:10px;">
            <a href="{{ $dtiUrl }}" target="_blank">
              <img src="{{ $dtiUrl }}" alt="DTI" class="doc-preview-img" onerror="this.style.display='none'">
            </a>
          </div>
        @elseif($dtiUrl)
          <div style="margin-top:8px; font-size:12px; color:#16697A; font-weight:600;">
            📄 PDF Document uploaded · <a href="{{ $dtiUrl }}" target="_blank" style="text-decoration:underline;">Click to open PDF</a>
          </div>
        @endif
      </div>

      {{-- 3. BIR Form 2303 --}}
      <div class="doc-card">
        <div class="doc-header">
          <div>
            <div class="doc-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="10 9 9 9 8 9"/></svg>
              BIR Form 2303
            </div>
            <div style="font-size:11px; color:#6B7280; margin-top:2px;">
              {{ $seller->bir_2303 ? basename($seller->bir_2303) : 'No file uploaded' }}
            </div>
          </div>
          @if($birUrl)
            <a href="{{ $birUrl }}" target="_blank" class="doc-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View Document
            </a>
          @else
            <span style="font-size:11px; color:#9CA3AF; font-weight:700;">Missing</span>
          @endif
        </div>
        @if($birUrl && !str_ends_with(strtolower($seller->bir_2303 ?? ''), '.pdf'))
          <div style="margin-top:10px;">
            <a href="{{ $birUrl }}" target="_blank">
              <img src="{{ $birUrl }}" alt="BIR 2303" class="doc-preview-img" onerror="this.style.display='none'">
            </a>
          </div>
        @elseif($birUrl)
          <div style="margin-top:8px; font-size:12px; color:#16697A; font-weight:600;">
            📄 PDF Document uploaded · <a href="{{ $birUrl }}" target="_blank" style="text-decoration:underline;">Click to open PDF</a>
          </div>
        @endif
      </div>
    </div>

    {{-- Administrator Decision Actions Card --}}
    <div class="info-card" style="border:2px solid #E5E7EB;">
      <h2>
        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Administrator Decision
      </h2>

      <div style="margin-bottom:14px; font-size:13.5px; color:#4B5563; line-height:1.5;">
        Current Status: <strong>{{ ucfirst($seller->approval_status ?? $seller->status) }}</strong>
        @if(($seller->approval_status ?? '') === 'pending')
          <p style="margin-top:4px; font-size:13px; color:#B45309;">
            This seller is waiting for approval. Review their submitted details, TIN, and documents above before deciding.
          </p>
        @endif
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        @if(($seller->approval_status ?? '') === 'pending' || ($seller->approval_status ?? '') === 'rejected')
          <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" style="flex:1;">
            @csrf
            <button type="submit" style="width:100%; padding:12px 20px; background:#10B981; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              Approve Seller
            </button>
          </form>
        @endif

        @if(($seller->approval_status ?? '') === 'pending' || ($seller->approval_status ?? '') === 'approved')
          <button type="button" onclick="document.getElementById('rejectModal').style.display='flex'" style="flex:1; padding:12px 20px; background:#EF4444; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Reject Application
          </button>
        @endif

        @if(($seller->status ?? '') === 'active' && ($seller->approval_status ?? '') === 'approved')
          <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/suspend') }}" style="flex:1;">
            @csrf
            <button type="submit" style="width:100%; padding:12px 20px; background:#F59E0B; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13.5px; cursor:pointer;">
              Suspend Store
            </button>
          </form>
        @elseif(($seller->status ?? '') === 'inactive' && ($seller->approval_status ?? '') === 'approved')
          <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reinstate') }}" style="flex:1;">
            @csrf
            <button type="submit" style="width:100%; padding:12px 20px; background:#10B981; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13.5px; cursor:pointer;">
              Reinstate Store
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Reject Reason Modal --}}
<div id="rejectModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
  <div style="background:#fff; border-radius:16px; width:100%; max-width:440px; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,.2);">
    <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0 0 8px;">Reject Seller Application</h3>
    <p style="font-size:13px; color:#6B7280; margin:0 0 16px;">
      Please provide a reason for rejecting <strong>{{ $seller->display_name }}</strong>. This will be logged and emailed to {{ $seller->user->email ?? 'the applicant' }}.
    </p>
    <form method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reject') }}">
      @csrf
      <textarea name="reason" rows="3" required minlength="5" maxlength="1000" placeholder="e.g. Incomplete business documents or invalid TIN..." style="width:100%; border:1.5px solid #D1D5DB; border-radius:10px; padding:10px 12px; font-size:13.5px; outline:none; resize:none;">{{ old('reason') }}</textarea>
      @error('reason')<p style="margin:6px 0 0;color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
        <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" style="padding:9px 16px; border:1px solid #D1D5DB; background:#fff; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">Cancel</button>
        <button type="submit" style="padding:9px 18px; background:#EF4444; color:#fff; border:none; border-radius:8px; font-weight:800; font-size:13px; cursor:pointer;">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>
@if($errors->has('reason'))
<script src="{{ asset('assets/admin/js/seller-detail.js') }}"></script>
@endif
@endsection
