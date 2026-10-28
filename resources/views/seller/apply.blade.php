<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply as Seller — INVOIZ</title>
<link rel="icon" href="{{ asset('assets/shared/images/logo.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script src="{{ asset('assets/seller/js/apply.js') }}"></script>
<link rel="stylesheet" href="{{ asset('assets/seller/css/apply.css') }}">
</head>
<body class="min-h-screen p-4 sm:p-8">
<div class="mx-auto w-full max-w-2xl overflow-hidden rounded-[2rem] bg-white shadow-soft ring-1 ring-black/5">
  <div class="bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-8 text-white">
    <div class="flex items-center gap-2">
      <img src="{{ asset('assets/shared/images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl bg-white object-cover p-1">
      <span class="text-lg font-extrabold tracking-tight">INVOIZ Seller</span>
    </div>
    <h1 class="mt-4 text-2xl font-extrabold">Seller Application</h1>
    <p class="mt-1 text-sm text-white/80">Submit your store and business verification details. After review, administrator approval will be sent to your email.</p>
  </div>

  <div class="p-6 sm:p-8">
    @if(session('success'))
      <div class="mb-5 rounded-2xl bg-emerald-50 px-4 py-3.5 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200">
        {{ session('success') }}
      </div>
    @endif
    @if(session('error'))
      <div class="mb-5 rounded-2xl bg-red-50 px-4 py-3.5 text-sm font-semibold text-red-800 ring-1 ring-red-200">
        {{ session('error') }}
      </div>
    @endif
    @if($errors->any())
      <div class="mb-5 rounded-2xl bg-red-50 px-4 py-3.5 text-sm font-semibold text-red-800 ring-1 ring-red-200">
        <ul class="list-disc pl-5 space-y-1">
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if(!empty($existing) && $existing->approval_status === 'pending')
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm">
        <div class="flex items-center gap-2.5">
          <span class="flex h-3 w-3 rounded-full bg-amber-500 animate-pulse"></span>
          <p class="text-base font-extrabold text-amber-900">Application Under Review</p>
        </div>
        <p class="mt-2 text-amber-800">
          You applied as <strong class="font-bold">{{ $existing->business_name }}</strong> ({{ $existing->line_of_business }}).
        </p>
        <div class="mt-3 rounded-xl bg-white/70 p-3.5 border border-amber-200/80 text-xs text-amber-900 space-y-1">
          <p><strong>Seller Type:</strong> {{ $existing->seller_type ?: 'Sole Proprietorship' }}</p>
          <p><strong>TIN:</strong> {{ $existing->tin ?: '—' }}</p>
          <p><strong>Address:</strong> {{ $existing->fullAddress() ?: '—' }}</p>
        </div>
        <p class="mt-3 text-xs text-amber-700">Please wait for the administrator's review. Decision notifications are sent to <strong>{{ $user->email ?? 'your email' }}</strong>.</p>
        <div class="mt-4 flex gap-2">
          <a href="{{ url('/') }}" class="rounded-xl bg-[#16697A] px-4 py-2 text-xs font-bold text-white hover:bg-[#0E4A57]">Back to Marketplace</a>
        </div>
      </div>
    @elseif(!empty($existing) && $existing->isApproved())
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-sm">
        <div class="flex items-center gap-2">
          <span class="text-xl">🎉</span>
          <p class="text-base font-extrabold text-emerald-900">You're an Approved Seller</p>
        </div>
        <p class="mt-2 text-emerald-800">Your store <strong class="font-bold">{{ $existing->business_name }}</strong> is approved and active.</p>
        <div class="mt-4 flex flex-wrap gap-2.5">
          <a href="{{ url('/seller/dashboard') }}" class="rounded-xl bg-[#16697A] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#0E4A57]">Open Seller Dashboard</a>
          <a href="{{ url('/shop') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-bold hover:bg-gray-50">Continue as Buyer</a>
        </div>
      </div>
    @else
      @if(!empty($existing) && $existing->approval_status === 'rejected')
        <div class="mb-5 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-800 ring-1 ring-red-200">
          Your previous application was not approved. Please review and update your information below and resubmit.
          @if($existing->rejection_reason)
            <p class="mt-2 font-normal"><strong>Reason:</strong> {{ $existing->rejection_reason }}</p>
          @endif
        </div>
      @endif

      <form method="POST" action="{{ url('/sell') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Personal Information --}}
        <div class="section-card">
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-teal-dark">1. Personal Information</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
              <label class="lbl">First Name <span class="req">*</span></label>
              <input name="first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required maxlength="100" class="inp" placeholder="e.g. Maria Clara">
            </div>
            <div>
              <label class="lbl">Last Name <span class="req">*</span></label>
              <input name="last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required maxlength="100" class="inp" placeholder="e.g. Santos">
            </div>
            <div>
              <label class="lbl">Email <span class="req">*</span></label>
              <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="inp @if(!empty($user)) bg-gray-50 @endif" placeholder="e.g. mariaclara.santos@example.com" @if(!empty($user)) readonly @endif>
            </div>
            <div>
              <label class="lbl">Mobile Number <span class="req">*</span></label>
              <input name="phone" value="{{ old('phone', $user->phone ?? '') }}" required maxlength="30" class="inp" placeholder="e.g. 09189876543">
            </div>

            @if(empty($user))
            <div>
              <label class="lbl">Password <span class="req">*</span></label>
              <input type="password" name="password" required minlength="8" class="inp" placeholder="Min. 8 characters">
            </div>
            <div>
              <label class="lbl">Confirm Password <span class="req">*</span></label>
              <input type="password" name="password_confirmation" required minlength="8" class="inp" placeholder="Repeat password">
            </div>
            @endif
          </div>
        </div>

        {{-- Store Details --}}
        <div class="section-card">
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-teal-dark">2. Shop & Business Information</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
              <label class="lbl">Store Name <span class="req">*</span></label>
              <input name="business_name" id="store-name" value="{{ old('business_name', $existing->business_name ?? $existing->store_name ?? '') }}" required minlength="2" maxlength="50" title="Use 2–50 characters. Letters, numbers, spaces, and common business punctuation are allowed." class="inp" placeholder="e.g. Maria Clara Handicrafts" aria-describedby="store-name-help">
              <p id="store-name-help" class="mt-1 text-xs text-gray-500">2–50 characters. Letters, numbers, spaces, and common business punctuation are allowed.</p>
            </div>
            <div>
              <label class="lbl">Shop Category <span class="req">*</span></label>
              <select name="line_of_business" required class="inp">
                <option value="">Select Category…</option>
                @php
                  $preCats = ['Home & Living', 'Fashion', 'Electronics', 'Beauty & Health', 'Sports & Outdoors', 'Toys & Hobbies', 'Groceries', 'Books'];
                  $dbCats = isset($categories) ? $categories->pluck('name')->toArray() : [];
                  $allCats = array_values(array_unique(array_merge($preCats, $dbCats)));
                  $selectedCat = old('line_of_business', $existing->line_of_business ?? '');
                @endphp
                @foreach($allCats as $catName)
                  <option value="{{ $catName }}" @selected($selectedCat === $catName)>{{ $catName }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="lbl">Seller Type <span class="req">*</span></label>
              <select name="seller_type" required class="inp">
                <option value="">Select Seller Type…</option>
                @php
                  $types = ['Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative', 'One Person Corporation (OPC)'];
                  $selectedType = old('seller_type', $existing->seller_type ?? 'Sole Proprietorship');
                @endphp
                @foreach($types as $st)
                  <option value="{{ $st }}" @selected($selectedType === $st)>{{ $st }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="lbl">TIN (Tax Identification Number) <span class="req">*</span></label>
              <input name="tin" id="seller-tin" value="{{ old('tin', preg_replace('/\D+/', '', (string) ($existing->tin ?? ''))) }}" required minlength="12" maxlength="12" pattern="[0-9]{12}" inputmode="numeric" class="inp" placeholder="12 digits, numbers only">
            </div>
            <div class="sm:col-span-2">
              <label class="lbl">Shop Logo / Picture (Optional)</label>
              <input type="file" name="logo" accept="image/*" class="inp"
                     data-photo-editor data-photo-aspect="1" data-photo-preview-key="seller-application-logo">
              <div data-photo-preview-target-key="seller-application-logo" hidden class="mt-3 h-16 w-16 overflow-hidden rounded-xl border border-gray-200 bg-gray-50" aria-label="Edited logo preview"></div>
              <p class="mt-1 text-xs text-gray-500">JPG or PNG. Maximum 5 MB.</p>
              @if(!empty($existing?->logo))
                <p class="text-xs text-gray-500 mt-1">Current: <a href="{{ $existing->logoUrl() }}" target="_blank" class="text-teal font-semibold underline">View existing logo</a></p>
              @endif
            </div>
          </div>
        </div>

        {{-- Address Details --}}
        <div class="section-card">
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-teal-dark">3. Business Address</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            @include('shared.partials.psgc-address-fields', [
              'prefix' => '',
              'uid' => 'seller_address',
              'values' => [
                'region' => old('region', $existing->region ?? $user->region ?? ''),
                'province' => old('province', $existing->province ?? $user->province ?? ''),
                'city' => old('city', $existing->city ?? $user->city ?? $user->municipality ?? ''),
                'barangay' => old('barangay', $existing->barangay ?? $user->barangay ?? ''),
              ],
              'controlClass' => 'inp',
              'labelClass' => 'lbl',
              'fields' => ['region', 'province', 'city', 'barangay'],
              'required' => ['region', 'province', 'city', 'barangay'],
              'regionFromProvince' => true,
            ])
            <div>
              <label class="lbl">Street Address <span class="req">*</span></label>
              <input name="address_line" value="{{ old('address_line', $existing->address_line ?? $user->address_line ?? '') }}" required maxlength="255" class="inp" placeholder="e.g. Blk 12 Lot 8, Sampaguita St.">
            </div>
            <div>
              <label class="lbl">Postal Code <span class="req">*</span></label>
              <input name="postal_code" id="seller-postal-code" value="{{ old('postal_code', $existing->postal_code ?? $user->postal_code ?? '') }}" required maxlength="4" pattern="[0-9]{4}" inputmode="numeric" class="inp" placeholder="e.g. 1121">
            </div>
          </div>
        </div>

        {{-- Document Uploads --}}
        <div class="section-card">
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-teal-dark">4. Verification Documents</h2>
          </div>
          <div class="space-y-4">
            <div>
              <label class="lbl">Government-issued ID (Upload) <span class="req">*</span></label>
              <input type="file" name="id_image" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" class="inp" @if(empty($availableDocuments['id_image'])) required @endif>
              <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                <span>JPG, PNG, or PDF. Maximum 5 MB.</span>
                @if(!empty($existing?->id_image) && !empty($availableDocuments['id_image']))
                  <a href="{{ $existing->idImageUrl() }}" target="_blank" class="text-teal font-bold underline">View Uploaded ID ({{ basename($existing->id_image) }})</a>
                @elseif(!empty($user?->id_image) && !empty($availableDocuments['id_image']))
                  <a href="{{ url('storage/'.ltrim($user->id_image, '/')) }}" target="_blank" class="text-teal font-bold underline">View Uploaded ID ({{ basename($user->id_image) }})</a>
                @elseif(!empty($existing?->id_image) || !empty($user?->id_image))
                  <span class="text-red-700">The saved ID is missing or exceeds 5 MB. Upload a replacement.</span>
                @endif
              </div>
            </div>

            <div>
              <label class="lbl">DTI Certificate (Upload) <span class="req">*</span></label>
              <input type="file" name="dti_certificate" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" class="inp" @if(empty($availableDocuments['dti_certificate'])) required @endif>
              <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                <span>JPG, PNG, or PDF. Maximum 5 MB.</span>
                @if(!empty($existing?->dti_certificate) && !empty($availableDocuments['dti_certificate']))
                  <a href="{{ $existing->dtiCertificateUrl() }}" target="_blank" class="text-teal font-bold underline">View Uploaded DTI ({{ basename($existing->dti_certificate) }})</a>
                @elseif(!empty($existing?->dti_certificate))
                  <span class="text-red-700">The saved file is missing or exceeds 5 MB. Upload a replacement.</span>
                @endif
              </div>
            </div>

            <div>
              <label class="lbl">BIR Form 2303 (Upload) <span class="req">*</span></label>
              <input type="file" name="bir_2303" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" class="inp" @if(empty($availableDocuments['bir_2303'])) required @endif>
              <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                <span>JPG, PNG, or PDF. Maximum 5 MB.</span>
                @if(!empty($existing?->bir_2303) && !empty($availableDocuments['bir_2303']))
                  <a href="{{ $existing->bir2303Url() }}" target="_blank" class="text-teal font-bold underline">View Uploaded BIR 2303 ({{ basename($existing->bir_2303) }})</a>
                @elseif(!empty($existing?->bir_2303))
                  <span class="text-red-700">The saved file is missing or exceeds 5 MB. Upload a replacement.</span>
                @endif
              </div>
            </div>
          </div>
        </div>

        {{-- Terms and Privacy Policy --}}
        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms', false)) class="mt-0.5 h-4 w-4 rounded text-teal accent-[#16697A]">
            <span class="text-xs sm:text-sm text-gray-700 leading-snug">
              I certify that all provided details and documents are authentic. I agree to the
              <a href="{{ url('/help') }}" target="_blank" class="font-bold text-teal underline">Terms of Service</a>
              and
              <a href="{{ url('/help') }}" target="_blank" class="font-bold text-teal underline">Privacy Policy</a>.
            </span>
          </label>
        </div>

        <button type="submit" class="btn-primary w-full rounded-2xl py-3.5 text-sm font-extrabold text-white shadow-lg">
          Submit Application →
        </button>
        <p class="text-center text-xs text-gray-500">
          After submitting, please wait for administrator approval, which will be sent to your email.
        </p>
      </form>
    @endif

    <p class="mt-6 text-center text-xs text-gray-500">
      <a href="{{ url('/') }}" class="hover:text-teal font-semibold">← Back to Marketplace</a>
    </p>
  </div>
</div>
@if(empty($existing) || ($existing->approval_status ?? null) === 'rejected')
<script src="{{ asset('assets/seller/js/apply-2.js') }}"></script>
@endif
@include('seller.partials.photo-editor')
</body>
</html>
