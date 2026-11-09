var invoizData_d58e567d5c = (function () {
    try {
        if (typeof document !== 'undefined' && document.currentScript && document.currentScript.dataset && document.currentScript.dataset.config) {
            return JSON.parse(document.currentScript.dataset.config);
        }
    } catch (e) {}
    return [null];
})();

(function () {
    var barEl = document.getElementById('seller-barcode');
    var code = (invoizData_d58e567d5c && invoizData_d58e567d5c[0]) || (barEl && barEl.dataset.tracking) || '';
    if (!code) return;

    if (barEl && typeof JsBarcode !== 'undefined') {
        try {
            JsBarcode(barEl, code, {
                format: 'CODE128',
                displayValue: false,
                height: 28,
                width: 1.3,
                margin: 0,
                background: '#ffffff',
                lineColor: '#111111'
            });
        } catch (e) {
            barEl.outerHTML = '<p class="font-mono text-xs font-bold">' + code + '</p>';
        }
    }
})();
