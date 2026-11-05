(() => {
  'use strict';
  const mobile = matchMedia('(max-width: 1023.98px)');
  let recordDialog;
  function showRecord(row, labels) {
    if (!recordDialog) {
      recordDialog = document.createElement('dialog');
      recordDialog.className = 'mobile-record-dialog';
      recordDialog.setAttribute('aria-labelledby', 'mobile-record-title');
      recordDialog.innerHTML = '<header><h2 id="mobile-record-title">Record details</h2><button type="button" aria-label="Close details">&times;</button></header><dl></dl><footer><button type="button">Close</button></footer>';
      document.body.append(recordDialog);
      recordDialog.querySelectorAll('button').forEach(button => button.addEventListener('click', () => recordDialog.close()));
      recordDialog.addEventListener('click', event => {
        const rect = recordDialog.getBoundingClientRect();
        if (event.target === recordDialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) recordDialog.close();
      });
    }
    const list = recordDialog.querySelector('dl');
    list.replaceChildren();
    [...row.cells].forEach((cell, index) => {
      if (cell.classList.contains('mobile-record-action')) return;
      const text = cell.innerText.trim();
      if (!text && !cell.querySelector('img')) return;
      const item = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
      label.textContent = labels[index] || 'Details';
      value.textContent = text || cell.querySelector('img')?.alt || 'Image';
      // Preserve useful original links. Stateful controls stay in their original row.
      if (!cell.querySelector('button,input,select,textarea,form')) {
        const links = cell.querySelectorAll('a[href]');
        if (links.length) {
          const nav = document.createElement('div');
          nav.className = 'mobile-record-links';
          links.forEach(link => {
            const anchor = document.createElement('a');
            anchor.href = link.href; anchor.textContent = link.textContent.trim() || 'Open';
            if (link.target === '_blank') { anchor.target = '_blank'; anchor.rel = 'noopener'; }
            nav.append(anchor);
          });
          value.append(nav);
        }
      }
      item.append(label, value); list.append(item);
    });
    recordDialog.showModal();
  }
  function enhanceTable(table) {
    if (table.closest('.payment-overview,.po-dialog') || table.dataset.mobileReady || !table.tHead || !table.tBodies.length) return;
    const header = table.tHead.rows[table.tHead.rows.length - 1];
    if (!header || [...header.cells].some(cell => cell.colSpan > 1 || cell.rowSpan > 1)) return;
    if (header.cells.length < 4) { table.classList.add('mobile-basic-table'); return; }
    const originalCount = header.cells.length;
    const rows = [...table.tBodies].flatMap(body => [...body.rows]);
    const normalRows = rows.filter(row => row.cells.length === originalCount && [...row.cells].every(cell => cell.colSpan === 1));
    const labels = [...header.cells].map(cell => cell.textContent.replace(/[\u25b2\u25bc]/g, '').trim());
    const controls = new Set(labels.map((label,index) => /^(actions?|manage|options?)$/i.test(label) ? index : -1).filter(index => index >= 0));
    normalRows.forEach(row => [...row.cells].forEach((cell, index) => {
      if (cell.querySelector('button,input,select,textarea,form,[role=button]') || /^(actions?|manage|options?)$/i.test(labels[index])) controls.add(index);
    }));
    if (controls.size > 2) return;
    const plain = labels.map((_, index) => index).filter(index => !controls.has(index));
    const first = plain[0];
    const second = plain.find(index => index !== first && /amount|total|status|balance/i.test(labels[index])) ?? plain[1];
    const visible = new Set([first, second, ...controls]);
    table.dataset.mobileReady = '1';table.dataset.mobileHasControls = String(controls.size > 0);table.classList.add('mobile-summary-table');
    [...header.cells].forEach((cell,index) => {
      cell.dataset.mobileField = visible.has(index) ? 'summary' : 'detail';
      if (controls.has(index)) cell.dataset.mobileControls = 'true';
      if (index === second) {
        cell.dataset.mobileMetric = 'true';
        if (/status/i.test(labels[index])) cell.classList.add('mobile-status-cell');
      }
    });
    const actionHeader = document.createElement('th');
    actionHeader.className = 'mobile-record-action';actionHeader.scope = 'col';actionHeader.textContent = 'View';header.append(actionHeader);
    rows.forEach(row => {
      if (!normalRows.includes(row)) { if(row.cells.length===1&&row.cells[0].colSpan===originalCount)row.cells[0].colSpan++;return; }
      [...row.cells].forEach((cell,index) => {
        cell.dataset.mobileField = visible.has(index) ? 'summary' : 'detail';
        if (controls.has(index)) cell.dataset.mobileControls = 'true';
        if (index === second) {
          cell.dataset.mobileMetric = 'true';
          if (/status/i.test(labels[index])) cell.classList.add('mobile-status-cell');
        }
        if (index === first) cell.dataset.mobilePrimary = 'true';
      });
      const cell = document.createElement('td'), button = document.createElement('button');
      cell.className = 'mobile-record-action';button.type = 'button';button.className = 'mobile-record-view';button.textContent = 'View';button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-label','View details for '+(row.cells[first]?.innerText.trim() || 'this record')); 
      button.addEventListener('click', () => showRecord(row, labels));cell.append(button);row.append(cell);
    });
    if (table.tFoot) [...table.tFoot.rows].forEach(row => {
      let column = 0;
      [...row.cells].forEach(cell => {
        if (cell.colSpan === 1) cell.dataset.mobileFooterLabel = labels[column] || '';
        column += cell.colSpan;
      });
    });
    // Remove the horizontal-only wrapper on phones while keeping desktop CSS intact.
    let parent=table.parentElement;
    while(parent && parent.tagName!=='MAIN' && parent!==document.body){
      if (/auto|scroll|hidden/.test(getComputedStyle(parent).overflowX))parent.classList.add('mobile-table-container');
      parent=parent.parentElement;
    }
  }
  function scan() { document.querySelectorAll('main table').forEach(enhanceTable); }
  function initAdminDrawer() {
    const aside=document.querySelector('aside.sb2--admin'), toggle=document.querySelector('.hamburger');
    if (!aside || !toggle) return;
    const media=matchMedia('(max-width:1023.98px)');
    const scrim=document.createElement('button');scrim.type='button';scrim.className='mobile-admin-scrim';scrim.setAttribute('aria-label','Close navigation');scrim.hidden=true;document.body.append(scrim);
    const sync=()=>{const open=media.matches&&!aside.classList.contains('collapsed');scrim.hidden=!open;document.body.classList.toggle('mobile-admin-open',open);};
    const close=()=>{if(media.matches&&!aside.classList.contains('collapsed')){toggle.click();toggle.focus();}};
    scrim.addEventListener('click',close);
    document.addEventListener('keydown',event=>{
      if(!media.matches||aside.classList.contains('collapsed'))return;
      if(event.key==='Escape')close();
      if(event.key==='Tab'){
        const items=[...aside.querySelectorAll('a[href],button')].filter(el=>el.getClientRects().length&&getComputedStyle(el).visibility!=='hidden');
        if(!items.length)return;
        const first=items[0],last=items[items.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
      }
    });
    toggle.addEventListener('click',()=>{sync();if(media.matches&&!aside.classList.contains('collapsed'))aside.querySelector('.sidebar-nav a')?.focus();});
    new MutationObserver(sync).observe(aside,{attributes:true,attributeFilter:['class']});media.addEventListener('change',sync);sync();
  }
  function init(){scan();initAdminDrawer();mobile.addEventListener('change',scan);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
