/* Invoiz - Main JavaScript */

/* Profile dropdown close on outside click */
document.addEventListener('click', function(e) {
  var d = document.getElementById('profileDrop');
  if (d && !e.target.closest('.profile-btn')) {
    d.classList.remove('open');
  }
});

/* Sidebar toggle (state persists across pages via localStorage) */
function toggleSidebar() {
  var sb = document.getElementById('sidebar');
  if (!sb) return;
  sb.classList.toggle('collapsed');
  try { localStorage.setItem('invoiz_sidebar', sb.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
}
(function restoreSidebar() {
  try {
    if (localStorage.getItem('invoiz_sidebar') === '1') {
      var sb = document.getElementById('sidebar');
      if (sb) sb.classList.add('collapsed');
    }
  } catch (e) {}
})();

/* Cart checkbox functions */
function toggleSeller(sellerId, checked) {
  document.querySelectorAll('.item-check[data-seller="' + sellerId + '"]').forEach(function(cb) {
    cb.checked = checked;
  });
  if (typeof updateTotal === 'function') updateTotal();
}

function updateTotal() {
  var total = 0;
  var count = 0;
  document.querySelectorAll('.item-check:checked').forEach(function(cb) {
    var price = parseFloat(cb.getAttribute('data-price'));
    var qty = parseInt(cb.getAttribute('data-qty'));
    if (!isNaN(price) && !isNaN(qty)) {
      total += price * qty;
      count += qty;
    }
  });
  var totalEl = document.getElementById('selectedTotal');
  var countEl = document.getElementById('selectedCount');
  if (totalEl) totalEl.textContent = total.toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  if (countEl) countEl.textContent = count;
}

function buySelected() {
  var ids = [];
  document.querySelectorAll('.item-check:checked').forEach(function(cb) {
    ids.push(cb.value);
  });
  if (ids.length === 0) {
    alert('Select at least one item');
    return;
  }
  window.location.href = '/buy/selected?ids=' + ids.join(',');
}
