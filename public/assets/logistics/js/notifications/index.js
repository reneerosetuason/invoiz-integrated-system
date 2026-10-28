var invoizData_6c70c2d482 = JSON.parse(document.currentScript.dataset.config);

        document.querySelectorAll('[data-mark-read]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fetch(invoizData_6c70c2d482[0] + '/' + btn.dataset.markRead + '/read', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': invoizData_6c70c2d482[1], 'Accept': 'application/json' },
                }).then(function () { window.location.reload(); });
            });
        });
        var markAll = document.querySelector('[data-mark-all]');
        if (markAll) {
            markAll.addEventListener('click', function () {
                fetch(invoizData_6c70c2d482[2], {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': invoizData_6c70c2d482[3], 'Accept': 'application/json' },
                }).then(function () { window.location.reload(); });
            });
        }
