(function () {
            var box = document.getElementById('terms');
            var btn = document.getElementById('createStaffBtn');
            function sync() { btn.disabled = !(box && box.checked); }
            if (box && btn) { box.addEventListener('change', sync); sync(); }
        })();
