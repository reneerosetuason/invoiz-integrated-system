/* Buyer boutique motion: staggered reveals, once each. Transform/opacity only.
   Content is fully visible without JS (hidden-initial states live under html.js). */
(function () {
  'use strict';
  function init() {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var els = Array.prototype.slice.call(document.querySelectorAll('.shopx .rv, .cartx .rv, .ordx .rv'));
    if (!els.length) return;
    if (reduce || !('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('in'); });
      return;
    }
    // Stagger siblings sharing a parent (cards) by index; lone blocks get 0ms.
    var sibCount = {};
    els.forEach(function (el) {
      var p = el.parentNode;
      var key = p ? (p.className || '') + '|' + p.childNodes.length : 'root';
      sibCount[key] = (sibCount[key] || 0) + 1;
      if (p && p.children.length > 1) {
        var idx = Array.prototype.indexOf.call(p.children, el);
        el.style.transitionDelay = ((idx % 8) * 70) + 'ms';
      }
    });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) { io.observe(el); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
