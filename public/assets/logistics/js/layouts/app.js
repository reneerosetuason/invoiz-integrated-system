var invoizData_46060e719a = JSON.parse(document.currentScript.dataset.config);

            // Apply the saved appearance before first paint (Settings →
            // Appearance). Runs inline so there is no light-mode flash.
            window.__invoizTheme = invoizData_46060e719a[0];
            (function () {
                var t = window.__invoizTheme;
                if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
                if (t === 'system' && window.matchMedia) {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                        document.documentElement.classList.toggle('dark', e.matches);
                    });
                }
            })();
