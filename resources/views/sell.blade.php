<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply as Seller — INVOIZ</title>
<link rel="icon" href="{{ asset('images/logo.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  colors: { teal: { DEFAULT:'#16697A', dark:'#0E4A57', light:'#EAF4F3' }, amber: { DEFAULT:'#F0A202', light:'#FFF8E1' } },
  fontFamily: { sans:['Segoe UI','system-ui','-apple-system','sans-serif'] },
  boxShadow: { soft:'0 24px 60px rgba(14,74,87,.14)' },
}}};
</script>
<style>
  body{background:#F8FAF9;font-family:'Segoe UI',system-ui,-apple-system,sans-serif}
  .lbl{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;margin-bottom:6px}
  .inp{width:100%;border-radius:.9rem;border:1.5px solid #E5E7EB;background:#fff;padding:.7rem 1rem;font-size:.88rem;outline:none;transition:border-color .2s, box-shadow .2s}
  .inp:focus{border-color:#16697A;box-shadow:0 0 0 4px rgba(22,105,122,.12)}
  .req{color:#DC2626}
  .btn-primary{background:linear-gradient(135deg,#16697A,#0E4A57);transition:transform .2s,filter .2s}
  .btn-primary:hover{transform:translateY(-1px);filter:brightness(1.06)}
</style>
</head>
<body class="min-h-screen p-4 sm:p-8">
<div class="mx-auto w-full max-w-2xl overflow-hidden rounded-[2rem] bg-white shadow-soft ring-1 ring-black/5">
  <div class="bg-gradient-to-br from-teal via-teal-dark to-[#0B3B46] p-8 text-white">
    <div class="flex items-center gap-2">
      <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" alt="INVOIZ" class="h-9 w-9 rounded-xl bg-white object-cover p-1">
      <span class="text-lg font-extrabold">INVOIZ Seller</span>
    </div>
    <h1 class="mt-4 text-2xl font-extrabold">Seller application</h1>
    <p class="mt-1 text-sm text-white/75">Fill this out once. After submitting, please wait for the administrator's approval, which will be sent to your email.</p>
  </div>

  <div class="p-6 sm:p-8">
    @if(session('success'))<div class="mb-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">{{ $errors->first() }}</div>@endif

    @if(!empty($existing) && $existing->approval_status === 'pending')
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm">
        <p class="font-extrabold text-amber-800">Application under review</p>
        <p class="mt-1 text-amber-700">You applied as <b>{{ $existing->business_name }}</b>. Please wait for the administrator's approval, which will be sent to <b>{{ $user->email }}</b>.</p>
      </div>
    @elseif(!empty($existing) && $existing->isApproved())
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm">
        <p class="font-extrabold text-emerald-800">You're an approved seller 🎉</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <a href="{{ url('/seller/dashboard') }}" class="rounded-xl bg-[#16697A] px-5 py-2.5 text-sm font-bold text-white">Open Seller Dashboard</a>
          <a href="{{ url('/shop') }}" class="rounded-xl border px-5 py-2.5 text-sm font-bold">Continue as Buyer</a>
        </div>
      </div>
    @else
      @if(!empty($existing) && $existing->approval_status === 'rejected')
        <div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-100">Your previous application wasn't approved. You may fix your details below and resubmit.</div>
      @endif
      <form method="POST" action="{{ url('/sell') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <div>
          <h2 class="text-sm font-extrabold uppercase tracking-wider text-teal-dark">Personal information</h2>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <div><label class="lbl">Last name <span class="req">*</span></label><input name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100" class="inp"></div>
            <div><label class="lbl">First name <span class="req">*</span></label><input name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100" class="inp"></div>
            <div><label class="lbl">Middle initial</label><input name="middle_initial" value="{{ old('middle_initial', $user->middle_initial) }}" maxlength="10" class="inp"></div>
            <div><label class="lbl">Sex <span class="req">*</span></label>
              <select name="sex" required class="inp">
                @foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l)
                  <option value="{{ $v }}" @selected(old('sex', $user->sex)===$v)>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div><label class="lbl">E-mail <span class="req">*</span></label><input type="email" value="{{ $user->email }}" readonly class="inp bg-gray-50"></div>
            <div><label class="lbl">Contact No. <span class="req">*</span></label><input name="phone" value="{{ old('phone', $user->phone) }}" required maxlength="30" class="inp" placeholder="09xx xxx xxxx"></div>
            <div><label class="lbl">Birthday <span class="req">*</span></label><input type="date" id="birthday" name="birthday" value="{{ old('birthday', $user->birthday ? $user->birthday->format('Y-m-d') : '') }}" required max="{{ date('Y-m-d', strtotime('-1 day')) }}" class="inp"></div>
            <div><label class="lbl">Age (auto)</label><input id="age" value="" readonly class="inp bg-gray-50" placeholder="—"></div>
          </div>
        </div>

        <div>
          <h2 class="text-sm font-extrabold uppercase tracking-wider text-teal-dark">Address</h2>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <div><label class="lbl">Province</label><select id="province" name="province" class="inp" data-selected="{{ old('province', $user->province) }}"><option value="">Loading…</option></select></div>
            <div><label class="lbl">Municipality / City</label><select id="municipality" name="municipality" class="inp" data-selected="{{ old('municipality', $user->municipality) }}"><option value="">Select province first</option></select></div>
            <div><label class="lbl">Barangay</label><select id="barangay" name="barangay" class="inp" data-selected="{{ old('barangay', $user->barangay) }}"><option value="">Select municipality first</option></select></div>
            <div><label class="lbl">Street, house no., etc.</label><input name="address_line" value="{{ old('address_line', $user->address_line) }}" maxlength="255" placeholder="123 Mabini St, Blk 4 Lot 9" class="inp"></div>
          </div>
          <p id="addrFallback" class="mt-2 hidden text-xs text-amber-700">Address list couldn't load — type your address manually below.</p>
        </div>

        <div>
          <h2 class="text-sm font-extrabold uppercase tracking-wider text-teal-dark">Store</h2>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <div><label class="lbl">Business name</label><input name="business_name" value="{{ old('business_name', $existing->business_name ?? '') }}" required maxlength="150" class="inp"></div>
            <div><label class="lbl">Line of business (category)</label>
              <select name="line_of_business" required class="inp">
                <option value="">Select…</option>
                @foreach($categories as $c)
                  <option value="{{ $c->name }}" @selected(old('line_of_business', $existing->line_of_business ?? '')===$c->name)>{{ $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-span-2"><label class="lbl">Shop picture (optional)</label><input type="file" name="logo" accept="image/*" class="inp"></div>
          </div>
        </div>

        <div>
          <h2 class="text-sm font-extrabold uppercase tracking-wider text-teal-dark">Documents</h2>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <div><label class="lbl">Upload ID</label><input type="file" name="id_image" accept="image/*" class="inp"></div>
            <div><label class="lbl">Upload business permit</label><input type="file" name="business_permit" accept="image/*" class="inp"></div>
          </div>
        </div>

        <button class="btn-primary w-full rounded-2xl py-3.5 text-sm font-extrabold text-white">Submit Application →</button>
        <p class="text-center text-xs text-gray-400">After submitting, please wait for the administrator's approval, which will be sent to your email.</p>
      </form>
    @endif
    <p class="mt-4 text-center text-xs text-gray-400"><a href="{{ url('/') }}" class="hover:text-teal-dark">← Back to marketplace</a></p>
  </div>
</div>

<script>
// Age auto-compute
(function(){
  var b = document.getElementById('birthday'), a = document.getElementById('age');
  function calc(){
    if(!b || !b.value){ if(a) a.value=''; return; }
    var bd = new Date(b.value), now = new Date(), age = now.getFullYear() - bd.getFullYear();
    var m = now.getMonth() - bd.getMonth();
    if(m < 0 || (m === 0 && now.getDate() < bd.getDate())) age--;
    if(a) a.value = age >= 0 ? age : '';
  }
  if(b){ b.addEventListener('change', calc); calc(); }
})();
// Philippine address dropdowns (PSGC API) with manual fallback
(function(){
  var prov = document.getElementById('province'),
      mun = document.getElementById('municipality'),
      brgy = document.getElementById('barangay');
  if(!prov) return;
  function fill(sel, items, val, ph){
    sel.innerHTML = '<option value="">' + ph + '</option>';
    items.forEach(function(it){
      var o = document.createElement('option');
      o.value = it.name; o.textContent = it.name; o.dataset.code = it.code;
      if(val && val === it.name) o.selected = true;
      sel.appendChild(o);
    });
  }
  function toInputs(){
    [['province','Province'],['municipality','Municipality / City'],['barangay','Barangay']].forEach(function(pair){
      var sel = document.getElementById(pair[0]);
      var inp = document.createElement('input');
      inp.name = sel.name; inp.value = sel.dataset.selected || '';
      inp.placeholder = pair[1]; inp.className = sel.className;
      sel.replaceWith(inp);
    });
    document.getElementById('addrFallback').classList.remove('hidden');
  }
  function get(url, ok, fail){
    fetch(url).then(function(r){ if(!r.ok) throw 0; return r.json(); }).then(ok).catch(fail);
  }
  get('https://psgc.gitlab.io/api/provinces/', function(list){
    fill(prov, list, prov.dataset.selected, 'Select province');
    prov.addEventListener('change', loadMun);
    if(prov.value) loadMun();
  }, toInputs);
  function loadMun(){
    var code = prov.selectedOptions[0] ? prov.selectedOptions[0].dataset.code : null;
    mun.innerHTML = '<option value="">Loading…</option>'; brgy.innerHTML = '<option value="">Select municipality first</option>';
    if(!code) return;
    get('https://psgc.gitlab.io/api/provinces/' + code + '/cities-municipalities/', function(list){
      fill(mun, list, mun.dataset.selected, 'Select municipality / city');
      mun.onchange = loadBrgy;
      if(mun.value) loadBrgy();
    }, function(){});
  }
  function loadBrgy(){
    var code = mun.selectedOptions[0] ? mun.selectedOptions[0].dataset.code : null;
    brgy.innerHTML = '<option value="">Loading…</option>';
    if(!code) return;
    get('https://psgc.gitlab.io/api/cities-municipalities/' + code + '/barangays/', function(list){
      fill(brgy, list, brgy.dataset.selected, 'Select barangay');
    }, function(){});
  }
})();
</script>
</body>
</html>
