var invoizData_a4c831fb72 = JSON.parse(document.currentScript.dataset.config);

(function(){
  var base = Number(invoizData_a4c831fb72[0]);
  var baseMax = Math.min(200, Math.max(0, Number(invoizData_a4c831fb72[1]) || 0));
  var hasVariants = invoizData_a4c831fb72[2];
  var maxQty = baseMax, qty = 1, variantId = '', variantAdj = 0;
  var submitting = false, opener = null, previousOverflow = '';
  var modal = document.getElementById('optModal');
  var peso = function(n){ return '₱' + n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  function resetPills(){
    document.querySelectorAll('.opt-pill').forEach(function(b){
      b.style.borderColor = 'var(--border)'; b.style.background = '#fff'; b.style.color = 'var(--text)';
      b.setAttribute('aria-pressed', 'false');
    });
  }
  function updateOpt(){
    document.getElementById('optQtyVal').textContent = qty;
    document.getElementById('optBasePrice').textContent = peso(base + variantAdj);
    document.getElementById('optTotal').textContent = peso((base + variantAdj) * qty);
    document.getElementById('optAvail').textContent = maxQty + ' available' + (variantId ? ' for this variation' : '');
    document.getElementById('optError').hidden = maxQty > 0;
    document.querySelectorAll('.opt-confirm').forEach(function(button){
      button.disabled = submitting || maxQty < 1 || (hasVariants && !variantId);
    });
  }
  window.openOpt = function(){
    opener = document.activeElement;
    qty = baseMax > 0 ? 1 : 0; variantId = ''; variantAdj = 0; maxQty = baseMax; submitting = false;
    resetPills();
    var first = modal.querySelector('.opt-pill:not(:disabled)');
    if(first) window.pickVariant(first);
    else if(hasVariants) { maxQty = 0; qty = 0; }
    updateOpt();
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    modal.style.display = 'flex';
    modal.querySelector('button[aria-label="Close options"]').focus();
  };
  window.closeOpt = function(){
    modal.style.display = 'none';
    document.body.style.overflow = previousOverflow;
    if(opener && opener.isConnected) opener.focus({preventScroll:true});
  };
  window.pickVariant = function(btn){
    if(btn.disabled) return;
    resetPills();
    btn.style.borderColor = 'var(--green)'; btn.style.background = '#f0fdf4'; btn.style.color = 'var(--green)';
    btn.setAttribute('aria-pressed', 'true');
    variantId = btn.getAttribute('data-variant');
    variantAdj = Number(btn.getAttribute('data-adj')) || 0;
    maxQty = Math.min(200, Math.max(0, Number(btn.getAttribute('data-stock')) || 0));
    qty = maxQty > 0 ? Math.min(Math.max(1, qty), maxQty) : 0;
    updateOpt();
  };
  window.optQty = function(d){
    qty = maxQty > 0 ? Math.min(Math.max(1, qty + d), maxQty) : 0;
    updateOpt();
  };
  window.confirmOpt = function(mode){
    if(submitting || maxQty < 1 || qty < 1 || (hasVariants && !variantId)) return;
    submitting = true;
    updateOpt();
    if(mode === 'buy'){
      window.location.href = invoizData_a4c831fb72[3] + '?qty=' + qty + (variantId ? '&variant_id=' + variantId : '');
      return;
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = invoizData_a4c831fb72[4];
    var fields = {_token: invoizData_a4c831fb72[5], qty: qty, variant_id: variantId || ''};
    Object.keys(fields).forEach(function(name){
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = name; input.value = fields[name]; form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  };
  modal.addEventListener('click', function(e){ if(e.target === modal) window.closeOpt(); });
  modal.addEventListener('keydown', function(e){
    if(e.key === 'Escape') { e.preventDefault(); window.closeOpt(); return; }
    if(e.key !== 'Tab') return;
    var targets = Array.from(modal.querySelectorAll('button:not(:disabled)'));
    var first = targets[0], last = targets[targets.length-1];
    if(e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if(!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  window.addEventListener('pageshow', function(){ submitting = false; updateOpt(); });
})();
