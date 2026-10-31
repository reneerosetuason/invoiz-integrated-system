var invoizData_bb23c8a4ce = JSON.parse(document.currentScript.dataset.config);

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
                        const res = await fetch(invoizData_bb23c8a4ce[0], {
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
                    const email = (this.codeSentTo || this.inputValue('email') || '').toLowerCase().trim();
                    if (!email) {
                        this.verifyMsgOk = false;
                        this.verifyMsg = 'Please enter your email address.';
                        return;
                    }
                    const code = (this.verifyCode ?? '').toString().replace(/\D/g, '').trim();
                    if (!code) {
                        this.verifyMsgOk = false;
                        this.verifyMsg = 'Please enter the 6-digit verification code.';
                        return;
                    }
                    if (code.length !== 6) {
                        this.verifyMsgOk = false;
                        this.verifyMsg = 'Please enter the full 6-digit verification code sent to your email.';
                        return;
                    }
                    this.verifyBusy = true;
                    this.verifyMsg = '';
                    try {
                        const res = await fetch(invoizData_bb23c8a4ce[1], {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                            body: JSON.stringify({ email: email, code: code }),
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
