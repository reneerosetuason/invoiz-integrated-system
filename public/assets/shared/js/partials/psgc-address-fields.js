var invoizData_d8545c190d = JSON.parse(document.currentScript.dataset.config);

    (function () {
        var config = {
            region: invoizData_d8545c190d[0],
            province: invoizData_d8545c190d[1],
            city: invoizData_d8545c190d[2],
            barangay: invoizData_d8545c190d[3],
            values: invoizData_d8545c190d[4],
            placeholders: invoizData_d8545c190d[5],
            regionFromProvince: invoizData_d8545c190d[6]
        };
        function boot() {
            if (window.initPsgcAddress) {
                window.initPsgcAddress(config);
                return;
            }
            document.addEventListener('DOMContentLoaded', function () {
                if (window.initPsgcAddress) {
                    window.initPsgcAddress(config);
                }
            });
        }
        boot();
    })();
