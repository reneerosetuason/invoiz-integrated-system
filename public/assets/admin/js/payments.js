(function(){
  const pills = document.querySelectorAll('.tx-filter');
  const rows = document.querySelectorAll('#txBody tr[data-status]');
  const emptyRow = document.getElementById('txEmpty');
  const title = document.getElementById('transactions');
  const urlParams = new URLSearchParams(window.location.search);
  let current = urlParams.get('status') || '';
  function applyFilter(status, push){
    pills.forEach(p=>{
      const f = p.getAttribute('data-filter');
      const active = f === status;
      p.classList.toggle('is-active', active);
      p.setAttribute('aria-current', active ? 'page' : 'false');
    });
    if(title){
      const labels = {'':'All Transactions','successful':'Successful Transactions','pending':'Pending Transactions','failed':'Failed Transactions','refunded':'Refunded Transactions','partially_refunded':'Partial Transactions'};
      title.textContent = labels[status] || 'All Transactions';
    }
    let visible = 0;
    rows.forEach(r=>{
      const show = !status || r.getAttribute('data-status') === status;
      r.style.display = show ? '' : 'none';
      if(show) visible++;
    });
    if(emptyRow) emptyRow.style.display = visible===0 && rows.length>0 ? '' : 'none';
    const url = status ? '/admin/payments?status='+status : '/admin/payments';
    if(push) history.replaceState({}, '', url);
  }
  if(current) applyFilter(current, false);
  pills.forEach(p=>{
    p.addEventListener('click', function(e){
      e.preventDefault();
      const status = p.getAttribute('data-filter');
      const y = window.scrollY;
      applyFilter(status, true);
      window.scrollTo(0, y);
    });
  });
})();
