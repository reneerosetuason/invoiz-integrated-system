(() => {
  'use strict';
  const tables = new Map();
  const originalSpans = new WeakMap();
  let fitFrame;
  let printing = false;
  const queueFit = () => {
    cancelAnimationFrame(fitFrame);
    fitFrame = requestAnimationFrame(() => {
      if (printing) return;
      document.querySelectorAll('main table').forEach(enhanceTable);
      tables.forEach((state, table) => table.isConnected ? fitTable(table, state) : tables.delete(table));
    });
  };
  const tableSizeObserver = new ResizeObserver(queueFit);

  // Keep the original cells, event handlers and actions. This helper only
  // removes lower-priority information from the visible layout when it overflows.
  function enhanceTable(table) {
    if (tables.has(table) || table.closest('.payment-overview,.po-dialog') || !table.tHead || !table.tBodies.length) return;
    const header = table.tHead.rows[table.tHead.rows.length - 1];
    if (!header || [...header.cells].some(cell => cell.colSpan > 1 || cell.rowSpan > 1)) return;
    const labels = [...header.cells].map(cell => cell.textContent.replace(/[\u25b2\u25bc]/g, '').trim());
    tables.set(table, {header, labels});
    table.classList.add('fit-data-table');
    let parent = table.parentElement;
    while (parent && parent.tagName !== 'MAIN' && parent !== document.body) {
      if (/auto|scroll|hidden/.test(getComputedStyle(parent).overflowX)) parent.classList.add('fit-table-container');
      parent = parent.parentElement;
    }
    tableSizeObserver.observe(table.parentElement);
  }

  function fitTable(table, {header, labels}) {
    if (!table.getClientRects().length) return;
    const available = table.parentElement.clientWidth;
    if (!available) return;
    table.classList.remove('fit-table-tight');
    table.querySelectorAll('[data-fit-hidden]').forEach(cell => cell.removeAttribute('data-fit-hidden'));
    const count = labels.length;
    const rows = [...table.tBodies].flatMap(body => [...body.rows]);
    const normalRows = rows.filter(row => row.cells.length === count && [...row.cells].every(cell => cell.colSpan === 1));
    const spanningRows = [...rows.filter(row => !normalRows.includes(row)), ...(table.tFoot ? [...table.tFoot.rows] : [])];
    spanningRows.forEach(row => [...row.cells].forEach(cell => {
      if (!originalSpans.has(cell)) originalSpans.set(cell, cell.colSpan);
      cell.colSpan = originalSpans.get(cell);
    }));
    const controls = new Set(labels.map((label, index) => /^(actions?|manage|options?|select)$/i.test(label) ? index : -1).filter(index => index >= 0));
    normalRows.forEach(row => [...row.cells].forEach((cell, index) => {
      if (cell.querySelector('button,input,select,textarea,form,[role=button],a.btn,[data-view-record],a[data-image-viewer]')
        || [...cell.querySelectorAll('a')].some(link => /^(view(?:\s+.*)?|details|edit|download(?:\s+.*)?|manage(?:\s+.*)?|receipt|proof|track(?:\s+.*)?|print(?:\s+.*)?)$/i.test((link.title || link.textContent).trim()))) controls.add(index);
    }));
    const plain = labels.map((_, index) => index).filter(index => !controls.has(index));
    const first = labels.findIndex((label, index) => !/^(actions?|manage|options?|select)$/i.test(label) && !header.cells[index].querySelector('input[type=checkbox]'));
    const metric = plain.find(index => index !== first && /price|amount|total|balance/i.test(labels[index]))
      ?? plain.find(index => index !== first && /status|stock/i.test(labels[index]));
    const hidden = new Set();
    const updateSpans = () => spanningRows.forEach(row => {
      let column = 0;
      [...row.cells].forEach(cell => {
        const span = originalSpans.get(cell);
        const visible = Array.from({length: span}, (_, offset) => column + offset).filter(index => !hidden.has(index)).length;
        cell.toggleAttribute('data-fit-hidden', visible === 0);
        cell.colSpan = Math.max(1, visible);
        column += span;
      });
    });
    const priority = index => {
      const label = labels[index];
      if (/cost|margin|variants?|rating|sku|category/i.test(label)) return 0;
      if (/date|joined|created|updated|phone|email|address|method|mode|reference|ref\s*#/i.test(label)) return 1;
      if (/status|stock|balance/i.test(label)) return 3;
      if (/amount|price|total/i.test(label)) return 4;
      return 2;
    };
    const candidates = plain.filter(index => index !== first && index !== metric).sort((a, b) => priority(a) - priority(b) || b - a);
    const overflows = () => Math.max(table.getBoundingClientRect().width, table.scrollWidth) > available + 1;
    for (const index of candidates) {
      if (!overflows()) break;
      hidden.add(index);
      header.cells[index].setAttribute('data-fit-hidden', '');
      normalRows.forEach(row => row.cells[index].setAttribute('data-fit-hidden', ''));
      updateSpans();
    }
    if (overflows()) table.classList.add('fit-table-tight');
    table.dataset.fitReady = 'true';
  }

  function watchTables() {
    queueFit();
    window.addEventListener('resize', queueFit);
    document.fonts?.ready.then(queueFit);
    window.addEventListener('beforeprint', () => {
      printing = true;
      cancelAnimationFrame(fitFrame);
      tables.forEach((state, table) => {
        table.classList.remove('fit-table-tight');
        table.querySelectorAll('[data-fit-hidden]').forEach(cell => cell.removeAttribute('data-fit-hidden'));
        table.querySelectorAll('td,th').forEach(cell => {
          if (originalSpans.has(cell)) cell.colSpan = originalSpans.get(cell);
        });
      });
    });
    window.addEventListener('afterprint', () => { printing = false; queueFit(); });
    document.querySelectorAll('main').forEach(main => {
      new MutationObserver(changes => {
        if (changes.some(change => [...change.addedNodes, ...change.removedNodes].some(node => node.nodeType === 1 && (node.matches('table,thead,tbody,tfoot,tr,th,td') || node.querySelector('table'))))) queueFit();
      }).observe(main, {childList: true, subtree: true});
    });
  }
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
  function init(){watchTables();initAdminDrawer();}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
