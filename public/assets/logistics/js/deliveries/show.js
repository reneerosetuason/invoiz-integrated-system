var invoizData_68dbadcd18 = JSON.parse(document.currentScript.dataset.config);

        (function () {
            var qrTarget = invoizData_68dbadcd18[0];

            var qrEl = document.getElementById('parcel-qr');
            if (qrEl && typeof QRCode !== 'undefined') {
                new QRCode(qrEl, {
                    text: qrTarget,
                    width: 128,
                    height: 128,
                    correctLevel: QRCode.CorrectLevel.H,
                });
            }

            var barEl = document.getElementById('parcel-barcode');
            if (barEl && typeof JsBarcode !== 'undefined') {
                try {
                    JsBarcode(barEl, qrTarget, {
                        format: 'CODE128',
                        displayValue: false,
                        height: 64,
                        width: 2,
                        margin: 0,
                        background: '#ffffff',
                        lineColor: '#111111',
                    });
                } catch (e) {
                    barEl.outerHTML = '<p class="font-mono text-sm font-bold">' + qrTarget + '</p>';
                }
            }

            var centerSelect = document.getElementById('destination_center');
            var areaSelect = document.getElementById('service_area');
            if (centerSelect && areaSelect) {
                var filterServiceAreas = function () {
                    Array.from(areaSelect.options).forEach(function (option) {
                        if (!option.value) return;
                        option.hidden = option.dataset.centerId !== centerSelect.value;
                        if (option.hidden && option.selected) areaSelect.value = '';
                    });
                };

                centerSelect.addEventListener('change', filterServiceAreas);
                filterServiceAreas();
            }
        })();
