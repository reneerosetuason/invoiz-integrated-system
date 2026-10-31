(function(){
  var configScript = document.getElementById('product-config-script') || document.currentScript;
  var config = window.invoizProductConfig || (configScript && configScript.dataset.config ? JSON.parse(configScript.dataset.config) : null);
  if (!config) return;

  var base = Number(config[0]);
  var baseMax = Math.min(200, Math.max(0, Number(config[1]) || 0));
  var hasVariants = Boolean(config[2]);
  var buyUrl = config[3];
  var cartUrl = config[4];
  var csrfToken = config[5];

  var maxQty = baseMax, qty = 1, variantId = '', variantAdj = 0;
  var submitting = false, opener = null, previousOverflow = '';
  var modal = document.getElementById('optModal');

  var peso = function(n){ return '₱' + n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); };

  function updateOpt(){
    var optQtyVal = document.getElementById('optQtyVal');
    if (optQtyVal) optQtyVal.textContent = qty;
    var pageQtyVal = document.getElementById('pageQtyVal');
    if (pageQtyVal) pageQtyVal.textContent = qty;

    var currentPrice = peso(base + variantAdj);
    var optBasePrice = document.getElementById('optBasePrice');
    if (optBasePrice) optBasePrice.textContent = currentPrice;
    var bigPrice = document.querySelector('.pbuy .bignum');
    if (bigPrice) bigPrice.textContent = currentPrice;

    var optTotal = document.getElementById('optTotal');
    if (optTotal) optTotal.textContent = peso((base + variantAdj) * qty);

    var availText = maxQty + ' available' + (variantId ? ' for this variation' : '');
    var optAvail = document.getElementById('optAvail');
    if (optAvail) optAvail.textContent = availText;
    var pageAvail = document.getElementById('pageAvail');
    if (pageAvail) pageAvail.textContent = availText;

    var optError = document.getElementById('optError');
    if (optError) optError.hidden = maxQty > 0;

    document.querySelectorAll('.opt-confirm, .opt-btn-cart, .opt-btn-buy').forEach(function(button){
      button.disabled = submitting || maxQty < 1;
    });
  }

  window.pickVariant = function(btn){
    if (btn.disabled) return;
    var vId = btn.getAttribute('data-variant');
    variantId = vId;
    variantAdj = Number(btn.getAttribute('data-adj')) || 0;
    maxQty = Math.min(baseMax, Math.max(0, Number(btn.getAttribute('data-stock')) || 0));
    qty = maxQty > 0 ? Math.min(Math.max(1, qty), maxQty) : 0;

    document.querySelectorAll('.opt-pill').forEach(function(b){
      var match = b.getAttribute('data-variant') === vId;
      b.style.borderColor = match ? 'var(--green)' : 'var(--border)';
      b.style.background = match ? '#f0fdf4' : '#fff';
      b.style.color = match ? 'var(--green)' : 'var(--text)';
      b.setAttribute('aria-pressed', match ? 'true' : 'false');
    });

    updateOpt();
  };

  window.optQty = function(d){
    qty = maxQty > 0 ? Math.min(Math.max(1, qty + d), maxQty) : 0;
    updateOpt();
  };

  window.openOpt = function(isBuy){
    opener = document.activeElement;
    submitting = false;
    updateOpt();
    if (modal) {
      previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      modal.style.display = 'flex';
      var closeBtn = modal.querySelector('button[aria-label="Close options"]');
      if (closeBtn) closeBtn.focus();
    }
  };

  window.closeOpt = function(){
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = previousOverflow;
    if (opener && opener.isConnected) opener.focus({preventScroll:true});
  };

  window.confirmOpt = function(mode){
    if (submitting || maxQty < 1 || qty < 1) return;
    if (hasVariants && !variantId) {
      var first = document.querySelector('.opt-pill:not(:disabled)');
      if (first) {
        window.pickVariant(first);
      } else {
        alert('Please select a variation.');
        return;
      }
    }

    submitting = true;
    updateOpt();

    if (mode === 'buy') {
      window.location.href = buyUrl + '?qty=' + qty + (variantId ? '&variant_id=' + variantId : '');
      return;
    }

    var currentToken = (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '') || csrfToken;

    if (window.fetch) {
      var formData = new FormData();
      formData.append('_token', currentToken);
      formData.append('qty', qty);
      formData.append('variant_id', variantId || '');

      fetch(cartUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': currentToken
        },
        body: formData,
        credentials: 'same-origin'
      }).then(function(res){
        if (res.status === 419) {
          // Token expired: silently reload page to get a fresh session and token
          window.location.reload();
          return null;
        }
        if (res.status === 401) {
          alert('Please login to add items to your cart.');
          window.location.href = '/login';
          return null;
        }
        return res.json().then(function(data){
          if (!res.ok) throw new Error(data.message || 'Could not add to cart.');
          return data;
        });
      }).then(function(data){
        submitting = false;
        if (!data) return;
        if (modal) window.closeOpt();
        
        // Update any cart badges on the page
        if (data.cart_count !== undefined) {
          document.querySelectorAll('.cart-badge, .badge-cart, [data-cart-badge]').forEach(function(badge){
            badge.textContent = data.cart_count > 99 ? '99+' : data.cart_count;
            badge.style.display = data.cart_count > 0 ? '' : 'none';
          });
        }
        
        // Give button success feedback
        var cartBtn = document.querySelector('.opt-btn-cart');
        if (cartBtn) {
          var prev = cartBtn.innerHTML;
          cartBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Added!';
          setTimeout(function(){ cartBtn.innerHTML = prev; updateOpt(); }, 2000);
        }
        alert('Added to cart!');
      }).catch(function(err){
        submitting = false;
        updateOpt();
        alert(err.message || 'Could not add to cart.');
      });
      return;
    }

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = cartUrl;
    var fields = {_token: currentToken, qty: qty, variant_id: variantId || ''};
    Object.keys(fields).forEach(function(name){
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  };

  if (modal) {
    modal.addEventListener('click', function(e){ if (e.target === modal) window.closeOpt(); });
    modal.addEventListener('keydown', function(e){
      if (e.key === 'Escape') { e.preventDefault(); window.closeOpt(); return; }
      if (e.key !== 'Tab') return;
      var targets = Array.from(modal.querySelectorAll('button:not(:disabled)'));
      var first = targets[0], last = targets[targets.length-1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  window.addEventListener('pageshow', function(){ submitting = false; updateOpt(); });

  // Auto-select first available variant on page load
  var firstPill = document.querySelector('.opt-pill:not(:disabled)');
  if (firstPill) {
    window.pickVariant(firstPill);
  } else {
    updateOpt();
  }
})();
