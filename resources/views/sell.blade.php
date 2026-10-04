<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sell on INVOIZ</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F8FAF9] min-h-screen p-4">
<div class="mx-auto w-full max-w-xl bg-white rounded-3xl shadow-lg p-8 mt-8">
  <div class="flex items-center gap-2 justify-center">
    <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-10 w-10 rounded-xl object-cover">
    <span class="text-xl font-extrabold text-[#0E4A57]">INVOIZ Seller</span>
  </div>
  <h1 class="text-center text-xl font-extrabold mt-4">Sell on INVOIZ</h1>
  <p class="text-center text-sm text-gray-500 mt-1">Reach local buyers. Admins review applications in Seller Management.</p>
  @if(session('success'))<div class="mt-4 bg-emerald-50 text-emerald-700 text-sm p-3 rounded-xl">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-xl">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-xl">{{ $errors->first() }}</div>@endif
  @if(!empty($existing))
    <div class="mt-6 rounded-2xl border p-4 text-sm">
      <p class="font-extrabold">{{ $existing->business_name ?? $existing->store_name }}</p>
      <p class="text-gray-500 mt-1">Status: <b class="uppercase">{{ $existing->approval_status }}</b></p>
      @if($existing->isApproved())
        <a href="{{ url('/seller/dashboard') }}" class="mt-3 inline-flex min-h-[44px] items-center rounded-xl bg-[#16697A] px-5 text-sm font-bold text-white">Open Seller Dashboard</a>
      @else
        <p class="text-gray-500 mt-2 text-xs">Pending review — you'll be able to enter the dashboard once approved.</p>
      @endif
    </div>
  @else
    <form method="POST" action="{{ url('/sell') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
      @csrf
      <div><label class="text-xs font-bold text-gray-500">Business / store name</label>
      <input name="business_name" value="{{ old('business_name') }}" required maxlength="150" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
      <div><label class="text-xs font-bold text-gray-500">Line of business</label>
      <input name="line_of_business" value="{{ old('line_of_business') }}" required maxlength="100" placeholder="e.g. Fashion & Accessories" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#16697A]"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-bold text-gray-500">Valid ID (optional)</label><input type="file" name="id_image" accept="image/*" class="mt-1 w-full text-xs"></div>
        <div><label class="text-xs font-bold text-gray-500">Business permit (optional)</label><input type="file" name="business_permit" accept="image/*" class="mt-1 w-full text-xs"></div>
      </div>
      <div><label class="text-xs font-bold text-gray-500">Shop picture / logo (optional — shown on your store)</label><input type="file" name="logo" accept="image/*" class="mt-1 w-full text-xs"></div>
      <button class="w-full rounded-2xl bg-[#16697A] text-white font-extrabold py-3 text-sm hover:bg-[#0E4A57]">Submit Application</button>
    </form>
  @endif
  <p class="text-center text-xs text-gray-400 mt-4"><a href="{{ url('/') }}">← Back to marketplace</a></p>
</div>
</body>
</html>
