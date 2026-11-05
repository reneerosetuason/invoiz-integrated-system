/* Native scrolling preserves links, wishlist forms, touch and keyboard browsing. */
(() => {
  const root = document.querySelector('[data-deals-carousel]');
  const track = root?.querySelector('[data-deals-track]');
  if (!track) return;
  const cards = [...track.querySelectorAll('[data-sale-product]')];
  if (cards.length < 2) return;
  const pause = root.querySelector('[data-deals-pause]');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  let paused = reduced.matches, hovered = false, touching = false, focused = false;
  let distance = 0, frame = 0, lastTime = 0, interactionUntil = 0, travel = 0;
  function rebuild() {
    track.querySelectorAll('[data-deals-copy]').forEach(el => el.remove());
    const gap = parseFloat(getComputedStyle(track).columnGap) || 16;
    distance = cards.reduce((sum, card) => sum + card.getBoundingClientRect().width + gap, 0);
    if (!distance) return;
    const copies = Math.ceil(track.clientWidth / distance) + 1;
    for (let group = 0; group < copies; group++) cards.forEach(card => {
      const copy = card.cloneNode(true);
      copy.dataset.dealsCopy = 'true'; copy.removeAttribute('data-sale-product');
      copy.setAttribute('aria-hidden', 'true');
      copy.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(el => el.tabIndex = -1);
      track.append(copy);
    });
    if (track.scrollLeft >= distance) track.scrollLeft %= distance;
  }
  function sync() {
    pause.disabled = reduced.matches;
    pause.textContent = reduced.matches ? 'Motion reduced' : (paused ? 'Play movement' : 'Pause movement');
    pause.setAttribute('aria-pressed', String(paused));
  }
  function animate(now) {
    if (lastTime && !paused && !reduced.matches && !hovered && !touching && !focused && !document.hidden && now > interactionUntil && distance > 0) {
      travel += Math.min(now - lastTime, 50) * 0.035;
      const step = Math.floor(travel); travel -= step;
      if (step) track.scrollLeft = (track.scrollLeft + step) % distance;
    }
    lastTime = now; frame = requestAnimationFrame(animate);
  }
  pause.addEventListener('click', () => { paused = !paused; sync(); });
  root.querySelector('[data-deals-prev]').addEventListener('click', () => move(-1));
  root.querySelector('[data-deals-next]').addEventListener('click', () => move(1));
  function move(direction) {
    interactionUntil = performance.now() + 5000;
    if (direction < 0 && track.scrollLeft < 1) track.scrollLeft = distance;
    track.scrollBy({left: direction * (cards[0].getBoundingClientRect().width + 16), behavior: reduced.matches ? 'instant' : 'smooth'});
  }
  root.addEventListener('mouseenter', () => hovered = true);
  root.addEventListener('mouseleave', () => hovered = false);
  root.addEventListener('focusin', () => focused = true);
  root.addEventListener('focusout', () => requestAnimationFrame(() => focused = root.contains(document.activeElement)));
  track.addEventListener('pointerdown', () => touching = true);
  window.addEventListener('pointerup', () => { touching = false; interactionUntil = performance.now() + 2500; });
  window.addEventListener('pointercancel', () => touching = false);
  track.addEventListener('wheel', () => interactionUntil = performance.now() + 5000, {passive:true});
  reduced.addEventListener('change', () => { if (reduced.matches) paused = true; sync(); });
  new ResizeObserver(rebuild).observe(track);
  window.addEventListener('pagehide', () => { cancelAnimationFrame(frame); lastTime = 0; });
  window.addEventListener('pageshow', () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(animate); });
  rebuild(); sync(); frame = requestAnimationFrame(animate);
})();
