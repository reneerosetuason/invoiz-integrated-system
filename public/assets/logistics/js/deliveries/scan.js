var invoizData_8870f012e1 = (function () {
    try {
        if (typeof document !== 'undefined' && document.currentScript && document.currentScript.dataset && document.currentScript.dataset.config) {
            return JSON.parse(document.currentScript.dataset.config);
        }
    } catch (e) {}
    return [null, '/logistics/deliveries/scan-verify', '/logistics/deliveries/scan-lookup'];
})();

function scanParcel(options) {
    options = options || {};
    var rawExpect = options.expect;
    var rawSubmit = options.submitUrl;
    var rawLookup = options.lookupUrl;

    if (!rawExpect && typeof invoizData_8870f012e1 !== 'undefined' && Array.isArray(invoizData_8870f012e1)) {
        rawExpect = invoizData_8870f012e1[0];
    }
    if (!rawExpect && typeof window !== 'undefined') {
        try {
            var params = new URLSearchParams(window.location.search);
            rawExpect = params.get('expect') || '';
        } catch (e) {
            rawExpect = '';
        }
    }

    if (!rawSubmit && typeof invoizData_8870f012e1 !== 'undefined' && Array.isArray(invoizData_8870f012e1)) {
        rawSubmit = invoizData_8870f012e1[1];
    }
    if (!rawSubmit) {
        rawSubmit = '/logistics/deliveries/scan-verify';
    }

    if (!rawLookup && typeof invoizData_8870f012e1 !== 'undefined' && Array.isArray(invoizData_8870f012e1)) {
        rawLookup = invoizData_8870f012e1[2];
    }
    if (!rawLookup) {
        rawLookup = '/logistics/deliveries/scan-lookup';
    }

    // Always normalize URLs to root-relative paths on the current origin.
    // If an absolute URL was provided (e.g. "http://localhost:8000/..."),
    // extracting pathname + search prevents CORS rejection on 127.0.0.1 and
    // Mixed Content blocking on https://invoiz.store.
    function rootRelative(url, fallback) {
        try {
            if (typeof url === 'string' && (url.indexOf('http://') === 0 || url.indexOf('https://') === 0)) {
                var parsedUrl = new URL(url, window.location.origin);
                return parsedUrl.pathname + parsedUrl.search;
            }
        } catch (e) {
            return fallback;
        }
        return url || fallback;
    }

    rawSubmit = rootRelative(rawSubmit, '/logistics/deliveries/scan-verify');
    rawLookup = rootRelative(rawLookup, '/logistics/deliveries/scan-lookup');

    var initialExpect = rawExpect ? String(rawExpect).trim().toUpperCase() : '';

    return {
        state: 'idle',
        busy: false,
        busyLabel: 'Looking up parcel…',
        message: '',
        alertText: '',
        result: null,
        alreadyScanned: false,
        readyToConfirm: false,
        confirmed: false,
        mismatch: false,
        warningType: '',
        conditionWarning: '',
        formCode: initialExpect,
        expect: initialExpect,
        lookupCode: '',
        submitUrl: rawSubmit,
        lookupUrl: rawLookup,
        _stream: null,
        _raf: 0,
        _lastCode: '',
        _lastHandledAt: 0,
        _canvas: null,
        _ctx: null,
        _cameraRequest: 0,
        _detector: undefined,
        _detecting: false,

        init() {
            if (this.$el) {
                if (this.$el.dataset && this.$el.dataset.expect && !this.expect) {
                    this.expect = this.normalize(this.$el.dataset.expect);
                    if (!this.formCode) this.formCode = this.expect;
                }
                if (this.$el.dataset && this.$el.dataset.submitUrl) {
                    this.submitUrl = rootRelative(this.$el.dataset.submitUrl, this.submitUrl);
                }
                if (this.$el.dataset && this.$el.dataset.lookupUrl) {
                    this.lookupUrl = rootRelative(this.$el.dataset.lookupUrl, this.lookupUrl);
                }
            }
            if (!this.formCode && this.expect) {
                this.formCode = this.expect;
            }

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
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && (window.BarcodeDetector || window.jsQR));
        },

        // Prefer the native detector for the Code128 waybill barcode. Older
        // browsers keep the jsQR path so any QR label still in the field can
        // be read; the backend validates the payload either way.
        barcodeDetector() {
            if (this._detector !== undefined) return this._detector;
            this._detector = null;
            try {
                if (typeof window.BarcodeDetector === 'function') {
                    try {
                        this._detector = new window.BarcodeDetector({ formats: ['code128', 'qr_code'] });
                    } catch (e) {
                        this._detector = new window.BarcodeDetector({ formats: ['code128'] });
                    }
                }
            } catch (e) {
                this._detector = null;
            }
            return this._detector;
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
            const cameraRequest = ++this._cameraRequest;
            this.state = 'starting';
            this.message = 'Requesting camera access…';
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            }).then(stream => {
                if (cameraRequest !== this._cameraRequest || document.hidden) {
                    stream.getTracks().forEach(track => track.stop());
                    return;
                }
                this._stream = stream;
                const video = this.$refs.video;
                video.srcObject = stream;
                this.barcodeDetector();
                this.state = 'running';
                this.message = 'Point the camera at the parcel barcode and hold steady.';
                this._raf = requestAnimationFrame(() => this.tick());
            }).catch(err => {
                if (cameraRequest !== this._cameraRequest) return;
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
            this._cameraRequest++;
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
                if (this.barcodeDetector()) {
                    this.detectBarcode(video);
                } else {
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
            }
            this._raf = requestAnimationFrame(() => this.tick());
        },

        detectBarcode(video) {
            if (this._detecting) return;
            this._detecting = true;
            const canvas = this.canvas();
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            if (!canvas.width || !canvas.height) {
                this._detecting = false;
                return;
            }
            this._ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            this._detector.detect(canvas).then(codes => {
                if (!this._stream) return;
                const code = codes && codes.length ? codes[0].rawValue : null;
                if (code) this.onDecode(code);
            }).catch(() => {}).finally(() => {
                this._detecting = false;
            });
        },

        onDecode(code) {
            if (this.busy || this.readyToConfirm || this.state === 'done') return;
            code = String(code).trim();
            const now = Date.now();
            if (code === this._lastCode && now - this._lastHandledAt < 3000) return;
            this._lastCode = code;
            this._lastHandledAt = now;
            this.lookup(code);
        },

        normalize(value) {
            return String(value || '').trim().toUpperCase();
        },

        isFormatValid(value) {
            return /^TRK-\d{8}-[A-Z0-9]{4}$/.test(value);
        },

        csrfToken() {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            return csrfMeta ? csrfMeta.getAttribute('content') : '';
        },

        postTracking(url, value) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ tracking_number: value }),
            }).then(async r => {
                let data = null;
                try { data = await r.json(); } catch (e) { data = null; }
                return { ok: r.ok, status: r.status, data: data, parsed: !!data };
            });
        },

        // Step 1: read-only lookup. A barcode scan or manual entry only shows
        // the matched parcel; nothing is marked as scanned until confirm().
        lookup(code) {
            if (this.busy) return;

            const value = this.normalize(code);
            if (!value) {
                this.message = '';
                this.alertText = 'No tracking number detected. Try scanning again.';
                return;
            }
            if (!this.isFormatValid(value)) {
                this.message = '';
                this.alertText = '"' + value + '" is not a valid parcel tracking number. Expected format: TRK-YYYYMMDD-XXXX.';
                return;
            }

            this.busy = true;
            this.busyLabel = 'Looking up parcel…';
            this.message = '';
            this.alertText = '';
            this.result = null;
            this.alreadyScanned = false;
            this.readyToConfirm = false;
            this.confirmed = false;
            this.warningType = '';
            this.conditionWarning = '';
            this.lookupCode = '';

            this.postTracking(this.lookupUrl, value)
              .then(({ ok, status, data, parsed }) => {
                  this.busy = false;

                  if (status === 419) {
                      this.state = 'error';
                      this.message = '';
                      this.alertText = 'Your session has expired. Please refresh the page and try again.';
                      return;
                  }

                  if (!parsed) {
                      this.state = 'error';
                      this.message = '';
                      this.alertText = 'Unexpected server response. Please refresh the page and try again.';
                      return;
                  }

                  this.handleLookupResponse(data, status, value);
              })
              .catch(() => {
                  this.busy = false;
                  this.message = '';
                  this.state = 'error';
                  this.alertText = 'Could not reach the server. Check your connection and try again.';
              });
        },

        handleLookupResponse(data, status, value) {
            this.message = '';
            this.result = (data && data.delivery) ? data.delivery : null;
            this.warningType = (data && data.warning_type) ? data.warning_type : '';
            this.conditionWarning = (data && data.condition_warning) ? data.condition_warning : '';
            this.mismatch = this.result && this.expect
                && this.normalize(this.result.tracking_number) !== this.normalize(this.expect);

            if (data && data.ok && data.already) {
                this.state = 'done';
                this.stopCamera();
                this.alreadyScanned = true;
                this.readyToConfirm = false;
                this.confirmed = false;
                this.message = data.message || 'Parcel was already scanned.';
                this.alertText = '';
                this.focusResult();
                return;
            }

            if (data && data.ok && data.can_confirm) {
                this.state = 'found';
                this.stopCamera();
                this.alreadyScanned = false;
                this.readyToConfirm = true;
                this.confirmed = false;
                this.lookupCode = value;
                this.message = data.message || 'Parcel found. Review the details and confirm to verify it.';
                this.alertText = '';
                this.focusResult();
                return;
            }

            this.state = 'error';
            this.readyToConfirm = false;
            this.confirmed = false;
            this.alertText = this.errorMessage(data, status);
        },

        // Step 2: the operator explicitly confirms the reviewed parcel. Only
        // now does the existing scan-verify endpoint mark it as scanned.
        confirm() {
            if (this.busy || !this.readyToConfirm || !this.lookupCode) return;

            this.busy = true;
            this.busyLabel = 'Verifying parcel…';
            this.message = 'Verifying parcel…';
            this.alertText = '';

            this.postTracking(this.submitUrl, this.lookupCode)
              .then(({ status, data, parsed }) => {
                  this.busy = false;

                  if (status === 419) {
                      this.message = '';
                      this.state = 'found';
                      this.alertText = 'Your session has expired. Please refresh the page and try again.';
                      return;
                  }

                  if (!parsed) {
                      this.message = '';
                      this.state = 'found';
                      this.alertText = 'Unexpected server response. Please refresh the page and try again.';
                      return;
                  }

                  this.handleConfirmResponse(data, status);
              })
              .catch(() => {
                  this.busy = false;
                  this.message = '';
                  this.state = 'found';
                  this.alertText = 'Could not reach the server. Check your connection and try again.';
              });
        },

        handleConfirmResponse(data, status) {
            this.message = '';
            if (data && data.delivery) {
                this.result = data.delivery;
            }
            if (data && data.warning_type) {
                this.warningType = data.warning_type;
            }
            if (data && data.condition_warning) {
                this.conditionWarning = data.condition_warning;
            }

            if (data && data.ok) {
                this.state = 'done';
                this.stopCamera();
                this.alreadyScanned = !!data.already;
                this.readyToConfirm = false;
                this.confirmed = !data.already;
                this.lookupCode = '';
                this.message = data.message || (data.already ? 'Parcel was already scanned.' : 'Parcel scanned successfully.');
                this.alertText = '';
                this.focusResult();
                return;
            }

            this.state = 'error';
            this.readyToConfirm = false;
            this.confirmed = false;
            this.alertText = this.errorMessage(data, status);
        },

        errorMessage(data, status) {
            let msg = data && data.message;
            if (!msg && data && data.errors && data.errors.tracking_number) {
                msg = Array.isArray(data.errors.tracking_number)
                    ? data.errors.tracking_number[0]
                    : data.errors.tracking_number;
            }
            if (msg) return msg;

            if (status === 404) {
                return 'Parcel not found. Check the tracking number and try again.';
            }
            if (status === 403) {
                return 'This parcel belongs to another logistics center or you do not have permission to verify it.';
            }
            if (status === 422) {
                return 'The tracking number is invalid or cannot be verified in its current state.';
            }
            if (status >= 500) {
                return 'A server error occurred. Please try again later.';
            }
            return 'The parcel could not be verified.';
        },

        focusResult() {
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    const el = this.$refs.resultPanel;
                    if (el && this.result) el.focus({ preventScroll: true });
                });
            });
        },

        scanAnother() {
            this.result = null;
            this.alreadyScanned = false;
            this.readyToConfirm = false;
            this.confirmed = false;
            this.warningType = '';
            this.conditionWarning = '';
            this.lookupCode = '';
            this.mismatch = false;
            this.message = '';
            this.alertText = '';
            this._lastCode = '';
            this.state = 'idle';
        },

        submitManual() {
            if (this.busy) return;
            const value = this.normalize(this.formCode);
            if (!value) {
                this.message = '';
                this.alertText = 'Enter a tracking number to look up.';
                return;
            }
            this.lookup(value);
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
