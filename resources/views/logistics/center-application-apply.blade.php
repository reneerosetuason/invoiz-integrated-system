@extends('layouts.auth-split')

@section('title', 'Open a Logistics Center')
@section('back_link')
<a href="/logistics/login" class="absolute right-4 top-5 z-10 inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink sm:right-8 sm:top-7"><span aria-hidden="true">←</span> Back to Login</a>
@endsection
@section('brand_eyebrow', 'Apply')
@section('brand_lines')
<span class="mask-line" aria-hidden="true"><span style="transition-delay:140ms">Open a</span></span>
<span class="mask-line" aria-hidden="true"><span style="transition-delay:230ms">Logistics Center.</span></span>
@endsection
@section('brand_plain', 'Open a Logistics Center.')
@section('brand_copy', 'Run your own INVOIZ Logistics Center and connect riders, deliveries, and service areas in your community.')

@section('left_bottom')
<p class="hidden text-xs text-muted min-[900px]:block">INVOIZ Logistics · v1.0</p>
@endsection

@section('form_eyebrow', 'Application')
@section('form_head')
<h1 class="sr-only">Open a Logistics Center application</h1>
@endsection

@section('form')
<style>
.public-file{width:100%;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-height:44px;padding:9px 12px;border:0;background:transparent;color:#1B1B1E;font-size:.9rem;line-height:1.4;cursor:pointer}
.public-file::file-selector-button{margin-right:.75rem;padding:.4rem .9rem;border:1px solid rgba(15,76,92,.25);border-radius:999px;background:#fff;color:#0E4A57;font-weight:600;font-size:.8rem;cursor:pointer}
.public-file:hover::file-selector-button{border-color:#0E4A57}
.dropzone{transition:border-color .2s cubic-bezier(.22,1,.36,1),background-color .2s}
.dropzone.drag{border-color:#0E4A57 !important;background:rgba(234,244,243,.6)}
</style>
<script>
function centerWizard(opts) {
return {
stepTitles: ['Center & Owner', 'Center Location', 'Supporting Documents', 'Review & Submit'],
totalSteps: 4,
step: Math.min(Math.max(opts.initialStep ?? 0, 0), 3),
maxStep: opts.errorSteps && opts.errorSteps.length > 0 ? 3 : 0,
errorSteps: opts.errorSteps ?? [],
documentLabels: opts.documentLabels ?? {},
submitting: false,
clientErrors: {},
files: {},
liveText: 'Step 1 of 4: Center & Owner',
verifiedEmail: opts.verifiedEmail ?? '',
verifyCode: '',
verifyBusy: false,
verifyMsg: '',
verifyMsgOk: false,
codeSentTo: '',
resendWait: 0,
resendTimer: null,
emailVerifiedNow() {
const current = this.inputValue('email').toLowerCase();
return this.verifiedEmail !== '' && current !== '' && this.verifiedEmail === current;
},
resetEmailVerification() {
if (this.verifiedEmail && this.verifiedEmail !== this.inputValue('email').toLowerCase()) {
this.verifiedEmail = '';
this.codeSentTo = '';
this.verifyCode = '';
this.verifyMsg = '';
}
},
csrfToken() {
return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
},
startResendCooldown() {
this.resendWait = 60;
if (this.resendTimer) clearInterval(this.resendTimer);
this.resendTimer = setInterval(() => {
this.resendWait -= 1;
if (this.resendWait <= 0) { clearInterval(this.resendTimer); this.resendTimer = null; }
}, 1000);
},
async sendVerifyCode() {
const email = this.inputValue('email');
if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
this.setFieldError('email', 'Enter a valid email address first.');
return;
}
this.clearFieldError('email');
this.verifyBusy = true;
this.verifyMsg = '';
try {
const res = await fetch('{{ route('center-application.verification.send') }}', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
body: JSON.stringify({ email: email, name: this.inputValue('owner_name') }),
});
const data = await res.json().catch(() => ({}));
if (!res.ok) {
this.verifyMsgOk = false;
this.verifyMsg = data.message || 'Unable to send the verification code. Please try again.';
return;
}
this.codeSentTo = email.toLowerCase();
this.verifyMsgOk = true;
this.verifyMsg = data.message || 'Verification code sent. Please check your email.';
this.startResendCooldown();
} catch (e) {
this.verifyMsgOk = false;
this.verifyMsg = 'Unable to send the verification code. Please try again.';
} finally {
this.verifyBusy = false;
}
},
async confirmVerifyCode() {
const email = this.inputValue('email');
this.verifyBusy = true;
this.verifyMsg = '';
try {
const res = await fetch('{{ route('center-application.verification.verify') }}', {
method: 'POST',
headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
body: JSON.stringify({ email: email, code: this.verifyCode.trim() }),
});
const data = await res.json().catch(() => ({}));
if (!res.ok) {
this.verifyMsgOk = false;
this.verifyMsg = (data.errors && data.errors.code && data.errors.code[0]) || data.message || 'Invalid verification code. Please try again.';
return;
}
this.verifiedEmail = email.toLowerCase();
this.verifyCode = '';
this.verifyMsg = '';
this.clearFieldError('email');
} catch (e) {
this.verifyMsgOk = false;
this.verifyMsg = 'Unable to verify the code. Please try again.';
} finally {
this.verifyBusy = false;
}
},
...centerAddressPicker({
municipalities: opts.municipalities,
barangays: opts.barangays,
provincePicked: opts.provincePicked,
municipalityPicked: opts.municipalityPicked,
}),
isCurrent(i) { return this.step === i; },
isError(i) {
return this.errorSteps.includes(i) || this.stepHasClientErrors(i);
},
isCompleted(i) {
if (this.isError(i)) return false;
return i < this.step;
},
isReachable(i) {
if (i === this.step) return true;
if (i < this.step) return true;
if (this.errorSteps.length > 0) return true;
return i <= this.maxStep;
},
stepHasClientErrors(i) {
return Object.keys(this.clientErrors).some((k) => this.fieldStep(k) === i);
},
fieldStep(field) {
if (field.startsWith('documents.')) return 2;
if (['house_number', 'street', 'barangay', 'municipality', 'province'].includes(field)) return 1;
return 0;
},
setStep(i) {
if (this.submitting) return;
this.step = i;
if (i > this.maxStep) this.maxStep = i;
this.liveText = 'Step ' + (i + 1) + ' of ' + this.totalSteps + ': ' + this.stepTitles[i];
this.$nextTick(() => {
const heading = this.$refs['stepHeading' + i];
if (heading && typeof heading.focus === 'function') {
heading.focus();
}
window.scrollTo({ top: heading ? heading.getBoundingClientRect().top + window.scrollY - 16 : 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
});
},
goTo(i) {
if (this.isReachable(i)) this.setStep(i);
},
back() {
if (this.step > 0) this.setStep(this.step - 1);
},
next() {
if (this.submitting) return;
if (this.step >= this.totalSteps - 1) return;
if (this.validateStep()) this.setStep(this.step + 1);
},
validateStep() {
if (this.step === 0) return this.validateStep1();
if (this.step === 2) return this.validateDocuments();
return true;
},
setFieldError(field, message) {
if (message) {
this.clientErrors[field] = message;
return false;
}
delete this.clientErrors[field];
return true;
},
clearFieldError(field) { delete this.clientErrors[field]; },
inputValue(field) { void this.step; return (this.$refs[field]?.value ?? '').trim(); },
validateStep1() {
let ok = true;
ok = this.setFieldError('business_name', this.inputValue('business_name') ? '' : 'The center name is required.') && ok;
ok = this.setFieldError('owner_name', this.inputValue('owner_name') ? '' : 'The owner name is required.') && ok;
const email = this.inputValue('email');
if (!email) {
ok = this.setFieldError('email', 'The email address is required.') && ok;
} else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
ok = this.setFieldError('email', 'Enter a valid email address.') && ok;
} else if (!this.emailVerifiedNow()) {
ok = this.setFieldError('email', 'Please verify your email address before continuing.') && ok;
}
const phoneRaw = this.inputValue('phone');
const phone = phoneRaw.replace(/[\s\-()]/g, '');
const validPhone = /^(09\d{9}|\+639\d{9})$/.test(phone);
ok = this.setFieldError('phone', !phoneRaw ? 'The contact number is required.' : (!validPhone ? 'Enter a valid Philippine mobile number, e.g., 0917 123 4567.' : '')) && ok;
if (!ok) {
const first = Object.keys(this.clientErrors).find((k) => this.fieldStep(k) === 0);
if (first && this.$refs[first]) {
this.$nextTick(() => this.$refs[first].focus());
}
}
return ok;
},
validateDocuments() {
let ok = true;
const LIMIT = 5 * 1024 * 1024;
const tooBig = (type) => {
const f = this.files[type];
if (!f || !f.name || !(f.size > LIMIT)) return '';
const label = (this.documentLabels && this.documentLabels[type]) || 'Document';
return label + ' is ' + this.fmtSize(f.size) + ' — files must be 5 MB or smaller. Please choose a smaller file.';
};
for (const type of ['valid_id', 'business_registration']) {
const hasFile = this.files[type] && this.files[type].name;
ok = this.setFieldError('documents.' + type, !hasFile ? 'This document is required.' : tooBig(type)) && ok;
}
for (const type of Object.keys(this.files)) {
if (type === 'valid_id' || type === 'business_registration') continue;
const msg = tooBig(type);
if (msg) ok = this.setFieldError('documents.' + type, msg) && ok;
}
if (!ok) {
const first = Object.keys(this.clientErrors).find((k) => k.startsWith('documents.'));
if (first && this.$refs['doc_' + first.slice('documents.'.length)]) {
this.$nextTick(() => this.$refs['doc_' + first.slice('documents.'.length)].focus());
}
}
return ok;
},
onFileChange(type, el) {
const f = el.files && el.files[0];
if (f) {
this.files[type] = { name: f.name, size: f.size };
} else {
delete this.files[type];
}
this.clearFieldError('documents.' + type);
},
removeDoc(type, el) {
if (el) el.value = '';
delete this.files[type];
this.clearFieldError('documents.' + type);
},
fmtSize(b) {
b = Number(b) || 0;
if (b < 1024) return b + ' B';
if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
return (b / 1048576).toFixed(1) + ' MB';
},
docFilename(type) { return (this.files[type] && this.files[type].name) || null; },
documentRows() {
return Object.keys(this.documentLabels).map((type) => ({
type: type,
label: this.documentLabels[type],
required: type === 'valid_id' || type === 'business_registration',
filename: this.docFilename(type),
}));
},
locationSummary() {
const parts = ['house_number', 'street', 'barangay', 'municipality', 'province']
.map((f) => this.inputValue(f))
.filter((v) => v.length > 0);
return parts.length ? parts.join(', ') : 'Not provided';
},
progressPercent() {
return Math.round((this.step / (this.totalSteps - 1)) * 100);
},
};
}

function centerAddressPicker(opts) {
return {
municipalities: opts.municipalities,
barangays: opts.barangays,
provincePicked: opts.provincePicked,
municipalityPicked: opts.municipalityPicked,
loadingMunicipalities: false,
loadingBarangays: false,
addressError: '',
escapeHtml(value) {
return String(value ?? '').replace(/[&<>"']/g, (c) => ({
'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));
},
placeholder(select) {
if (select === 'municipality') return 'City / Municipality (optional)';
if (select === 'barangay') return 'Barangay (optional)';
return 'Select an option';
},
emptyOptions(select) {
return `<option value="">${this.escapeHtml(this.placeholder(select))}</option>`;
},
buildOptions(select, items) {
let html = this.emptyOptions(select);
for (const item of items) {
const id = item.id ?? '';
html += `<option value="${this.escapeHtml(item.name)}" data-id="${id}">${this.escapeHtml(item.name)}</option>`;
}
return html;
},
reloadMunicipalityOptions() {
this.loadingMunicipalities = true;
fetch(`/api/address/provinces/${this.provinceId}/municipalities`)
.then((res) => { if (!res.ok) throw new Error(); return res.json(); })
.then((data) => {
this.$refs.municipality.innerHTML = this.buildOptions('municipality', data.municipalities);
this.municipalityPicked = false;
this.addressError = '';
})
.catch(() => {
this.$refs.municipality.innerHTML = this.emptyOptions('municipality');
this.municipalityPicked = false;
this.addressError = 'We could not load cities and municipalities. Please retry.';
})
.finally(() => { this.loadingMunicipalities = false; });
},
selectProvince() {
const opt = this.$refs.province.options[this.$refs.province.selectedIndex];
this.provinceId = opt && opt.dataset.id ? opt.dataset.id : '';
this.provincePicked = this.$refs.province.value !== '';
this.$refs.municipality.innerHTML = this.emptyOptions('municipality');
this.$refs.barangay.innerHTML = this.emptyOptions('barangay');
this.municipalityId = '';
this.municipalityPicked = false;
this.addressError = '';
if (this.provinceId) this.reloadMunicipalityOptions();
},
reloadBarangayOptions() {
this.loadingBarangays = true;
fetch(`/api/address/municipalities/${this.municipalityId}/barangays`)
.then((res) => { if (!res.ok) throw new Error(); return res.json(); })
.then((data) => {
this.$refs.barangay.innerHTML = this.buildOptions('barangay', data.barangays);
this.addressError = '';
})
.catch(() => {
this.$refs.barangay.innerHTML = this.emptyOptions('barangay');
this.addressError = 'We could not load barangays. Please retry.';
})
.finally(() => { this.loadingBarangays = false; });
},
selectMunicipality() {
const opt = this.$refs.municipality.options[this.$refs.municipality.selectedIndex];
this.municipalityId = opt && opt.dataset.id ? opt.dataset.id : '';
this.municipalityPicked = this.$refs.municipality.value !== '';
this.$refs.barangay.innerHTML = this.emptyOptions('barangay');
this.addressError = '';
if (this.municipalityId) this.reloadBarangayOptions();
},
retryAddress() {
if (this.loadingMunicipalities || this.loadingBarangays) return;
if (this.provinceId && !this.municipalityId) this.reloadMunicipalityOptions();
else if (this.municipalityId) this.reloadBarangayOptions();
},
};
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<noscript>
<style>
[x-cloak] { display: block !important; }
</style>
</noscript>
@php
    // Group server-side validation errors by wizard step so we can (a) open the
    // first failing step after a redirect-back and (b) surface every step that
    // still needs attention. Step mapping lives in CenterApplicationService so
    // the admin maintenance view reports the exact same steps.
    $errorsByStep = [1 => [], 2 => [], 3 => [], 4 => []];
    if ($errors->any()) {
        foreach ($errors->keys() as $key) {
            $step = \App\Services\CenterApplicationService::stepsForErrors([$key])[0] ?? 1;
            $errorsByStep[$step][] = $errors->first($key);
        }
    }
    $errorSteps = array_values(array_filter(
        array_keys($errorsByStep),
        fn ($s) => $errorsByStep[$s] !== [],
    ));
    $initialStep = $errorSteps[0] ?? 1;
    $zeroBasedErrorSteps = array_map(fn ($s) => $s - 1, $errorSteps);
@endphp
@if (session('success'))
<div class="rv mt-6 rounded-[14px] bg-white px-4 py-3" role="status" aria-live="polite" style="border:1px solid rgba(15,76,92,.2)">
<p class="flex items-start gap-2 text-[15px] font-bold text-teal-dark"><svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Application submitted</p>
<p class="mt-1 text-sm text-muted">{{ session('success') }}</p>
</div>
@endif
@if ($errors->any())
<div class="rv mt-6 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" role="alert" aria-live="assertive" style="border:1px solid rgba(180,35,24,.35);color:#B42318">
<p class="font-bold">Please fix the following:</p>
<ul class="mt-3 space-y-3">
@foreach ($errorsByStep as $step => $messages)
@if ($messages)
<li class="flex flex-wrap items-start gap-2">
<button type="button" x-on:click="goTo({{ $step - 1 }})" class="min-h-[44px] flex-none rounded-lg bg-white px-3 py-2 text-xs font-semibold transition hover:border-teal" style="border:1px solid rgba(180,35,24,.4);color:#B42318">Fix in Step {{ $step }}</button>
<ul class="min-w-0 flex-1 space-y-1">
@foreach ($messages as $message)
<li class="break-words">{{ $message }}</li>
@endforeach
</ul>
</li>
@endif
@endforeach
</ul>
</div>
@endif
<form method="POST" action="{{ route('center-application.store') }}" enctype="multipart/form-data"
class="rv mt-6"
x-data="centerWizard({
initialStep: @js($initialStep - 1),
errorSteps: @js($zeroBasedErrorSteps),
verifiedEmail: @js($verifiedEmail ?? ''),
documentLabels: @js($documentLabels),
municipalities: @js($municipalities),
barangays: @js($barangays),
provincePicked: @js(old('province', '') !== ''),
municipalityPicked: @js(old('municipality', '') !== ''),
})"
data-wizard-initial-step="{{ $initialStep - 1 }}"
data-wizard-error-steps="{{ json_encode($zeroBasedErrorSteps) }}"
x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
novalidate>
@csrf
<p role="status" aria-live="polite" class="sr-only" x-text="liveText"></p>
<div class="rounded-[14px] bg-white p-3 sm:hidden" style="border:1px solid rgba(15,76,92,.12)">
<p class="text-[13px] font-semibold">Step <span x-text="step + 1"></span> of 4 · <span x-text="stepTitles[step]"></span></p>
<div class="mt-2 h-1.5 w-full overflow-hidden rounded-full" style="background:rgba(15,76,92,.12)" role="presentation">
<div class="h-full rounded-full bg-teal transition-all duration-300" :style="'width: ' + progressPercent() + '%'"></div>
</div>
</div>
<ol class="relative mt-6 hidden sm:grid sm:grid-cols-4 sm:gap-3" aria-label="Application steps">
<span class="absolute left-8 right-8 top-[17px] hidden sm:block" style="height:1px;background:rgba(15,76,92,.12)" aria-hidden="true"></span>
<span class="absolute left-8 right-8 top-[17px] hidden bg-teal sm:block" style="height:1px;:style:width" aria-hidden="true" x-bind:style="'width: calc(' + progressPercent() + '% - 4rem)'"></span>
<template x-for="(title, i) in stepTitles" :key="i">
<li class="relative min-w-0">
<button type="button"
:disabled="!isReachable(i) || submitting"
:aria-current="isCurrent(i) ? 'step' : null"
:aria-label="(isCurrent(i) ? 'Current step, ' : '') + 'Step ' + (i + 1) + ': ' + title"
x-on:click="goTo(i)"
class="group block w-full min-h-[44px] rounded-[14px] p-1.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 disabled:cursor-not-allowed">
<span class="mx-0 flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition"
:class="isError(i)
? 'text-white'
: (isCurrent(i)
? 'bg-teal-dark text-white'
: (isCompleted(i)
? 'bg-teal text-white'
: 'bg-white text-muted'))"
:style="isError(i) ? 'background:#B42318;box-shadow:0 0 0 4px rgba(180,35,24,.15)' : (isCurrent(i) ? 'box-shadow:0 0 0 4px rgba(14,74,87,.15)' : 'border:1px solid rgba(15,76,92,.25)')">
<template x-if="isCompleted(i) && !isError(i) && !isCurrent(i)">
<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
</template>
<template x-if="!isCompleted(i) || isError(i) || isCurrent(i)">
<span x-text="i + 1" aria-hidden="true"></span>
</template>
</span>
<span class="mt-1.5 block text-[11px] font-semibold leading-snug sm:text-xs"
:class="isCurrent(i) ? 'text-teal-dark' : (isError(i) ? 'color:#B42318' : 'text-muted')"
x-text="title"></span>
</button>
</li>
</template>
</ol>
<section x-cloak x-show="step === 0" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" aria-labelledby="wizard-step-1" class="mt-6">
<h3 id="wizard-step-1" tabindex="-1" x-ref="stepHeading0" class="font-display font-medium tracking-[-0.02em] focus:outline-none" style="font-size:1.5rem;line-height:1.2">Center &amp; Owner Details</h3>
<div class="mt-5 grid gap-5 sm:grid-cols-2">
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="business_name">Logistics Center Name <span class="text-teal" aria-hidden="true">*</span></label>
<div class="relative">
<input id="business_name" x-ref="business_name" x-on:input="clearFieldError('business_name')" type="text" name="business_name" value="{{ old('business_name') }}" required maxlength="255" autocomplete="organization" placeholder="e.g., Midtown Logistics Hub" :aria-invalid="clientErrors['business_name'] ? 'true' : '@error('business_name') true @enderror'" class="@error('business_name') field-error @enderror">
</div>
<span class="bar" aria-hidden="true"></span>
<p x-cloak x-show="clientErrors['business_name']" class="mt-1.5 text-[13px] font-medium" style="color:#B42318" x-text="clientErrors['business_name']"></p>
@error('business_name')<p id="business_name_error" class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="owner_name">Owner Name <span class="text-teal" aria-hidden="true">*</span></label>
<div class="relative">
<input id="owner_name" x-ref="owner_name" x-on:input="clearFieldError('owner_name')" type="text" name="owner_name" value="{{ old('owner_name') }}" required maxlength="255" autocomplete="name" placeholder="Full name" :aria-invalid="clientErrors['owner_name'] ? 'true' : '@error('owner_name') true @enderror'" class="@error('owner_name') field-error @enderror">
</div>
<span class="bar" aria-hidden="true"></span>
<p x-cloak x-show="clientErrors['owner_name']" class="mt-1.5 text-[13px] font-medium" style="color:#B42318" x-text="clientErrors['owner_name']"></p>
@error('owner_name')<p id="owner_name_error" class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="email">Email Address <span class="text-teal" aria-hidden="true">*</span></label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
<input id="email" x-ref="email" x-on:input="clearFieldError('email'); resetEmailVerification()" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" inputmode="email" placeholder="you@example.com" :aria-invalid="clientErrors['email'] ? 'true' : '@error('email') true @enderror'" class="@error('email') field-error @enderror">
</div>
<span class="bar" aria-hidden="true"></span>
<p x-cloak x-show="clientErrors['email']" class="mt-1.5 text-[13px] font-medium" style="color:#B42318" x-text="clientErrors['email']"></p>
@error('email')<p id="email_error" class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
<div x-cloak x-show="!emailVerifiedNow()" class="mt-3">
<p class="text-[13px] text-muted">Verify this email address to continue. We'll send a 6-digit code.</p>
<template x-if="!codeSentTo">
<div>
<button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy" class="mt-2 inline-flex min-h-[44px] w-auto items-center justify-center gap-2 whitespace-nowrap rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-teal-dark transition hover:border-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed" style="border:1px solid rgba(15,76,92,.25)">Send verification code</button>
</div>
</template>
<template x-if="codeSentTo">
<div>
<p class="text-[13px] text-muted">Enter the 6-digit code sent to <span class="font-semibold text-ink" x-text="codeSentTo"></span>.</p>
<div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
<input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" x-model="verifyCode" :disabled="verifyBusy" aria-label="Verification code" class="block w-full sm:max-w-[12rem] text-center tnum" style="height:44px;border:0;border-bottom:1px solid rgba(15,76,92,.25);background:transparent;font-size:15px;letter-spacing:.3em;color:#1B1B1E;outline:none">
<button type="button" x-on:click="confirmVerifyCode()" :disabled="verifyBusy || verifyCode.trim().length !== 6" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full bg-teal-dark px-5 py-2.5 text-sm font-bold text-white transition hover:bg-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">Verify</button>
</div>
<button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy || resendWait > 0" class="lnk mt-1.5 min-h-[36px] text-xs font-semibold text-teal-dark disabled:opacity-60"><span class="tnum" x-text="resendWait > 0 ? 'Resend code in ' + resendWait + 's' : 'Resend code'">Resend code</span></button>
</div>
</template>
<p x-cloak x-show="verifyMsg" class="mt-2 text-[13px] font-medium" :class="verifyMsgOk ? 'text-teal-dark' : ''" :style="verifyMsgOk ? '' : 'color:#B42318'" x-text="verifyMsg"></p>
</div>
<div x-cloak x-show="emailVerifiedNow()" class="mt-3 flex items-center gap-2" role="status">
<svg class="h-5 w-5 flex-none text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
<p class="text-sm font-semibold text-teal-dark">Email verified<span x-text="verifiedEmail ? ' — ' + verifiedEmail : ''"></span></p>
</div>
</div>
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="phone">Contact Number <span class="text-teal" aria-hidden="true">*</span></label>
<div class="relative">
<svg class="pointer-events-none absolute left-0 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.3a1 1 0 01.9.7l1.5 4a1 1 0 01-.3 1.1L8.1 10a16 16 0 006 6l1.2-2.3a1 1 0 011.1-.3l4 1.5a1 1 0 01.7.9V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z"/></svg>
<input id="phone" x-ref="phone" x-on:input="clearFieldError('phone')" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="0917 123 4567" :aria-invalid="clientErrors['phone'] ? 'true' : '@error('phone') true @enderror'" class="@error('phone') field-error @enderror">
</div>
<span class="bar" aria-hidden="true"></span>
<p x-cloak x-show="clientErrors['phone']" class="mt-1.5 text-[13px] font-medium" style="color:#B42318" x-text="clientErrors['phone']"></p>
@error('phone')<p id="phone_error" class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
</div>
</section>
<section x-cloak x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" aria-labelledby="wizard-step-2" class="mt-6">
<h3 id="wizard-step-2" tabindex="-1" x-ref="stepHeading1" class="font-display font-medium tracking-[-0.02em] focus:outline-none" style="font-size:1.5rem;line-height:1.2">Center Location</h3>
<div class="mt-5 grid gap-5 sm:grid-cols-2">
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="house_number">House / Unit Number</label>
<div class="relative">
<input id="house_number" x-ref="house_number" type="text" name="house_number" value="{{ old('house_number') }}" maxlength="50" autocomplete="address-line1" placeholder="e.g., 42" style="padding-left:0">
</div>
<span class="bar" aria-hidden="true"></span>
@error('house_number')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="street">Street</label>
<div class="relative">
<input id="street" x-ref="street" type="text" name="street" value="{{ old('street') }}" maxlength="255" autocomplete="address-line1" placeholder="e.g., Rizal Ave" style="padding-left:0">
</div>
<span class="bar" aria-hidden="true"></span>
@error('street')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
@if ($provinces->isNotEmpty())
<div class="sm:col-span-2 ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="province">Province</label>
<div class="relative">
<select id="province" name="province" x-ref="province" x-on:change="selectProvince" autocomplete="address-level1" aria-invalid="@error('province') true @enderror" class="@error('province') field-error @enderror" style="padding-left:0">
<option value="">Select a province...</option>
@foreach ($provinces as $province)
<option value="{{ $province->name }}" data-id="{{ $province->id }}" @selected(old('province') === $province->name)>{{ $province->name }}</option>
@endforeach
</select>
<svg class="pointer-events-none absolute right-1 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
</div>
<span class="bar" aria-hidden="true"></span>
@error('province')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0" :aria-busy="loadingMunicipalities ? 'true' : 'false'">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="municipality">City / Municipality</label>
<div class="relative">
<select id="municipality" name="municipality" x-ref="municipality" x-on:change="selectMunicipality" :disabled="!provincePicked || loadingMunicipalities" autocomplete="address-level2" aria-invalid="@error('municipality') true @enderror" class="@error('municipality') field-error @enderror" style="padding-left:0">
<option value="">City / Municipality (optional)</option>
@foreach ($municipalities as $municipality)
<option value="{{ $municipality['name'] }}" data-id="{{ $municipality['id'] ?? '' }}" @selected(old('municipality') === $municipality['name'])>{{ $municipality['name'] }}</option>
@endforeach
</select>
<svg class="pointer-events-none absolute right-1 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
</div>
<span class="bar" aria-hidden="true"></span>
<div x-cloak x-show="loadingMunicipalities" role="status" class="mt-1.5 text-xs text-muted">Loading cities and municipalities...</div>
@error('municipality')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0" :aria-busy="loadingBarangays ? 'true' : 'false'">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="barangay">Barangay</label>
<div class="relative">
<select id="barangay" name="barangay" x-ref="barangay" :disabled="!municipalityPicked || loadingBarangays" autocomplete="address-level2" aria-invalid="@error('barangay') true @enderror" class="@error('barangay') field-error @enderror" style="padding-left:0">
<option value="">Barangay (optional)</option>
@foreach ($barangays as $barangay)
<option value="{{ $barangay['name'] }}" data-id="{{ $barangay['id'] ?? '' }}" @selected(old('barangay') === $barangay['name'])>{{ $barangay['name'] }}</option>
@endforeach
</select>
<svg class="pointer-events-none absolute right-1 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
</div>
<span class="bar" aria-hidden="true"></span>
<div x-cloak x-show="loadingBarangays" role="status" class="mt-1.5 text-xs text-muted">Loading barangays...</div>
@error('barangay')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="sm:col-span-2" x-cloak x-show="addressError">
<div role="alert" aria-live="assertive" class="flex items-center gap-3 rounded-[14px] bg-white px-4 py-3 text-sm font-semibold" style="border:1px solid rgba(180,35,24,.35);color:#B42318">
<p class="flex-1 min-w-0 break-words" x-text="addressError"></p>
<button type="button" x-on:click="retryAddress" class="lnk flex-none text-xs font-bold" style="color:#B42318">Retry</button>
</div>
</div>
@else
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="barangay">Barangay</label>
<div class="relative">
<input id="barangay" x-ref="barangay" type="text" name="barangay" value="{{ old('barangay') }}" maxlength="255" autocomplete="address-level2" placeholder="e.g., Barangay Uno" style="padding-left:0">
</div>
<span class="bar" aria-hidden="true"></span>
@error('barangay')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="municipality">City / Municipality</label>
<div class="relative">
<input id="municipality" x-ref="municipality" type="text" name="municipality" value="{{ old('municipality') }}" maxlength="255" autocomplete="address-level2" placeholder="e.g., Quezon City" style="padding-left:0">
</div>
<span class="bar" aria-hidden="true"></span>
@error('municipality')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
<div class="ufield sm:col-span-2 min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="province">Province</label>
<div class="relative">
<input id="province" x-ref="province" type="text" name="province" value="{{ old('province') }}" maxlength="255" autocomplete="address-level1" placeholder="e.g., Metro Manila" style="padding-left:0">
</div>
<span class="bar" aria-hidden="true"></span>
@error('province')<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
</div>
@endif
</div>
</section>
<section x-cloak x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" aria-labelledby="wizard-step-3" class="mt-6">
<h3 id="wizard-step-3" tabindex="-1" x-ref="stepHeading2" class="font-display font-medium tracking-[-0.02em] focus:outline-none" style="font-size:1.5rem;line-height:1.2">Supporting Documents</h3>
<p class="mt-2 text-sm text-muted">Accepted formats: <span class="font-semibold text-ink">JPG, PNG, WebP, PDF, DOC, DOCX</span>. Maximum file size: <span class="font-semibold text-ink">5 MB</span> per file.</p>
<div class="mt-5 space-y-5">
@foreach ($documentLabels as $type => $label)
@php $required = in_array($type, ['valid_id', 'business_registration'], true); @endphp
<div class="min-w-0">
<label class="mb-1 block text-[13px] font-medium text-teal-dark" for="documents_{{ $type }}">{{ $label }} @if ($required)<span class="text-teal" aria-hidden="true">*</span>@endif</label>
<div class="dropzone mt-2 rounded-[14px] p-4 transition" style="border:1.5px dashed rgba(15,76,92,.3)" x-on:dragover.prevent="$el.classList.add('drag')" x-on:dragleave="$el.classList.remove('drag')" x-on:drop="$el.classList.remove('drag')">
<p class="flex items-center gap-2 text-sm font-semibold"><svg class="h-5 w-5 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9A5 5 0 0117.9 8L18 8a4 4 0 01-.5 8H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v9m-3-3l3 3 3-3"/></svg>Drag a file here or choose from your device</p>
<input id="documents_{{ $type }}" x-ref="doc_{{ $type }}" type="file" name="documents[{{ $type }}]" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" x-on:change="onFileChange('{{ $type }}', $el)" class="public-file mt-2 block @error("documents.$type") field-error @enderror" @if ($required) required @endif aria-describedby="documents_{{ $type }}_hint" :aria-invalid="clientErrors['documents.{{ $type }}'] ? 'true' : '@error("documents.$type") true @enderror'" />
<p id="documents_{{ $type }}_hint" class="mt-1.5 text-[13px] text-muted">@if ($required)Required. @else Optional. @endif JPG, PNG, WebP, PDF, DOC, DOCX &middot; up to 5 MB.</p>
<p x-cloak x-show="clientErrors['documents.{{ $type }}']" class="mt-1.5 text-[13px] font-medium" style="color:#B42318" x-text="clientErrors['documents.{{ $type }}']"></p>
@error("documents.$type")<p class="mt-1.5 text-[13px] font-medium" style="color:#B42318">{{ $message }}</p>@enderror
<p x-cloak x-show="files['{{ $type }}']" role="status" class="mt-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
<svg class="h-4 w-4 flex-none text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
<span class="sr-only">Selected file:</span>
<span class="min-w-0 flex-1 truncate font-medium" x-text="(files['{{ $type }}'] || {}).name"></span>
<span class="tnum text-muted" x-text="fmtSize((files['{{ $type }}'] || {}).size)"></span>
<button type="button" x-on:click="removeDoc('{{ $type }}', $refs['doc_{{ $type }}'])" :aria-label="'Remove selected file for {{ $label }}'" class="grid h-11 w-11 place-items-center rounded-lg text-muted transition hover:text-[#B42318] hover:bg-white">
<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12.1A2 2 0 0116.1 21H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-9 0h10"/></svg>
</button>
</p>
</div>
</div>
@endforeach
</div>
</section>
<section x-cloak x-show="step === 3" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" aria-labelledby="wizard-step-4" class="mt-6">
<h3 id="wizard-step-4" tabindex="-1" x-ref="stepHeading3" class="font-display font-medium tracking-[-0.02em] focus:outline-none" style="font-size:1.5rem;line-height:1.2">Review your application.</h3>
<p class="mt-1 text-sm text-muted">Check your details. You can go back and edit any step below.</p>
<div class="mt-6">
<div class="py-4" style="border-top:1px solid rgba(15,76,92,.12)">
<div class="flex items-center justify-between gap-3">
<h4 class="text-[13px] font-bold text-ink">Center &amp; Owner</h4>
<button type="button" x-on:click="goTo(0)" class="lnk min-h-[44px] px-1 text-xs font-bold text-teal-dark">Edit</button>
</div>
<dl class="mt-2 space-y-1.5 text-sm">
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-medium text-muted">Center name</dt><dd class="min-w-0 break-words font-semibold" x-text="inputValue('business_name') || 'Not provided'"></dd></div>
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-medium text-muted">Owner name</dt><dd class="min-w-0 break-words font-semibold" x-text="inputValue('owner_name') || 'Not provided'"></dd></div>
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-medium text-muted">Email</dt><dd class="min-w-0 break-words font-semibold" x-text="inputValue('email') || 'Not provided'"></dd></div>
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-medium text-muted">Phone</dt><dd class="min-w-0 break-words font-semibold" x-text="inputValue('phone') || 'Not provided'"></dd></div>
</dl>
</div>
<div class="py-4" style="border-top:1px solid rgba(15,76,92,.12)">
<div class="flex items-center justify-between gap-3">
<h4 class="text-[13px] font-bold text-ink">Location</h4>
<button type="button" x-on:click="goTo(1)" class="lnk min-h-[44px] px-1 text-xs font-bold text-teal-dark">Edit</button>
</div>
<dl class="mt-2 space-y-1.5 text-sm">
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-medium text-muted">Address</dt><dd class="min-w-0 break-words font-semibold" x-text="locationSummary()"></dd></div>
</dl>
</div>
<div class="py-4" style="border-top:1px solid rgba(15,76,92,.12);border-bottom:1px solid rgba(15,76,92,.12)">
<div class="flex items-center justify-between gap-3">
<h4 class="text-[13px] font-bold text-ink">Documents</h4>
<button type="button" x-on:click="goTo(2)" class="lnk min-h-[44px] px-1 text-xs font-bold text-teal-dark">Edit</button>
</div>
<dl class="mt-2 space-y-1.5 text-sm">
<template x-for="row in documentRows()" :key="row.type">
<div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
<dt class="w-40 flex-none font-medium text-muted"><span x-text="row.label"></span>@verbatim<span x-show="row.required" class="text-teal" aria-hidden="true">*</span>@endverbatim</dt>
<dd class="min-w-0 break-words font-semibold">
<template x-if="row.filename">
<span class="break-words" x-text="row.filename"></span>
</template>
<template x-if="!row.filename">
<span class="font-normal text-muted" x-text="row.required ? 'Missing — required' : 'Not selected'"></span>
</template>
</dd>
</div>
</template>
</dl>
</div>
</div>
<p class="mt-5 text-[13px] leading-relaxed text-muted">By submitting, you agree that the INVOIZ Logistics team may review the details and documents you provide. Your information is used only to process this application.</p>
</section>
<div class="mt-8 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color:rgba(15,76,92,.12)">
<button type="button" id="wizard-back" x-show="step > 0" x-cloak :disabled="submitting" x-on:click="back" class="lnk inline-flex min-h-[48px] items-center gap-2 text-sm font-semibold text-teal-dark disabled:opacity-60 disabled:cursor-not-allowed">
<svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
Back
</button>
<button type="button" id="wizard-next" x-show="step < 3" x-cloak :disabled="submitting" x-on:click="next" class="press inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-dark px-8 py-3 text-sm font-bold text-white transition hover:bg-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
<template x-if="step === 2"><span>Review &amp; Submit</span></template>
<template x-if="step !== 2"><span>Next</span></template>
</button>
<button type="submit" id="wizard-submit" x-show="step === 3" x-cloak :disabled="submitting || !emailVerifiedNow()" class="press inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal-dark px-8 py-3 text-sm font-bold text-white transition hover:bg-teal focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed">
<svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
<path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
</svg>
<span x-text="submitting ? 'Submitting application...' : 'Submit Application'">Submit Application</span>
</button>
</div>
</form>
<p class="rv mt-6 text-center text-sm text-muted">Already applied? <a href="{{ route('center-application.status') }}" class="lnk font-bold text-teal-dark">Check a Logistics Center Application Status</a></p>
@endsection

@section('after_form')
@endsection

@section('right-scroll', 'min-[900px]:max-h-[100dvh] min-[900px]:overflow-y-auto min-[900px]:py-10')
