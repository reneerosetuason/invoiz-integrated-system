function heroSlider(n){
  return {
    active: 0, total: n, paused: false, tx: 0, timer: null,
    reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    ready: false,
    init(){ this.ready = true; this.play(); },
    play(){ if (this.reducedMotion || this.total < 2 || this.paused === 'hold') return; this.stop(); var self = this; this.timer = setInterval(function(){ self.next(); }, 6000); },
    stop(){ if (this.timer) { clearInterval(this.timer); this.timer = null; } },
    pause(){ this.paused = true; this.stop(); },
    next(){ this.active = (this.active + 1) % Math.max(this.total, 1); this.restart(); },
    prev(){ this.active = (this.active - 1 + Math.max(this.total, 1)) % Math.max(this.total, 1); this.restart(); },
    go(i){ this.active = i; this.restart(); },
    restart(){ if (!this.paused && !this.reducedMotion && this.total > 1) { this.play(); } },
    swipe(e){ var dx = e.changedTouches[0].clientX - this.tx; if (dx < -40) this.next(); else if (dx > 40) this.prev(); }
  };
}
(function(){
  var top = document.getElementById('toTop');
  function onScroll(){ var show = window.scrollY > 600; top.classList.toggle('hidden', !show); top.classList.toggle('flex', show); }
  window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
  top.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });
  var nav = document.getElementById('navbar');
  function onNav(){ if (!nav) return; nav.classList.toggle('scrolled', window.scrollY > 24); }
  window.addEventListener('scroll', onNav, {passive:true}); onNav();
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var els = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } }); }, {threshold:.2});
    els.forEach(function(s){ io.observe(s); });
  } else { els.forEach(function(s){ s.classList.add('in'); }); }
  document.querySelectorAll('[data-countup]').forEach(function(el){
    var end = parseInt(el.getAttribute('data-countup'), 10) || 0;
    function render(v){ el.textContent = v.toLocaleString('en-US'); }
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
  var how = document.getElementById('howline');
  if (how && 'IntersectionObserver' in window && !reduce) {
    var hio = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ how.classList.add('drawn'); hio.disconnect(); } }); }, {threshold:.25});
    hio.observe(how);
  } else if (how) { how.classList.add('drawn'); }
  var orbs = document.querySelectorAll('.orb-drift');
  if (orbs.length && !reduce) {
    var ticking = false;
    window.addEventListener('scroll', function(){
      if (ticking) return; ticking = true;
      requestAnimationFrame(function(){ var y = window.scrollY || 0; orbs.forEach(function(o, i){ o.style.transform = 'translateY(' + (y * (i % 2 ? 0.03 : -0.04)) + 'px)'; }); ticking = false; });
    }, {passive:true});
  }
})();
