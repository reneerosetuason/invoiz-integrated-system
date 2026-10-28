(function () {
var box = document.getElementById('buyer-terms');
var btn = document.getElementById('buyer-register-btn');
function sync() { if (btn) btn.disabled = !(box && box.checked); }
if (box && btn) { box.addEventListener('change', sync); sync(); }
})();
