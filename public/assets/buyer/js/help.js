function filterFaqs(q) {
  q = (q || '').toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('.faq-item').forEach(function (el) {
    const hit = !q || el.getAttribute('data-text').indexOf(q) !== -1;
    el.style.display = hit ? '' : 'none';
    if (hit) visible++;
  });
  document.querySelectorAll('.faq-group').forEach(function (g) {
    const anyVisible = Array.from(g.querySelectorAll('.faq-item')).some(function (el) { return el.style.display !== 'none'; });
    g.style.display = anyVisible ? '' : 'none';
  });
  document.getElementById('faqEmpty').style.display = visible ? 'none' : '';
}
