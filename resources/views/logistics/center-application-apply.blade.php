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
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Open a Logistics Center</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: {
                            DEFAULT: '#16697A',
                            dark: '#0E4A57',
                            light: '#EAF4F3',
                        },
                        secondary: '#F0A202',
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                },
            },
        };
    </script>
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
                // ── Email OTP verification (mirrors rider apply) ─────────
                // verifiedEmail holds the server-verified address (lowercase).
                // It is UX state only: store() re-checks the OTP record.
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
                // ── Address picker (existing cascading PSGC logic) ─────────
                ...centerAddressPicker({
                    municipalities: opts.municipalities,
                    barangays: opts.barangays,
                    provincePicked: opts.provincePicked,
                    municipalityPicked: opts.municipalityPicked,
                }),
                // ── Step state helpers ─────────────────────────────────────
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
                    if (field.startsWith('documents.')) return 2;      // 0-based documents step
                    if (['house_number', 'street', 'barangay', 'municipality', 'province'].includes(field)) return 1;
                    return 0;                                           // 0-based center & owner step
                },
                // ── Navigation ────────────────────────────────────────────
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
                // ── Lightweight client-side checks (server stays authoritative) ──
                validateStep() {
                    if (this.step === 0) return this.validateStep1();
                    if (this.step === 2) return this.validateDocuments();
                    return true; // Step 2 optional, Step 4 review has no gating
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
                // Reads a field's current DOM value. The `void this.step` makes this
                // request track the reactive `step`, so the review summary re-reads
                // the fields every time the user navigates between steps (Alpine
                // only re-evaluates x-text when one of its reactive dependencies
                // changes; raw DOM reads alone would render a stale value once).
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
                    for (const type of ['valid_id', 'business_registration']) {
                        const hasFile = this.files[type] && this.files[type].name;
                        ok = this.setFieldError('documents.' + type, hasFile ? '' : 'This document is required.') && ok;
                    }
                    if (!ok) {
                        const first = Object.keys(this.clientErrors).find((k) => k.startsWith('documents.'));
                        if (first && this.$refs['doc_' + first.slice('documents.'.length)]) {
                            this.$nextTick(() => this.$refs['doc_' + first.slice('documents.'.length)].focus());
                        }
                    }
                    return ok;
                },
                // ── File inputs ────────────────────────────────────────────
                onFileChange(type, el) {
                    const f = el.files && el.files[0];
                    if (f) {
                        this.files[type] = { name: f.name, size: f.size };
                    } else {
                        delete this.files[type];
                    }
                    this.clearFieldError('documents.' + type);
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
                // ── Review helpers ─────────────────────────────────────────
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
    <style>
        [x-cloak] { display: none !important; }
        /* Consistent, tappable file pickers (the shared form-controls partial
           intentionally excludes file inputs, so they are styled here). Long
           filenames are truncated instead of overflowing the control. */
        input[type="file"].public-file {
            min-height: 44px;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background-color: #ffffff;
            color: #111827;
            font-size: 0.875rem;
            line-height: 1.4;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        input[type="file"].public-file:focus {
            border-color: #16697a;
            box-shadow: 0 0 0 3px rgba(22, 105, 122, 0.15);
            outline: none;
        }
        input[type="file"].public-file::file-selector-button {
            margin-right: 0.75rem;
            padding: 0.4rem 0.9rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background-color: #f9fafb;
            color: #374151;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
        }
        input[type="file"].public-file:hover::file-selector-button {
            border-color: #16697a;
            color: #0E4A57;
        }
        /* Indicator labels: keep long step names on one truncated line on small
           screens so the stepper never overflows at 320px. */
        .step-indicator-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (prefers-reduced-motion: reduce) {
            .animate-spin { animation: none; }
            .wizard-progress { transition: none; }
            [x-cloak] { transition: none !important; }
        }
    </style>
    <noscript>
        <!-- Without JavaScript the wizard behaves like the original single-page
             form: every step is visible and stacked, and the Submit button stays
             available. x-cloak is neutralized so nothing is hidden. -->
        <style>
            [x-cloak] { display: block !important; }
        </style>
    </noscript>
</head>
<body class="font-sans antialiased bg-[#F7F6F2] text-gray-900 overflow-x-hidden">
<div class="min-h-screen flex flex-col-reverse lg:flex-row">

    {{-- ================= LEFT PANEL — LOGISTICS BRANDING ================= --}}
    <div class="lg:w-1/2 bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] text-white flex flex-col p-8 sm:p-12 xl:p-16">
        <div class="flex-1 flex flex-col justify-center max-w-xl mx-auto lg:mx-0 w-full">
            {{-- Brand --}}
            <a href="{{ route('login') }}" class="inline-flex items-center gap-4 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl object-cover ring-1 ring-white/25 shadow-lg">
                <div>
                    <p class="text-2xl sm:text-3xl font-extrabold tracking-tight">Logistics</p>
                    <p class="text-[11px] sm:text-xs font-semibold tracking-[0.3em] uppercase text-white/70 mt-0.5">Logistics Center</p>
                </div>
            </a>

            {{-- Headline --}}
            <h1 class="mt-12 sm:mt-16 text-3xl sm:text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight">
                Open a<br>Logistics Center.
            </h1>

            <p class="mt-5 text-sm sm:text-base text-white/75 leading-relaxed max-w-md">
                Run your own INVOIZ Logistics Center and connect riders, deliveries, and service areas in your community.
            </p>

            {{-- Steps --}}
            <ol class="mt-10 space-y-4 max-w-md text-sm sm:text-base">
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">1</span>
                    <span class="text-white/80">Tell us about your center and the owner.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">2</span>
                    <span class="text-white/80">Add your center location.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">3</span>
                    <span class="text-white/80">Upload the required documents for review.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">4</span>
                    <span class="text-white/80">Review and submit your application.</span>
                </li>
            </ol>
        </div>

        {{-- Footer --}}
        <div class="pt-10 flex flex-wrap items-center justify-between gap-3 max-w-xl mx-auto lg:mx-0 w-full">
            <p class="text-xs sm:text-sm text-white/50">Logistics &middot; v1.0 &middot; Logistics</p>
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-white/80 hover:text-white underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Login
            </a>
        </div>
    </div>

    {{-- ================= RIGHT PANEL — APPLICATION WIZARD ================= --}}
    <div class="lg:w-1/2 flex items-start justify-center p-4 sm:p-10 xl:p-16">
        <div class="w-full max-w-2xl">
            {{-- Success message --}}
            @if (session('success'))
                <div role="status" aria-live="polite"
                     class="mt-6 flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-4 rounded-xl text-sm">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="font-semibold">Application submitted</p>
                        <p class="mt-1 text-emerald-700">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            {{-- Error summary: grouped by wizard step, each group links back to
                 the step that needs correcting so nothing stays hidden. --}}
            @if ($errors->any())
                <div role="alert" aria-live="assertive"
                     class="mt-6 bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-xl text-sm">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-3 space-y-3">
                        @foreach ($errorsByStep as $step => $messages)
                            @if ($messages)
                                <li class="flex flex-wrap items-start gap-2">
                                    <button type="button"
                                            x-on:click="goTo({{ $step - 1 }})"
                                            class="flex-none min-h-[44px] rounded-lg bg-red-100 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-1">
                                        Fix in Step {{ $step }}
                                    </button>
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
                  class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-8"
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

                {{-- Alpine announces every step change to assistive technology
                     without moving the live region out of the document. --}}
                <p role="status" aria-live="polite" class="sr-only" x-text="liveText"></p>

                {{-- Mobile progress summary --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 sm:hidden">
                    <p class="text-xs font-semibold text-gray-700">
                        Step <span x-text="step + 1"></span> of 4 &middot; <span x-text="stepTitles[step]"></span>
                    </p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200" role="presentation">
                        <div class="wizard-progress h-full rounded-full bg-teal transition-all duration-300"
                             :style="'width: ' + progressPercent() + '%'"></div>
                    </div>
                </div>

                {{-- Step indicator --}}
                <ol class="mt-5 grid grid-cols-4 gap-1 sm:flex sm:items-center sm:gap-0" aria-label="Application steps">
                    <template x-for="(title, i) in stepTitles" :key="i">
                        <li class="min-w-0 sm:flex-1">
                            <button type="button"
                                    :disabled="!isReachable(i) || submitting"
                                    :aria-current="isCurrent(i) ? 'step' : null"
                                    :aria-label="(isCurrent(i) ? 'Current step, ' : '') + 'Step ' + (i + 1) + ': ' + title"
                                    x-on:click="goTo(i)"
                                    class="group w-full min-h-[44px] rounded-xl p-1.5 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 sm:p-2 disabled:cursor-not-allowed">
                                <span class="mx-auto flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-full text-xs font-bold ring-1 ring-gray-200 transition-colors sm:h-9 sm:w-9"
                                      :class="isError(i)
                                          ? 'bg-red-100 text-red-700 ring-red-300'
                                          : (isCurrent(i)
                                              ? 'bg-teal text-white ring-teal ring-4 ring-teal/20'
                                              : (isCompleted(i)
                                                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                  : 'bg-gray-100 text-gray-400 ring-gray-200'))">
                                    <template x-if="isCompleted(i) && !isError(i) && !isCurrent(i)">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </template>
                                    <template x-if="!isCompleted(i) || isError(i) || isCurrent(i)">
                                        <span x-text="i + 1" aria-hidden="true"></span>
                                    </template>
                                </span>
                                <span class="step-indicator-label mt-1 block text-[10px] font-semibold sm:text-xs"
                                      :class="isCurrent(i) ? 'text-teal-dark' : (isError(i) ? 'text-red-600' : (isReachable(i) && !isCompleted(i) ? 'text-gray-500' : 'text-gray-400'))"
                                      x-text="title"></span>
                            </button>
                        </li>
                    </template>
                </ol>

                {{-- ============ STEP 1 — CENTER & OWNER ============ --}}
                <section x-cloak x-show="step === 0" aria-labelledby="wizard-step-1" class="mt-6">
                    <h3 id="wizard-step-1" tabindex="-1" x-ref="stepHeading0" class="text-base sm:text-lg font-bold text-gray-900 focus:outline-none">
                        Center &amp; Owner Details
                    </h3>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="business_name" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Logistics Center Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="business_name" x-ref="business_name" x-on:input="clearFieldError('business_name')" type="text" name="business_name" value="{{ old('business_name') }}"
                                   class="block mt-2 w-full @error('business_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="255" autocomplete="organization" placeholder="e.g., Midtown Logistics Hub"
                                   :aria-invalid="clientErrors['business_name'] ? 'true' : '@error('business_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['business_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['business_name']"></p>
                            @error('business_name')<p id="business_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="owner_name" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Owner Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="owner_name" x-ref="owner_name" x-on:input="clearFieldError('owner_name')" type="text" name="owner_name" value="{{ old('owner_name') }}"
                                   class="block mt-2 w-full @error('owner_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="255" autocomplete="name" placeholder="Full name"
                                   :aria-invalid="clientErrors['owner_name'] ? 'true' : '@error('owner_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['owner_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['owner_name']"></p>
                            @error('owner_name')<p id="owner_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="email" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Email Address <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="email" x-ref="email" x-on:input="clearFieldError('email'); resetEmailVerification()" type="email" name="email" value="{{ old('email') }}"
                                   class="block mt-2 w-full @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="255" autocomplete="email" placeholder="you@example.com"
                                   :aria-invalid="clientErrors['email'] ? 'true' : '@error('email') true @enderror'" />
                            <p x-cloak x-show="clientErrors['email']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['email']"></p>
                            @error('email')<p id="email_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            {{-- Email OTP verification (mirrors rider apply) --}}
                            <div x-cloak x-show="!emailVerifiedNow()" class="mt-3 rounded-xl border border-teal/20 bg-teal-light/40 p-4">
                                <template x-if="!codeSentTo">
                                    <div>
                                        <p class="text-xs text-gray-600">Verify this email address to continue. We'll send a 6-digit code.</p>
                                        <button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy"
                                                class="mt-2 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-teal px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                                            <span x-text="verifyBusy ? 'Sending...' : 'Send verification code'">Send verification code</span>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="codeSentTo">
                                    <div>
                                        <p class="text-xs text-gray-600">Enter the 6-digit code sent to <span class="font-semibold text-gray-900" x-text="codeSentTo"></span>.</p>
                                        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456"
                                                   x-model="verifyCode" :disabled="verifyBusy" aria-label="Verification code"
                                                   class="block w-full sm:max-w-[12rem] text-center font-mono tracking-[0.3em]" />
                                            <button type="button" x-on:click="confirmVerifyCode()" :disabled="verifyBusy || verifyCode.trim().length !== 6"
                                                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-teal px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                                                <span x-text="verifyBusy ? 'Verifying...' : 'Verify'">Verify</span>
                                            </button>
                                        </div>
                                        <button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy || resendWait > 0"
                                                class="mt-1.5 min-h-[36px] text-xs font-semibold text-teal hover:text-teal-dark underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-teal disabled:opacity-60 disabled:no-underline">
                                            <span x-text="resendWait > 0 ? 'Resend code in ' + resendWait + 's' : 'Resend code'">Resend code</span>
                                        </button>
                                    </div>
                                </template>
                                <p x-cloak x-show="verifyMsg" class="mt-2 text-sm" :class="verifyMsgOk ? 'text-emerald-600' : 'text-red-600'" x-text="verifyMsg"></p>
                            </div>
                            <div x-cloak x-show="emailVerifiedNow()" class="mt-3 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3" role="status">
                                <svg class="h-5 w-5 flex-none text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <p class="text-sm font-semibold text-emerald-700">Email verified<span x-text="verifiedEmail ? ' — ' + verifiedEmail : ''"></span></p>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label for="phone" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Contact Number <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="phone" x-ref="phone" x-on:input="clearFieldError('phone')" type="tel" name="phone" value="{{ old('phone') }}"
                                   class="block mt-2 w-full @error('phone') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="20" autocomplete="tel" placeholder="0917 123 4567"
                                   :aria-invalid="clientErrors['phone'] ? 'true' : '@error('phone') true @enderror'" />
                            <p x-cloak x-show="clientErrors['phone']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['phone']"></p>
                            @error('phone')<p id="phone_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- ============ STEP 2 — CENTER LOCATION ============ --}}
                <section x-cloak x-show="step === 1" aria-labelledby="wizard-step-2" class="mt-6">
                    <h3 id="wizard-step-2" tabindex="-1" x-ref="stepHeading1" class="text-base sm:text-lg font-bold text-gray-900 focus:outline-none">
                        Center Location
                    </h3>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="house_number" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">House / Unit Number</label>
                            <input id="house_number" x-ref="house_number" type="text" name="house_number" value="{{ old('house_number') }}"
                                   class="block mt-2 w-full @error('house_number') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   maxlength="50" autocomplete="address-line1" placeholder="e.g., 42" />
                            @error('house_number')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="street" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Street</label>
                            <input id="street" x-ref="street" type="text" name="street" value="{{ old('street') }}"
                                   class="block mt-2 w-full @error('street') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   maxlength="255" autocomplete="address-line1" placeholder="e.g., Rizal Ave" />
                            @error('street')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Province / City / Barangay use cascading selects backed by the local
                             PSGC dataset (the same dataset as the mobile app). If the dataset is
                             missing the selects fall back to plain text inputs. --}}
                        @if ($provinces->isNotEmpty())
                            <div class="sm:col-span-2 min-w-0">
                                <label for="province" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Province</label>
                                <select id="province" name="province" x-ref="province" x-on:change="selectProvince"
                                        class="block mt-2 w-full @error('province') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                        autocomplete="address-level1"
                                        aria-invalid="@error('province') true @enderror">
                                    <option value="">Select a province...</option>
                                    @foreach ($provinces as $province)
                                        <option value="{{ $province->name }}" data-id="{{ $province->id }}" @selected(old('province') === $province->name)>{{ $province->name }}</option>
                                    @endforeach
                                </select>
                                @error('province')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0" :aria-busy="loadingMunicipalities ? 'true' : 'false'">
                                <label for="municipality" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">City / Municipality</label>
                                <select id="municipality" name="municipality" x-ref="municipality" x-on:change="selectMunicipality"
                                        :disabled="!provincePicked || loadingMunicipalities"
                                        class="block mt-2 w-full @error('municipality') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                        autocomplete="address-level2"
                                        aria-invalid="@error('municipality') true @enderror">
                                    <option value="">City / Municipality (optional)</option>
                                    @foreach ($municipalities as $municipality)
                                        <option value="{{ $municipality['name'] }}" data-id="{{ $municipality['id'] ?? '' }}" @selected(old('municipality') === $municipality['name'])>{{ $municipality['name'] }}</option>
                                    @endforeach
                                </select>
                                <div x-cloak x-show="loadingMunicipalities" role="status" class="mt-1.5 text-xs text-gray-400">Loading cities and municipalities...</div>
                                @error('municipality')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0" :aria-busy="loadingBarangays ? 'true' : 'false'">
                                <label for="barangay" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Barangay</label>
                                <select id="barangay" name="barangay" x-ref="barangay"
                                        :disabled="!municipalityPicked || loadingBarangays"
                                        class="block mt-2 w-full @error('barangay') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                        autocomplete="address-level2"
                                        aria-invalid="@error('barangay') true @enderror">
                                    <option value="">Barangay (optional)</option>
                                    @foreach ($barangays as $barangay)
                                        <option value="{{ $barangay['name'] }}" data-id="{{ $barangay['id'] ?? '' }}" @selected(old('barangay') === $barangay['name'])>{{ $barangay['name'] }}</option>
                                    @endforeach
                                </select>
                                <div x-cloak x-show="loadingBarangays" role="status" class="mt-1.5 text-xs text-gray-400">Loading barangays...</div>
                                @error('barangay')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2" x-cloak x-show="addressError">
                                <div role="alert" aria-live="assertive" class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                                    <p class="flex-1 min-w-0 break-words" x-text="addressError"></p>
                                    <button type="button" x-on:click="retryAddress"
                                            class="flex-none text-xs font-semibold text-red-700 underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Retry</button>
                                </div>
                            </div>
                        @else
                            <div class="min-w-0">
                                <label for="barangay" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Barangay</label>
                                <input id="barangay" x-ref="barangay" type="text" name="barangay" value="{{ old('barangay') }}"
                                       class="block mt-2 w-full @error('barangay') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level2" placeholder="e.g., Barangay Uno" />
                                @error('barangay')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0">
                                <label for="municipality" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">City / Municipality</label>
                                <input id="municipality" x-ref="municipality" type="text" name="municipality" value="{{ old('municipality') }}"
                                       class="block mt-2 w-full @error('municipality') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level2" placeholder="e.g., Quezon City" />
                                @error('municipality')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2 min-w-0">
                                <label for="province" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">Province</label>
                                <input id="province" x-ref="province" type="text" name="province" value="{{ old('province') }}"
                                       class="block mt-2 w-full @error('province') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level1" placeholder="e.g., Metro Manila" />
                                @error('province')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    </div>
                </section>

                {{-- ============ STEP 3 — SUPPORTING DOCUMENTS ============ --}}
                <section x-cloak x-show="step === 2" aria-labelledby="wizard-step-3" class="mt-6">
                    <h3 id="wizard-step-3" tabindex="-1" x-ref="stepHeading2" class="text-base sm:text-lg font-bold text-gray-900 focus:outline-none">
                        Supporting Documents
                    </h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600">
                        Accepted formats: <span class="font-semibold text-gray-700">JPG, PNG, WebP, PDF, DOC, DOCX</span>.
                        Maximum file size: <span class="font-semibold text-gray-700">5 MB</span> per file.
                    </p>

                    <div class="mt-5 space-y-5">
                        @foreach ($documentLabels as $type => $label)
                            @php $required = in_array($type, ['valid_id', 'business_registration'], true); @endphp
                            <div class="min-w-0">
                                <label for="documents_{{ $type }}" class="block text-xs font-semibold tracking-wider uppercase text-gray-500">
                                    {{ $label }}
                                    @if ($required)<span class="text-red-500" aria-hidden="true">*</span>@endif
                                </label>
                                <input id="documents_{{ $type }}" x-ref="doc_{{ $type }}" type="file" name="documents[{{ $type }}]"
                                       accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
                                       x-on:change="onFileChange('{{ $type }}', $el)"
                                       class="public-file block mt-2 @error("documents.$type") border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       @if ($required) required @endif
                                       aria-describedby="documents_{{ $type }}_hint"
                                       :aria-invalid="clientErrors['documents.{{ $type }}'] ? 'true' : '@error("documents.$type") true @enderror'" />
                                <div class="min-w-0 mt-1 flex items-start gap-1.5">
                                    <div class="min-w-0 flex-1">
                                        <p id="documents_{{ $type }}_hint" class="mt-1.5 text-xs text-gray-500">
                                            @if ($required)Required. @else Optional. @endif
                                            JPG, PNG, WebP, PDF, DOC, DOCX &middot; up to 5 MB.
                                        </p>
                                        <p x-cloak x-show="clientErrors['documents.{{ $type }}']"
                                           class="mt-1.5 text-sm text-red-600" x-text="clientErrors['documents.{{ $type }}']"></p>
                                        @error("documents.$type")<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <p x-cloak x-show="files['{{ $type }}']" role="status"
                                   class="mt-2 flex min-w-0 items-center gap-1.5 text-xs text-gray-500">
                                    <svg class="h-4 w-4 flex-none text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="sr-only">Selected file:</span>
                                    <span class="min-w-0 truncate font-medium text-gray-600" x-text="(files['{{ $type }}'] || {}).name"></span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ============ STEP 4 — REVIEW & SUBMIT ============ --}}
                <section x-cloak x-show="step === 3" aria-labelledby="wizard-step-4" class="mt-6">
                    <h3 id="wizard-step-4" tabindex="-1" x-ref="stepHeading3" class="text-base sm:text-lg font-bold text-gray-900 focus:outline-none">
                        Review your application.
                    </h3>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Check your details. You can go back and edit any step below.</p>

                    <div class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-100">
                        {{-- Center & Owner --}}
                        <div class="p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Center &amp; Owner</h4>
                                <button type="button" x-on:click="goTo(0)"
                                        class="min-h-[44px] rounded-lg px-3 py-2 text-xs font-semibold text-teal hover:bg-teal-light focus:outline-none focus-visible:ring-2 focus-visible:ring-teal">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Center name</dt><dd class="min-w-0 break-words" x-text="inputValue('business_name') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Owner name</dt><dd class="min-w-0 break-words" x-text="inputValue('owner_name') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Email</dt><dd class="min-w-0 break-words" x-text="inputValue('email') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Phone</dt><dd class="min-w-0 break-words" x-text="inputValue('phone') || 'Not provided'"></dd></div>
                            </dl>
                        </div>

                        {{-- Location --}}
                        <div class="p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Location</h4>
                                <button type="button" x-on:click="goTo(1)"
                                        class="min-h-[44px] rounded-lg px-3 py-2 text-xs font-semibold text-teal hover:bg-teal-light focus:outline-none focus-visible:ring-2 focus-visible:ring-teal">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Address</dt><dd class="min-w-0 break-words" x-text="locationSummary()"></dd></div>
                            </dl>
                        </div>

                        {{-- Documents --}}
                        <div class="p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Documents</h4>
                                <button type="button" x-on:click="goTo(2)"
                                        class="min-h-[44px] rounded-lg px-3 py-2 text-xs font-semibold text-teal hover:bg-teal-light focus:outline-none focus-visible:ring-2 focus-visible:ring-teal">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-3 space-y-2 text-sm">
                                <template x-for="row in documentRows()" :key="row.type">
                                    <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                                        <dt class="w-40 flex-none font-semibold text-gray-500">
                                            <span x-text="row.label"></span>
                                            <span x-show="row.required" class="text-red-500" aria-hidden="true">*</span>
                                        </dt>
                                        <dd class="min-w-0 break-words">
                                            <template x-if="row.filename">
                                                <span class="break-words" x-text="row.filename"></span>
                                            </template>
                                            <template x-if="!row.filename">
                                                <span class="text-gray-400" x-text="row.required ? 'Missing — required' : 'Not selected'"></span>
                                            </template>
                                        </dd>
                                    </div>
                                </template>
                            </dl>
                        </div>
                    </div>

                    {{-- Privacy note --}}
                    <div class="mt-5 rounded-xl bg-gray-50 border border-gray-100 p-4">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Privacy</h4>
                        <p class="mt-1.5 max-w-xl break-words text-xs text-gray-600">
                            By submitting, you agree that the INVOIZ Logistics team may review the details and documents you provide.
                            Your information is used only to process this application.
                        </p>
                    </div>
                </section>

                {{-- ============ NAVIGATION CONTROLS ============ --}}
                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button"
                            id="wizard-back"
                            x-show="step > 0"
                            x-cloak
                            :disabled="submitting"
                            x-on:click="back"
                            class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>

                    <button type="button"
                            id="wizard-next"
                            x-show="step < 3"
                            x-cloak
                            :disabled="submitting"
                            x-on:click="next"
                            class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal px-8 py-3 text-sm font-semibold text-white transition-colors hover:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <template x-if="step === 2"><span>Review &amp; Submit</span></template>
                        <template x-if="step !== 2"><span>Next</span></template>
                    </button>

                    <button type="submit"
                            id="wizard-submit"
                            x-show="step === 3"
                            x-cloak
                            :disabled="submitting || !emailVerifiedNow()"
                            class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-teal px-8 py-3 text-sm font-semibold text-white transition-colors hover:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed">
                        <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span x-text="submitting ? 'Submitting application...' : 'Submit Application'">Submit Application</span>
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                Already applied?
                <a href="{{ route('center-application.status') }}" class="font-semibold text-teal hover:text-teal-dark underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1">
                    Check a Logistics Center Application Status
                </a>
            </p>
        </div>
    </div>
</div>
@include('logistics.layouts.partials.form-controls')
</body>
</html>
