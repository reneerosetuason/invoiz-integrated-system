var invoizData_d58e567d5c = JSON.parse(document.currentScript.dataset.config);

        (function () {
            var code = invoizData_d58e567d5c[0];
            var qrEl = document.getElementById('seller-qr');
            if (qrEl && typeof QRCode !== 'undefined') {
                new QRCode(qrEl, { text: code, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.H });
            }
            var barEl = document.getElementById('seller-barcode');
            if (barEl && typeof JsBarcode !== 'undefined') {
                try {
                    JsBarcode(barEl, code, { format: 'CODE128', displayValue: false, height: 56, width: 2, margin: 0, background: '#ffffff', lineColor: '#111111' });
                } catch (e) {
                    barEl.outerHTML = '<p class="font-mono text-sm font-bold">' + code + '</p>';
                }
            }
        })();
