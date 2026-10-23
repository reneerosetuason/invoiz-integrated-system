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
                regions: opts.regions ?? {},
                submitting: false,
                termsAccepted: false,
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
                            body: JSON.stringify({ email: email, name: this.inputValue('owner_first_name') }),
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
                    if (['house_number', 'street', 'barangay', 'municipality', 'province', 'region', 'postal_code'].includes(field)) return 1;
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
                    ok = this.setFieldError('business_name', this.inputValue('business_name').length < 2 ? 'The center name must be at least 2 characters.' : '') && ok;
                    ok = this.setFieldError('owner_first_name', this.inputValue('owner_first_name').length < 2 ? 'The owner first name must be at least 2 characters.' : '') && ok;
                    ok = this.setFieldError('owner_last_name', this.inputValue('owner_last_name').length < 2 ? 'The owner last name must be at least 2 characters.' : '') && ok;
                    const secNum = this.inputValue('sec_number');
                    ok = this.setFieldError('sec_number', !secNum ? 'The SEC registration number is required.' : (!/^[A-Za-z]{2}\d{9}$/.test(secNum) ? 'The SEC registration number must be 2 letters followed by 9 digits (e.g., AB123456789).' : '')) && ok;
                    const tinNum = this.inputValue('tin');
                    ok = this.setFieldError('tin', !tinNum ? 'The TIN is required.' : (!/^\d{3}-\d{3}-\d{3}-\d{3}$/.test(tinNum) ? 'The TIN must be in the format 000-000-000-000 (12 digits).' : '')) && ok;
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
                regionName() {
                    const sel = document.getElementById('region');
                    if (!sel || !sel.value) return 'Not provided';
                    const opt = sel.options[sel.selectedIndex];
                    return (opt ? opt.textContent.trim() : sel.value) || 'Not provided';
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
                async selectRegion() {
                    const regionSel = document.getElementById('region');
                    const provSel = document.getElementById('province');
                    const muniSel = document.getElementById('municipality');
                    const brgySel = document.getElementById('barangay');
                    if (muniSel && muniSel.tagName === 'SELECT') muniSel.innerHTML = '<option value="">City / Municipality (optional)</option>';
                    if (brgySel && brgySel.tagName === 'SELECT') brgySel.innerHTML = '<option value="">Barangay (optional)</option>';
                    if (provSel && provSel.tagName === 'SELECT') {
                        provSel.innerHTML = '<option value="">Select a province...</option>';
                        provSel.dispatchEvent(new Event('change', { bubbles: true }));
                        const code = regionSel ? regionSel.value : '';
                        if (!code) return;
                        provSel.disabled = true;
                        try {
                            const [provRes, cityRes] = await Promise.all([
                                fetch('https://psgc.gitlab.io/api/regions/' + encodeURIComponent(code) + '/provinces', { headers: { Accept: 'application/json' } }),
                                fetch('https://psgc.gitlab.io/api/regions/' + encodeURIComponent(code) + '/cities', { headers: { Accept: 'application/json' } }),
                            ]);
                            const provs = provRes.ok ? await provRes.json() : [];
                            const cities = cityRes.ok ? await cityRes.json() : [];
                            (Array.isArray(provs) ? provs : []).forEach((p) => {
                                const o = document.createElement('option');
                                o.value = p.name; o.textContent = p.name; o.dataset.id = p.code; o.dataset.kind = 'province';
                                provSel.appendChild(o);
                            });
                            (Array.isArray(cities) ? cities : []).forEach((c) => {
                                const o = document.createElement('option');
                                o.value = c.name; o.textContent = c.name; o.dataset.id = c.code; o.dataset.kind = 'city';
                                provSel.appendChild(o);
                            });
                        } catch (e) { /* server validates; keep the empty list */ }
                        finally { provSel.disabled = false; }
                    }
                    this.clearFieldError('region');
                },
                reloadMunicipalityOptions() {
                    this.loadingMunicipalities = true;
                    const pid = String(this.provinceId ?? '');
                    const url = /^\d{9}$/.test(pid)
                        ? `https://psgc.gitlab.io/api/provinces/${pid}/cities-municipalities`
                        : `/api/address/provinces/${this.provinceId}/municipalities`;
                    fetch(url)
                        .then((res) => { if (!res.ok) throw new Error(); return res.json(); })
                        .then((data) => {
                            const items = Array.isArray(data) ? data : (data.municipalities || []);
                            this.$refs.municipality.innerHTML = this.buildOptions('municipality', items.map((m) => ({ id: m.code ?? m.id, name: m.name })));
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
                    if ((opt && opt.dataset.kind) === 'city' && this.provinceId) {
                        this.$refs.municipality.innerHTML = `<option value="${this.escapeHtml(this.$refs.province.value)}" data-id="${this.escapeHtml(this.provinceId)}" selected>${this.escapeHtml(this.$refs.province.value)}</option>`;
                        this.municipalityId = this.provinceId;
                        this.municipalityPicked = true;
                        this.reloadBarangayOptions();
                        return;
                    }
                    if (this.provinceId) this.reloadMunicipalityOptions();
                },
                reloadBarangayOptions() {
                    this.loadingBarangays = true;
                    const mid = String(this.municipalityId ?? '');
                    const url = /^\d{9}$/.test(mid)
                        ? `https://psgc.gitlab.io/api/cities-municipalities/${mid}/barangays`
                        : `/api/address/municipalities/${this.municipalityId}/barangays`;
                    fetch(url)
                        .then((res) => { if (!res.ok) throw new Error(); return res.json(); })
                        .then((data) => {
                            const items = Array.isArray(data) ? data : (data.barangays || []);
                            this.$refs.barangay.innerHTML = this.buildOptions('barangay', items.map((b) => ({ id: b.code ?? b.id, name: b.name })));
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
        /* Indicator labels: wrap to two lines instead of truncating, so
           long step names (notably "Supporting Documents") stay fully
           readable on small screens without overflowing at 320px. */
        .step-indicator-label {
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
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
<body class="font-sans antialiased bg-[#F8FAF9] text-gray-900 overflow-x-hidden">
<div class="min-h-screen flex flex-col-reverse lg:flex-row">

    {{-- ================= LEFT PANEL — LOGISTICS BRANDING ================= --}}
    {{-- Compact 40/60 split shared with login + status: narrower brand column,
         tighter rhythm (mt-8 / space-y-3), max-w-md line lengths. --}}
    <div class="lg:w-[40%] xl:w-[38%] bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] text-white flex flex-col p-6 sm:p-10 lg:p-10 xl:p-12">
        <div class="w-full max-w-md mx-auto lg:mx-0 my-auto py-8 lg:py-10 flex flex-col justify-center">
            {{-- Brand --}}
            <a href="/logistics/login" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-12 w-12 rounded-xl object-cover ring-1 ring-white/25 shadow-lg">
                <div>
                    <p class="text-xl font-extrabold tracking-tight">Logistics</p>
                    <p class="text-[11px] font-semibold tracking-[0.25em] uppercase text-white/70 mt-0.5">Logistics Center</p>
                </div>
            </a>

            {{-- Headline --}}
            <h1 class="mt-8 text-2xl sm:text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight">
                Open a Logistics Center.
            </h1>

            <p class="mt-4 text-sm sm:text-[15px] text-white/75 leading-relaxed">
                Run your own INVOIZ Logistics Center and connect riders, deliveries, and service areas in your community.
            </p>

            {{-- Steps --}}
            <ol class="mt-8 space-y-3 text-sm">
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">1</span>
                    <span class="text-white/80 pt-1">Tell us about your center and the owner.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">2</span>
                    <span class="text-white/80 pt-1">Add your center location.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">3</span>
                    <span class="text-white/80 pt-1">Upload the required documents for review.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex-none w-7 h-7 rounded-full bg-white/10 ring-1 ring-white/20 flex items-center justify-center text-xs font-bold">4</span>
                    <span class="text-white/80 pt-1">Review and submit your application.</span>
                </li>
            </ol>
        </div>

        {{-- Footer --}}
        <div class="mt-8 pt-6 border-t border-white/10 flex flex-wrap items-center justify-between gap-3 w-full max-w-md mx-auto lg:mx-0">
            <p class="text-xs text-white/50">Logistics &middot; v1.0 &middot; Logistics</p>
            <a href="/logistics/login" class="inline-flex items-center gap-1.5 text-xs font-semibold text-white/80 hover:text-white underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Login
            </a>
        </div>
    </div>

    {{-- ================= RIGHT PANEL — APPLICATION WIZARD ================= --}}
    {{-- Keep vertical-centering + m-auto (already implemented); widen to 60% to match. --}}
    <div class="flex-1 lg:w-[60%] xl:w-[62%] flex justify-center bg-[#F8FAF9] px-4 py-10 sm:p-10">
        <div class="w-full max-w-2xl m-auto">
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
                  class="mt-6 bg-white rounded-2xl border border-gray-200/70 shadow-[0_12px_40px_-16px_rgba(14,74,87,0.25)] p-6 sm:p-8"
                  x-data="centerWizard({
                      initialStep: @js($initialStep - 1),
                      errorSteps: @js($zeroBasedErrorSteps),
                      verifiedEmail: @js($verifiedEmail ?? ''),
                      documentLabels: @js($documentLabels),
                      regions: @js($regions ?? []),
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

                {{-- Step indicator: one continuous track behind the circles.
                     The track spans center-to-center of the outer steps; the
                     opaque circles mask every segment joint, so no gaps can
                     appear at any width. Progress uses INVOIZ teal for
                     completed and active steps, light gray for upcoming ones.
                     Only segment colors change with state — geometry never
                     moves. Completed steps show a teal check; the current
                     step keeps its number, scale, and shadow so it stays
                     the single most prominent item. --}}
                <div class="relative mt-5">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-[12.5%] top-[22px] h-0.5 sm:top-[28px]">
                        <div class="flex h-full">
                            <div class="h-full flex-1 transition-colors duration-300"
                                 :class="isError(1) ? 'bg-red-300' : ((1 <= step) ? 'bg-teal' : 'bg-gray-200')"></div>
                            <div class="h-full flex-1 transition-colors duration-300"
                                 :class="isError(2) ? 'bg-red-300' : ((2 <= step) ? 'bg-teal' : 'bg-gray-200')"></div>
                            <div class="h-full flex-1 transition-colors duration-300"
                                 :class="isError(3) ? 'bg-red-300' : ((3 <= step) ? 'bg-teal' : 'bg-gray-200')"></div>
                        </div>
                    </div>
                <ol class="relative grid grid-cols-4 items-start" aria-label="Application steps">
                    <template x-for="(title, i) in stepTitles" :key="i">
                        <li class="min-w-0">
                            <button type="button"
                                    :disabled="!isReachable(i) || submitting"
                                    :aria-current="isCurrent(i) ? 'step' : null"
                                    :aria-label="(isCurrent(i) ? 'Current step, ' : '') + 'Step ' + (i + 1) + ': ' + title"
                                    x-on:click="goTo(i)"
                                    class="group w-full min-h-[44px] rounded-xl px-0.5 py-1 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-1 sm:p-1.5 disabled:cursor-not-allowed">
                                <span class="flex justify-center" aria-hidden="true">
                                    <span class="flex h-9 w-9 sm:h-11 sm:w-11 flex-none items-center justify-center rounded-full text-[13px] font-bold ring-2 transition-all duration-300"
                                          :class="isError(i)
                                              ? 'bg-red-100 text-red-700 ring-red-300'
                                              : (isCurrent(i)
                                                  ? 'bg-teal text-white ring-teal/40 shadow-[0_8px_20px_-8px_rgba(22,105,122,0.65)] scale-105'
                                                  : (isCompleted(i)
                                                      ? 'bg-teal text-white ring-teal/40'
                                                      : 'bg-white text-gray-400 ring-gray-300'))">
                                        <template x-if="isCompleted(i) && !isError(i) && !isCurrent(i)">
                                            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </template>
                                        <template x-if="!isCompleted(i) || isError(i) || isCurrent(i)">
                                            <span x-text="i + 1" aria-hidden="true"></span>
                                        </template>
                                    </span>
                                </span>
                                <span class="step-indicator-label mt-1.5 block px-0.5 text-center text-[10px] font-semibold leading-snug sm:text-[11px]"
                                      :class="isCurrent(i) ? 'font-bold text-teal-dark' : (isError(i) ? 'text-red-600' : (isCompleted(i) ? 'text-gray-800' : (isReachable(i) ? 'text-gray-500' : 'text-gray-400'))))"
                                      x-text="title"></span>
                            </button>
                        </li>
                    </template>
                </ol>
                </div>

                {{-- ============ STEP 1 — CENTER & OWNER ============ --}}
                <section x-cloak x-show="step === 0" aria-labelledby="wizard-step-1" class="mt-8">
                    <h3 id="wizard-step-1" tabindex="-1" x-ref="stepHeading0" class="text-[20px] sm:text-[23px] font-bold tracking-tight text-[#1F2933] focus:outline-none">
                        Center &amp; Owner Details
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-500">Tell us about your center and its owner. All fields marked <span class="text-red-500" aria-hidden="true">*</span> are required.</p>

                    <div class="mt-6 grid gap-x-5 gap-y-6 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="business_name" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Logistics Center Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="business_name" x-ref="business_name" x-on:input="clearFieldError('business_name')" type="text" name="business_name" value="{{ old('business_name') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('business_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="255" autocomplete="organization" placeholder="e.g., Midtown Logistics Hub"
                                   :aria-invalid="clientErrors['business_name'] ? 'true' : '@error('business_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['business_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['business_name']"></p>
                            @error('business_name')<p id="business_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="sec_number" class="block text-xs font-bold tracking-wider uppercase text-gray-600">SEC Registration Number <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="sec_number" x-ref="sec_number" x-on:input="clearFieldError('sec_number')" type="text" name="sec_number" value="{{ old('sec_number') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('sec_number') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="20" placeholder="e.g., AB123456789"
                                   :aria-invalid="clientErrors['sec_number'] ? 'true' : '@error('sec_number') true @enderror'" />
                            <p x-cloak x-show="clientErrors['sec_number']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['sec_number']"></p>
                            @error('sec_number')<p id="sec_number_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="tin" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Tax Identification Number (TIN) <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="tin" x-ref="tin" x-on:input="clearFieldError('tin')" type="text" name="tin" value="{{ old('tin') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('tin') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="20" inputmode="numeric" placeholder="e.g., 123-456-789-000"
                                   :aria-invalid="clientErrors['tin'] ? 'true' : '@error('tin') true @enderror'" />
                            <p x-cloak x-show="clientErrors['tin']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['tin']"></p>
                            @error('tin')<p id="tin_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="owner_first_name" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Owner First Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="owner_first_name" x-ref="owner_first_name" x-on:input="clearFieldError('owner_first_name')" type="text" name="owner_first_name" value="{{ old('owner_first_name') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('owner_first_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="50" autocomplete="given-name" placeholder="Juan"
                                   :aria-invalid="clientErrors['owner_first_name'] ? 'true' : '@error('owner_first_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['owner_first_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['owner_first_name']"></p>
                            @error('owner_first_name')<p id="owner_first_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="owner_middle_name" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Owner Middle Name</label>
                            <input id="owner_middle_name" x-ref="owner_middle_name" x-on:input="clearFieldError('owner_middle_name')" type="text" name="owner_middle_name" value="{{ old('owner_middle_name') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('owner_middle_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   maxlength="50" autocomplete="additional-name" placeholder="Santos (optional)"
                                   :aria-invalid="clientErrors['owner_middle_name'] ? 'true' : '@error('owner_middle_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['owner_middle_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['owner_middle_name']"></p>
                            @error('owner_middle_name')<p id="owner_middle_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="owner_last_name" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Owner Last Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="owner_last_name" x-ref="owner_last_name" x-on:input="clearFieldError('owner_last_name')" type="text" name="owner_last_name" value="{{ old('owner_last_name') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('owner_last_name') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="50" autocomplete="family-name" placeholder="Dela Cruz"
                                   :aria-invalid="clientErrors['owner_last_name'] ? 'true' : '@error('owner_last_name') true @enderror'" />
                            <p x-cloak x-show="clientErrors['owner_last_name']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['owner_last_name']"></p>
                            @error('owner_last_name')<p id="owner_last_name_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="email" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Email Address <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="email" x-ref="email" x-on:input="clearFieldError('email'); resetEmailVerification()" type="email" name="email" value="{{ old('email') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="255" autocomplete="email" placeholder="you@example.com"
                                   :aria-invalid="clientErrors['email'] ? 'true' : '@error('email') true @enderror'" />
                            <p x-cloak x-show="clientErrors['email']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['email']"></p>
                            @error('email')<p id="email_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            {{-- Email OTP verification (mirrors rider apply).
                                 The panel stays compact but can extend across
                                 the email/contact row on wider screens so it
                                 reads as the next form action. --}}
                            <div x-cloak x-show="!emailVerifiedNow()" class="mt-4 max-w-xl rounded-xl border border-teal/25 bg-teal-light/60 p-4 sm:w-[calc(200%+1.25rem)]">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 flex-none text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <p class="text-[13px] font-bold tracking-wide text-teal-dark uppercase">Email verification</p>
                                </div>
                                <template x-if="!codeSentTo">
                                    <div class="mt-2">
                                        <p class="text-[13px] leading-relaxed text-gray-600">Verify this email address to continue. We&rsquo;ll send a 6-digit code.</p>
                                        <button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy"
                                                class="mt-3 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-teal px-6 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_-8px_rgba(22,105,122,0.5)] transition hover:bg-teal-dark active:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none">
                                            <span class="whitespace-nowrap" x-text="verifyBusy ? 'Sending...' : 'Send verification code'">Send verification code</span>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="codeSentTo">
                                    <div class="mt-2">
                                        <p class="text-[13px] leading-relaxed text-gray-600">Enter the 6-digit code sent to <span class="font-semibold text-gray-900" x-text="codeSentTo"></span>.</p>
                                        <div class="mt-3 flex flex-col gap-2.5 sm:flex-row">
                                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456"
                                                   x-model="verifyCode" :disabled="verifyBusy" aria-label="Verification code"
                                                   class="block h-[52px] w-full rounded-xl border-gray-300 text-center font-mono text-[15px] tracking-[0.3em] placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 sm:flex-1" />
                                            <button type="button" x-on:click="confirmVerifyCode()" :disabled="verifyBusy || verifyCode.trim().length !== 6"
                                                    class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-teal px-6 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_-8px_rgba(22,105,122,0.5)] transition hover:bg-teal-dark active:bg-teal-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none sm:w-auto sm:flex-none sm:px-8">
                                                <span x-text="verifyBusy ? 'Verifying...' : 'Verify'">Verify</span>
                                            </button>
                                        </div>
                                        <button type="button" x-on:click="sendVerifyCode()" :disabled="verifyBusy || resendWait > 0"
                                                class="mt-1 inline-flex min-h-[44px] items-center text-xs font-semibold text-teal hover:text-teal-dark underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-teal disabled:opacity-60 disabled:no-underline">
                                            <span x-text="resendWait > 0 ? 'Resend code in ' + resendWait + 's' : 'Resend code'">Resend code</span>
                                        </button>
                                    </div>
                                </template>
                                <p x-cloak x-show="verifyMsg" class="mt-2.5 text-sm font-medium" :class="verifyMsgOk ? 'text-emerald-600' : 'text-red-600'" x-text="verifyMsg"></p>
                            </div>
                            <div x-cloak x-show="emailVerifiedNow()" class="mt-4 flex max-w-xl items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:w-[calc(200%+1.25rem)]" role="status">
                                <svg class="h-5 w-5 flex-none text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <p class="text-sm font-semibold text-emerald-700">Email verified<span x-text="verifiedEmail ? ' — ' + verifiedEmail : ''"></span></p>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label for="phone" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Contact Number <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="phone" x-ref="phone" x-on:input="clearFieldError('phone')" type="tel" name="phone" value="{{ old('phone') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('phone') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   required maxlength="20" autocomplete="tel" placeholder="0917 123 4567"
                                   :aria-invalid="clientErrors['phone'] ? 'true' : '@error('phone') true @enderror'" />
                            <p x-cloak x-show="clientErrors['phone']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['phone']"></p>
                            @error('phone')<p id="phone_error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- ============ STEP 2 — CENTER LOCATION ============ --}}
                <section x-cloak x-show="step === 1" aria-labelledby="wizard-step-2" class="mt-6">
                    <h3 id="wizard-step-2" tabindex="-1" x-ref="stepHeading1" class="text-[20px] sm:text-[23px] font-bold tracking-tight text-[#1F2933] focus:outline-none">
                        Center Location
                    </h3>

                    <div class="mt-6 grid gap-x-5 gap-y-6 sm:grid-cols-2">
                        <div class="sm:col-span-2 min-w-0">
                            <label for="region" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Region <span class="text-red-500" aria-hidden="true">*</span></label>
                            <select id="region" name="region" x-ref="region" x-on:change="selectRegion"
                                    class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('region') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                    autocomplete="address-level1"
                                    aria-invalid="@error('region') true @enderror">
                                <option value="">Select a region...</option>
                                @foreach ($regions as $code => $name)
                                    <option value="{{ $code }}" @selected(old('region') === (string) $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('region')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="house_number" class="block text-xs font-bold tracking-wider uppercase text-gray-600">House / Unit Number</label>
                            <input id="house_number" x-ref="house_number" type="text" name="house_number" value="{{ old('house_number') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('house_number') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   maxlength="50" autocomplete="address-line1" placeholder="e.g., 42" />
                            @error('house_number')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="min-w-0">
                            <label for="street" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Street</label>
                            <input id="street" x-ref="street" type="text" name="street" value="{{ old('street') }}"
                                   class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('street') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                   maxlength="255" autocomplete="address-line1" placeholder="e.g., Rizal Ave" />
                            @error('street')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Province / City / Barangay use cascading selects backed by the local
                             PSGC dataset (the same dataset as the mobile app). If the dataset is
                             missing the selects fall back to plain text inputs. --}}
                        @if ($provinces->isNotEmpty())
                            <div class="sm:col-span-2 min-w-0">
                                <label for="province" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Province</label>
                                <select id="province" name="province" x-ref="province" x-on:change="selectProvince"
                                        class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('province') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
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
                                <label for="municipality" class="block text-xs font-bold tracking-wider uppercase text-gray-600">City / Municipality</label>
                                <select id="municipality" name="municipality" x-ref="municipality" x-on:change="selectMunicipality"
                                        :disabled="!provincePicked || loadingMunicipalities"
                                        class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('municipality') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
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
                                <label for="barangay" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Barangay</label>
                                <select id="barangay" name="barangay" x-ref="barangay"
                                        :disabled="!municipalityPicked || loadingBarangays"
                                        class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('barangay') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
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

                            <div class="min-w-0">
                                <label for="postal_code" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Postal Code <span class="text-red-500" aria-hidden="true">*</span></label>
                                <input id="postal_code" x-ref="postal_code" x-on:input="clearFieldError('postal_code')" type="text" name="postal_code" value="{{ old('postal_code') }}"
                                       class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('postal_code') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       required maxlength="10" inputmode="numeric" autocomplete="postal-code" placeholder="e.g., 6000"
                                       :aria-invalid="clientErrors['postal_code'] ? 'true' : '@error('postal_code') true @enderror'" />
                                <p x-cloak x-show="clientErrors['postal_code']" class="mt-1.5 text-sm text-red-600" x-text="clientErrors['postal_code']"></p>
                                @error('postal_code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
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
                                <label for="barangay" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Barangay</label>
                                <input id="barangay" x-ref="barangay" type="text" name="barangay" value="{{ old('barangay') }}"
                                       class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('barangay') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level2" placeholder="e.g., Barangay Uno" />
                                @error('barangay')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0">
                                <label for="municipality" class="block text-xs font-bold tracking-wider uppercase text-gray-600">City / Municipality</label>
                                <input id="municipality" x-ref="municipality" type="text" name="municipality" value="{{ old('municipality') }}"
                                       class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('municipality') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level2" placeholder="e.g., Quezon City" />
                                @error('municipality')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2 min-w-0">
                                <label for="province" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Province</label>
                                <input id="province" x-ref="province" type="text" name="province" value="{{ old('province') }}"
                                       class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('province') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       maxlength="255" autocomplete="address-level1" placeholder="e.g., Metro Manila" />
                                @error('province')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="min-w-0">
                                <label for="postal_code" class="block text-xs font-bold tracking-wider uppercase text-gray-600">Postal Code <span class="text-red-500" aria-hidden="true">*</span></label>
                                <input id="postal_code" x-ref="postal_code" type="text" name="postal_code" value="{{ old('postal_code') }}"
                                       class="block mt-2 h-[52px] w-full rounded-xl border-gray-300 bg-white px-4 text-[15px] text-gray-900 placeholder:text-gray-400 focus:border-teal focus:ring-4 focus:ring-teal/15 @error('postal_code') border-red-400 focus:border-red-500 focus:ring-red-500 @enderror"
                                       required maxlength="10" inputmode="numeric" autocomplete="postal-code" placeholder="e.g., 6000" />
                                @error('postal_code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    </div>
                </section>

                {{-- ============ STEP 3 — SUPPORTING DOCUMENTS ============ --}}
                <section x-cloak x-show="step === 2" aria-labelledby="wizard-step-3" class="mt-6">
                    <h3 id="wizard-step-3" tabindex="-1" x-ref="stepHeading2" class="text-[20px] sm:text-[23px] font-bold tracking-tight text-[#1F2933] focus:outline-none">
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
                                <label for="documents_{{ $type }}" class="block text-xs font-bold tracking-wider uppercase text-gray-600">
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
                    <h3 id="wizard-step-4" tabindex="-1" x-ref="stepHeading3" class="text-[20px] sm:text-[23px] font-bold tracking-tight text-[#1F2933] focus:outline-none">
                        Review your application.
                    </h3>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Check your details. You can go back and edit any step below.</p>

                    <div class="mt-6 divide-y divide-gray-100 rounded-2xl border border-gray-200/70 shadow-sm">
                        {{-- Center & Owner --}}
                        <div class="p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Center &amp; Owner</h4>
                                <button type="button" x-on:click="goTo(0)"
                                        class="min-h-[44px] rounded-lg px-3 py-2 text-xs font-semibold text-teal hover:bg-teal-light focus:outline-none focus-visible:ring-2 focus-visible:ring-teal">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Center name</dt><dd class="min-w-0 break-words" x-text="inputValue('business_name') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Owner name</dt><dd class="min-w-0 break-words" x-text="([inputValue('owner_first_name'), inputValue('owner_middle_name'), inputValue('owner_last_name')].filter(Boolean).join(' ')) || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">SEC number</dt><dd class="min-w-0 break-words" x-text="inputValue('sec_number') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">TIN</dt><dd class="min-w-0 break-words" x-text="inputValue('tin') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Email</dt><dd class="min-w-0 break-words" x-text="inputValue('email') || 'Not provided'"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Phone</dt><dd class="min-w-0 break-words" x-text="inputValue('phone') || 'Not provided'"></dd></div>
                            </dl>
                        </div>

                        {{-- Location --}}
                        <div class="p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-gray-500">Location</h4>
                                <button type="button" x-on:click="goTo(1)"
                                        class="min-h-[44px] rounded-lg px-3 py-2 text-xs font-semibold text-teal hover:bg-teal-light focus:outline-none focus-visible:ring-2 focus-visible:ring-teal">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Region</dt><dd class="min-w-0 break-words" x-text="regionName()"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Address</dt><dd class="min-w-0 break-words" x-text="locationSummary()"></dd></div>
                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3"><dt class="w-40 flex-none font-semibold text-gray-500">Postal code</dt><dd class="min-w-0 break-words" x-text="inputValue('postal_code') || 'Not provided'"></dd></div>
                            </dl>
                        </div>

                        {{-- Documents --}}
                        <div class="p-5">
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

                    {{-- Terms consent (required server-side; submit stays disabled until checked) --}}
                    <label class="mt-5 flex cursor-pointer items-start gap-2.5 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-800">
                        <input type="checkbox" name="terms" value="1" x-model="termsAccepted" class="mt-1 h-4 w-4 shrink-0 accent-[#16697A]">
                        <span>I agree to the Logistics Terms of Service and consent to the processing of this application. <span class="font-bold text-teal">*</span></span>
                    </label>
                    @error('terms')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </section>

                {{-- ============ NAVIGATION CONTROLS ============ --}}
                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button"
                            id="wizard-back"
                            x-show="step > 0"
                            x-cloak
                            :disabled="submitting"
                            x-on:click="back"
                            class="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-7 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 hover:border-gray-400 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none">
                        <svg class="h-4 w-4 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>

                    <button type="button"
                            id="wizard-next"
                            x-show="step < 3"
                            x-cloak
                            :disabled="submitting"
                            x-on:click="next"
                            class="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-xl bg-teal px-9 py-3 text-sm font-bold tracking-wide text-white shadow-[0_10px_25px_-10px_rgba(22,105,122,0.55)] transition hover:bg-teal-dark active:bg-teal-dark active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none">
                        <template x-if="step === 2"><span>Review &amp; Submit</span></template>
                        <template x-if="step !== 2"><span>Next</span></template>
                    </button>

                    <button type="submit"
                            id="wizard-submit"
                            x-show="step === 3"
                            x-cloak
                            :disabled="submitting || !emailVerifiedNow() || !termsAccepted"
                            class="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-xl bg-teal px-9 py-3 text-sm font-bold tracking-wide text-white shadow-[0_10px_25px_-10px_rgba(22,105,122,0.55)] transition hover:bg-teal-dark active:bg-teal-dark active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed disabled:shadow-none">
                        <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span x-text="submitting ? 'Submitting application...' : 'Submit Application'">Submit Application</span>
                    </button>
                </div>
            </form>

            <script>
            // Full PSGC region list (no key needed). The server-rendered options
            // stay as the instant fallback; the complete national list replaces
            // them once loaded, preserving the current selection.
            (function () {
                function fillRegions() {
                    var sel = document.getElementById('region');
                    if (!sel) return;
                    fetch('https://psgc.gitlab.io/api/regions/', { headers: { Accept: 'application/json' } })
                        .then(function (res) { if (!res.ok) throw new Error('regions'); return res.json(); })
                        .then(function (list) {
                            if (!Array.isArray(list) || !list.length) return;
                            var cur = sel.value;
                            sel.querySelectorAll('option[data-psgc]').forEach(function (o) { o.remove(); });
                            list.forEach(function (rg) {
                                var o = document.createElement('option');
                                o.value = rg.code;
                                o.textContent = (rg.regionName ? rg.regionName + ' – ' : '') + rg.name;
                                o.setAttribute('data-psgc', '1');
                                if (rg.code === cur) o.selected = true;
                                sel.appendChild(o);
                            });
                        })
                        .catch(function () { /* keep the fallback options */ });
                }
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fillRegions);
                else fillRegions();
            })();
            </script>

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
