(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var els = document.querySelectorAll('.rv,.rv-r');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } }); }, {threshold:.15});
    els.forEach(function(s){ io.observe(s); });
  } else { els.forEach(function(s){ s.classList.add('in'); }); }
  document.querySelectorAll('[data-pw-toggle]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      var eye = btn.querySelector('[data-eye]');
      var eyeOff = btn.querySelector('[data-eye-off]');
      if (eye) eye.classList.toggle('hidden', show);
      if (eyeOff) eyeOff.classList.toggle('hidden', !show);
      input.focus({preventScroll:true});
    });
  });
  document.querySelectorAll('form[data-guard]').forEach(function(form){
    form.addEventListener('submit', function(){
      var btn = form.querySelector('[type="submit"]');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      var arrow = btn.querySelector('[data-arrow]');
      var spin = btn.querySelector('[data-spin]');
      if (arrow) arrow.style.display = 'none';
      if (spin) spin.classList.remove('hidden');
    });
  });
})();
