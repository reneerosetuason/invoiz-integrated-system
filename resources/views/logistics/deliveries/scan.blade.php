<x-app-layout>
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('deliveries.index') }}"
           class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm" aria-label="Back to deliveries">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Parcel Scanner</h1>
            <p class="text-sm text-gray-500 mt-0.5">Scan a parcel's QR code to verify and move it from Received to Scanned.</p>
        </div>
    </div>

    @if($expect)
        <div class="mb-6 flex items-start gap-3 bg-teal-light border border-teal/20 rounded-2xl p-4">
            <svg class="h-5 w-5 flex-shrink-0 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-sm text-teal-dark">
                <p class="font-semibold">Expected parcel: <span class="font-mono">{{ $expect }}</span></p>
                @if($expectedDelivery && $expectedDelivery->recipient_name)
                    <p class="mt-0.5">{{ $expectedDelivery->recipient_name }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6"
         x-data="scanParcel()"
         x-init="init()"
         x-cloak>

        {{-- Camera / Scanner --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h2 class="text-base font-semibold text-gray-900">Camera Scanner</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-show="['idle','stopped','denied','unsupported','error'].includes(state) || state === 'done'" x-cloak
                            @click="scanAnother(); start()"
                            class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-4 py-2.5 rounded-xl transition shadow-sm text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span x-text="state === 'done' ? 'Scan Another Parcel' : 'Start Camera'"></span>
                    </button>
                    <button type="button" x-show="state === 'running'" x-cloak
                            @click="stop()"
                            class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2.5 rounded-xl transition text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                        Stop Camera
                    </button>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-xl bg-gray-900 aspect-video">
                <video ref="video" autoplay playsinline muted
                       x-show="state === 'running' || state === 'starting'" x-cloak
                       class="absolute inset-0 w-full h-full object-cover"></video>
                <canvas ref="canvas" class="hidden"></canvas>

                {{-- Scan guide overlay --}}
                <div x-show="state === 'running'" x-cloak
                     class="absolute inset-0 z-10" aria-hidden="true">
                    <div class="scan-overlay"></div>
                </div>

                {{-- Placeholder when camera is not running --}}
                <div x-show="['idle','stopped','denied','unsupported','error','done'].includes(state)" x-cloak
                     class="absolute inset-0 z-10 flex flex-col items-center justify-center text-center px-6">
                    <svg class="h-12 w-12 text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <template x-if="state === 'done'">
                        <p class="text-gray-300 font-semibold text-sm">Parcel verified — press "Scan Another Parcel" to continue.</p>
                    </template>
                    <template x-if="state === 'denied'">
                        <p class="text-gray-300 font-semibold text-sm">Camera permission denied. Allow camera access or use manual entry below.</p>
                    </template>
                    <template x-if="state === 'unsupported'">
                        <p class="text-gray-300 font-semibold text-sm">Camera scanning is not supported in this browser. Use manual entry below.</p>
                    </template>
                    <template x-if="state !== 'done' && state !== 'denied' && state !== 'unsupported' && state !== 'error'">
                        <p class="text-gray-300 font-semibold text-sm">Press "Start Camera" to begin scanning.</p>
                    </template>
                </div>

                {{-- Busy overlay --}}
                <div x-show="busy" x-cloak
                     class="absolute inset-0 z-20 bg-black/60 flex items-center justify-center">
                    <p class="text-white font-semibold text-sm">Verifying parcel…</p>
                </div>
            </div>

            <p x-show="message" x-text="message" aria-live="polite"
               class="mt-3 text-sm text-gray-600" x-cloak></p>

            <p x-show="alertText" x-text="alertText" role="alert"
               class="mt-3 text-sm text-red-600" x-cloak></p>

            <p class="mt-2 text-xs text-gray-400">Frames are decoded on this device only — no images are uploaded.</p>
        </div>

        {{-- Result / Manual entry --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6 flex flex-col gap-5">

            {{-- Verification result --}}
            <div x-show="result" x-cloak tabindex="-1" ref="resultPanel" class="rounded-2xl outline-none">
                <template x-if="result">
                    <div :class="alreadyScanned ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200'"
                         class="rounded-2xl border p-4">
                        <div class="flex items-start gap-3">
                            <div :class="alreadyScanned ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                                 class="h-9 w-9 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p x-text="alreadyScanned ? 'Parcel Already Scanned' : 'Parcel Verified'" 
                                   :class="alreadyScanned ? 'text-amber-800' : 'text-green-800'"
                                   class="font-bold text-sm"></p>
                                <p class="mt-0.5 font-mono font-semibold text-gray-900 break-all" x-text="result.tracking_number"></p>
                                <dl class="mt-2 space-y-1 text-sm">
                                    <div><dt class="text-xs text-gray-500 inline">Recipient: </dt><dd class="inline font-medium text-gray-800" x-text="result.recipient_name || '—'"></dd></div>
                                    <div><dt class="text-xs text-gray-500 inline">Status: </dt><dd class="inline font-medium text-gray-800" x-text="result.parcel_status"></dd></div>
                                    <div><dt class="text-xs text-gray-500 inline">Scanned at: </dt><dd class="inline font-medium text-gray-800" x-text="result.scanned_at ? new Date(result.scanned_at).toLocaleString() : '—'"></dd></div>
                                </dl>
                            </div>
                        </div>

                        <p x-show="mismatch" role="alert" x-cloak
                           class="mt-3 text-xs text-amber-700 bg-amber-100 rounded-lg px-3 py-2"
                           x-text="'Scanned parcel does not match the expected parcel ' + expect + ' in the previous step. Review the details above before continuing.'"></p>

                        <a x-show="result.show_url" :href="result.show_url"
                           class="mt-4 inline-flex items-center gap-2 text-teal hover:text-teal-dark font-semibold text-sm">
                            View delivery details
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    </div>
                </template>
            </div>

            {{-- Manual entry fallback --}}
            <div class="mt-auto">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Manual Entry</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Camera not working? Type the parcel's tracking number.</p>
                <form @submit.prevent="submitManual()">
                    <label for="manual_code" class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" id="manual_code" name="tracking_number"
                               x-model="formCode"
                               autocomplete="off" autocapitalize="characters" spellcheck="false"
                               placeholder="TRK-YYYYMMDD-XXXX"
                               class="flex-1 min-w-0 rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm font-mono">
                        <button type="submit"
                                :disabled="busy"
                                class="bg-indigo-500 hover:bg-indigo-600 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm text-sm whitespace-nowrap">
                            Verify Manually
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Format: <span class="font-mono">TRK-</span> followed by the date and 4-character code.</p>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('vendor/jsqr/jsQR.js') }}"></script>
        <style>
            .scan-overlay {
                position: absolute;
                inset: 0;
                margin: auto;
                width: 62%;
                max-width: 280px;
                height: 62%;
                max-height: 280px;
                border: 2px solid rgba(255, 255, 255, 0.9);
                border-radius: 16px;
                box-shadow: 0 0 0 2000px rgba(0, 0, 0, 0.35);
                animation: scan-pulse 1.8s ease-in-out infinite;
            }
            .scan-overlay::before, .scan-overlay::after {
                content: '';
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                height: 3px;
                background: #F0A202;
                border-radius: 9999px;
            }
            .scan-overlay::before { top: 18%; width: 30%; }
            .scan-overlay::after { bottom: 18%; width: 30%; }
            @keyframes scan-pulse {
                0%, 100% { opacity: 0.9; }
                50% { opacity: 0.55; }
            }
            @media (prefers-reduced-motion: reduce) {
                .scan-overlay { animation: none; }
            }
        </style>
        <script>
            function scanParcel() {
                return {
                    state: 'idle',
                    busy: false,
                    message: '',
                    alertText: '',
                    result: null,
                    alreadyScanned: false,
                    mismatch: false,
                    formCode: '',
                    expect: @json($expect),
                    submitUrl: @json(route('deliveries.scan-verify')),
                    _stream: null,
                    _raf: 0,
                    _lastCode: '',
                    _lastHandledAt: 0,
                    _canvas: null,
                    _ctx: null,

                    init() {
                        window.addEventListener('beforeunload', () => this.stopCamera());
                        // A hidden tab keeps the camera hardware on (requestAnimationFrame
                        // just throttles), so pause capture when the page is hidden.
                        // The user restarts explicitly on return — never auto-resume.
                        document.addEventListener('visibilitychange', () => {
                            if (!document.hidden) return;
                            this.stopCamera();
                            if (this.state === 'running' || this.state === 'starting') {
                                this.state = 'stopped';
                                this.message = 'Camera paused because the tab was hidden. Press Start Camera to resume.';
                            }
                        });
                    },

                    get canUseCamera() {
                        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.jsQR);
                    },

                    canvas() {
                        if (!this._canvas) {
                            this._canvas = this.$refs.canvas;
                            this._ctx = this._canvas.getContext('2d', { willReadFrequently: true });
                        }
                        return this._canvas;
                    },

                    start() {
                        this.message = '';
                        this.alertText = '';
                        if (!this.canUseCamera) {
                            this.state = 'unsupported';
                            this.alertText = 'Camera scanning is not available in this browser. Use the manual entry field below instead.';
                            return;
                        }
                        this.state = 'starting';
                        this.message = 'Requesting camera access…';
                        navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                            audio: false,
                        }).then(stream => {
                            this._stream = stream;
                            const video = this.$refs.video;
                            video.srcObject = stream;
                            this.state = 'running';
                            this.message = 'Point the camera at the parcel QR code and hold steady.';
                            this._raf = requestAnimationFrame(() => this.tick());
                        }).catch(err => {
                            const name = err && err.name;
                            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                                this.state = 'denied';
                                this.alertText = 'Camera permission was denied. Allow camera access in your browser settings, or use the manual entry field below.';
                            } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                                this.state = 'error';
                                this.alertText = 'No camera was found on this device. Use the manual entry field below instead.';
                            } else if (name === 'NotReadableError' || name === 'TrackStartError') {
                                this.state = 'error';
                                this.alertText = 'The camera is in use by another application. Close it and try again, or use manual entry.';
                            } else if (name === 'OverconstrainedError') {
                                this.state = 'error';
                                this.alertText = 'The camera could not be started. Try again or use manual entry below.';
                            } else {
                                this.state = 'error';
                                this.alertText = 'Could not start the camera. Use the manual entry field below instead.';
                            }
                        });
                    },

                    stopCamera() {
                        if (this._raf) { cancelAnimationFrame(this._raf); this._raf = 0; }
                        if (this._stream) {
                            this._stream.getTracks().forEach(t => t.stop());
                            this._stream = null;
                        }
                        const video = this.$refs.video;
                        if (video) video.srcObject = null;
                    },

                    stop() {
                        this.stopCamera();
                        if (this.state === 'running' || this.state === 'starting') this.state = 'stopped';
                        this.message = 'Camera stopped. You can start it again or use manual entry.';
                    },

                    tick() {
                        const video = this.$refs.video;
                        if (!video || !this._stream || !video.videoWidth) {
                            this._raf = requestAnimationFrame(() => this.tick());
                            return;
                        }
                        if (video.readyState === video.HAVE_ENOUGH_DATA) {
                            const canvas = this.canvas();
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            if (canvas.width > 0 && canvas.height > 0) {
                                this._ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                try {
                                    const img = this._ctx.getImageData(0, 0, canvas.width, canvas.height);
                                    const code = window.scanParcelDecode(img);
                                    if (code) this.onDecode(code);
                                } catch (e) { /* frame decode failed — keep scanning */ }
                            }
                        }
                        this._raf = requestAnimationFrame(() => this.tick());
                    },

                    onDecode(code) {
                        if (this.busy || this.state === 'done') return;
                        code = String(code).trim();
                        const now = Date.now();
                        if (code === this._lastCode && now - this._lastHandledAt < 3000) return;
                        this._lastCode = code;
                        this._lastHandledAt = now;
                        this.verify(code);
                    },

                    normalize(value) {
                        return String(value || '').trim().toUpperCase();
                    },

                    isFormatValid(value) {
                        return /^TRK-\d{8}-[A-Z0-9]{4}$/.test(value);
                    },

                    verify(code) {
                        const value = this.normalize(code);
                        if (!value) {
                            this.alertText = 'No tracking number detected. Try scanning again.';
                            return;
                        }
                        if (!this.isFormatValid(value)) {
                            this.alertText = '"' + value + '" is not a valid parcel tracking number. Expected format: TRK-YYYYMMDD-XXXX.';
                            return;
                        }
                        this.busy = true;
                        this.message = 'Verifying parcel…';
                        this.alertText = '';
                        fetch(this.submitUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            body: JSON.stringify({ tracking_number: value }),
                        }).then(async r => {
                            // Parse defensively: an expired session or a server
                            // error page returns HTML, not JSON. Never let a
                            // parse failure surface as a confusing message.
                            let data = null;
                            try { data = await r.json(); } catch (e) { data = null; }
                            return { ok: r.ok, data: data, parsed: !!data };
                        })
                          .then(({ ok, data, parsed }) => {
                              this.busy = false;
                              if (!parsed) {
                                  this.state = 'error';
                                  this.alertText = 'Unexpected server response. Your session may have expired — refresh the page and try again.';
                                  return;
                              }
                              this.handleResponse(data);
                          })
                          .catch(() => {
                              this.busy = false;
                              this.alertText = 'Could not reach the server. Check your connection and try again.';
                          });
                    },

                    handleResponse(data) {
                        this.result = data.delivery || null;
                        this.mismatch = false;
                        if (this.result && this.expect && this.normalize(this.result.tracking_number) !== this.normalize(this.expect)) {
                            this.mismatch = true;
                        }
                        if (data.ok) {
                            this.state = 'done';
                            this.stopCamera();
                            this.alreadyScanned = !!data.already;
                            this.message = '';
                            this.alertText = '';
                            this.$nextTick(() => {
                                const el = this.$refs.resultPanel;
                                if (el) el.focus({ preventScroll: true });
                            });
                        } else {
                            this.state = 'error';
                            this.alertText = data.message || 'The parcel could not be verified.';
                        }
                    },

                    scanAnother() {
                        this.result = null;
                        this.alreadyScanned = false;
                        this.mismatch = false;
                        this.message = '';
                        this.alertText = '';
                        this._lastCode = '';
                        this.state = 'idle';
                    },

                    submitManual() {
                        const value = this.normalize(this.formCode);
                        if (!value) {
                            this.alertText = 'Enter a tracking number to verify.';
                            return;
                        }
                        this.verify(value);
                    },
                };
            }
            window.scanParcel = scanParcel;
            window.scanParcelDecode = function (imageData) {
                try {
                    const r = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                    return r ? r.data : null;
                } catch (e) {
                    return null;
                }
            };
        </script>
    @endpush
</x-app-layout>