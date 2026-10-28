(function(){
  var form = document.getElementById('logistics-login-form');
  if (!form) return;
  function setErr(name, msg) {
    var p = form.querySelector('[data-err-for="' + name + '"]');
    var input = form.querySelector('[name="' + name + '"]');
    if (p) { p.textContent = msg || ''; p.classList.toggle('hidden', !msg); }
    if (input) input.classList.toggle('field-error', !!msg);
    return !msg;
  }
  function emailOf() { var i = form.querySelector('[name="email"]'); return i ? i.value.trim() : ''; }
  function passOf() { var i = form.querySelector('[name="password"]'); return i ? i.value : ''; }
  ['email', 'password'].forEach(function(n){
    var i = form.querySelector('[name="' + n + '"]');
    if (i) i.addEventListener('blur', function(){
      if (n === 'email') {
        if (!emailOf()) setErr(n, 'Please enter your email address.');
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailOf())) setErr(n, 'Please enter a valid email address.');
        else setErr(n, '');
      } else if (!passOf()) setErr(n, 'Please enter your password.');
      else setErr(n, '');
    });
  });
  form.addEventListener('submit', function(e){
    var okEmail = emailOf() ? (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailOf()) || !setErr('email', 'Please enter a valid email address.')) : !setErr('email', 'Please enter your email address.');
    var okPass = passOf() ? setErr('password', '') : !setErr('password', 'Please enter your password.');
    if (!okEmail || !okPass) e.preventDefault();
  });
  document.querySelectorAll('[data-countup]').forEach(function(el){
    var end = parseInt(el.getAttribute('data-countup'), 10) || 0;
    function render(v){ el.textContent = v.toLocaleString('en-US'); }
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !('IntersectionObserver' in window)) { render(end); return; }
    var done = false;
    var cio = new IntersectionObserver(function(es){ es.forEach(function(e){
      if (e.isIntersecting && !done) { done = true; cio.disconnect();
        var t0 = null;
        function frame(t){ if (!t0) t0 = t; var p = Math.min(1, (t - t0) / 1200); render(Math.round(end * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(frame); }
        requestAnimationFrame(frame);
      }
    }); }, {threshold:.4});
    cio.observe(el);
  });
})();
