var invoizData_8870f012e1 = JSON.parse(document.currentScript.dataset.config);

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
                    expect: invoizData_8870f012e1[0],
                    submitUrl: invoizData_8870f012e1[1],
                    _stream: null,
                    _raf: 0,
                    _lastCode: '',
                    _lastHandledAt: 0,
                    _canvas: null,
                    _ctx: null,
                    _cameraRequest: 0,

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
                            this.state = 'running';
                            this.message = 'Point the camera at the parcel QR code and hold steady.';
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
                                // x-show reveals the panel on the next frame.
                                requestAnimationFrame(() => {
                                    const el = this.$refs.resultPanel;
                                    if (el && this.result) el.focus({ preventScroll: true });
                                });
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
