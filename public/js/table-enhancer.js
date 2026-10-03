(function () {
  var STYLE_ID = 'tbl-enhancer-style';
  if (!document.getElementById(STYLE_ID)) {
    var st = document.createElement('style');
    st.id = STYLE_ID;
    st.textContent =
      'th.sortable-th{cursor:pointer;user-select:none;white-space:nowrap}' +
      'th.sortable-th:hover{color:inherit;filter:brightness(.82)}' +
      'th.sortable-th .sort-arrow{font-size:9px;margin-left:4px;opacity:.45;display:inline-block;min-width:8px}' +
      'th.sortable-th.sorted-asc .sort-arrow,th.sortable-th.sorted-desc .sort-arrow{opacity:1;font-weight:700}' +
      '.tbl-pager{display:flex;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap}' +
      '.tbl-pager button{border:1px solid #d8d6d0;background:#fff;border-radius:8px;padding:6px 14px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;color:inherit}' +
      '.tbl-pager button:hover:not(:disabled){background:#f0eee9}' +
      '.tbl-pager button:disabled{opacity:.35;cursor:default}' +
      '.tbl-pager .tbl-info{font-size:12.5px;color:#6e6e73}';
    document.head.appendChild(st);
  }

  var PAGE_SIZE = 10;

  function cellKey(text) {
    var t = (text || '').trim();
    if (!t) return { s: '', n: null };
    var numStr = t.replace(/[₱$,\s%]/g, '');
    if (/^-?\d+(\.\d+)?$/.test(numStr)) return { s: t.toLowerCase(), n: parseFloat(numStr) };
    var parsed = Date.parse(t);
    if (!isNaN(parsed) && /\d/.test(t)) return { s: t.toLowerCase(), n: parsed };
    return { s: t.toLowerCase(), n: null };
  }

  function enhance(table) {
    if (table.dataset.tblEnhanced) return;
    if (table.getAttribute('data-enhance') === 'off') return;
    var thead = table.querySelector('thead');
    var tbody = table.tBodies[0];
    if (!thead || !tbody || !thead.rows.length) return;

    var headerRow = thead.rows[0];
    var headers = Array.prototype.slice.call(headerRow.cells);
    var emptyRows = [];
    var items = [];
    Array.prototype.forEach.call(tbody.rows, function (tr, i) {
      if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) {
        emptyRows.push(tr);
      } else {
        items.push({
          i: i,
          tr: tr,
          k: Array.prototype.map.call(tr.cells, function (td) {
            return cellKey(td.textContent);
          })
        });
      }
    });

    if (!items.length || !headers.length) return;

    table.dataset.tblEnhanced = '1';
    emptyRows.forEach(function (tr) { tbody.removeChild(tr); });

    var state = { col: -1, dir: 1, page: 1 };
    var pager = null;

    headers.forEach(function (th, idx) {
      th.classList.add('sortable-th');
      th.title = 'Click to sort';
      var arrow = document.createElement('span');
      arrow.className = 'sort-arrow';
      th.appendChild(arrow);
      th.addEventListener('click', function () {
        if (state.col === idx) {
          state.dir = -state.dir;
        } else {
          state.col = idx;
          state.dir = 1;
        }
        headers.forEach(function (h) {
          h.classList.remove('sorted-asc', 'sorted-desc');
          h.querySelector('.sort-arrow').textContent = '';
        });
        th.classList.add(state.dir === 1 ? 'sorted-asc' : 'sorted-desc');
        arrow.textContent = state.dir === 1 ? '\u25B2' : '\u25BC';
        state.page = 1;
        render();
      });
    });

    function totalPagesFor(len) {
      return Math.max(1, Math.ceil(len / PAGE_SIZE));
    }

    function ensurePager(total) {
      if (!pager) {
        pager = document.createElement('div');
        pager.className = 'tbl-pager';
        pager.innerHTML =
          '<button type="button" data-p="prev">\u2039 Prev</button>' +
          '<span class="tbl-info"></span>' +
          '<button type="button" data-p="next">Next \u203A</button>';
        if (table.nextSibling) {
          table.parentNode.insertBefore(pager, table.nextSibling);
        } else {
          table.parentNode.appendChild(pager);
        }
        pager.addEventListener('click', function (e) {
          var b = e.target.closest('button');
          if (!b) return;
          if (b.dataset.p === 'prev') state.page = Math.max(1, state.page - 1);
          if (b.dataset.p === 'next') state.page = Math.min(totalPagesFor(items.length), state.page + 1);
          render();
        });
      }
      pager.style.display = total > PAGE_SIZE ? 'flex' : 'none';
    }

    function render() {
      var sorted;
      if (state.col >= 0) {
        sorted = items.slice().sort(function (a, b) {
          var x = a.k[state.col] || { s: '', n: null };
          var y = b.k[state.col] || { s: '', n: null };
          var cmp;
          if (x.n !== null && y.n !== null) cmp = x.n - y.n;
          else if (x.n !== null) cmp = -1;
          else if (y.n !== null) cmp = 1;
          else cmp = x.s.localeCompare(y.s);
          return cmp * state.dir;
        });
      } else {
        sorted = items.slice().sort(function (a, b) { return a.i - b.i; });
      }

      var total = sorted.length;
      var pages = totalPagesFor(total);
      if (state.page > pages) state.page = pages;
      ensurePager(total);

      var start = (state.page - 1) * PAGE_SIZE;
      var slice = sorted.slice(start, start + PAGE_SIZE);

      Array.prototype.slice.call(tbody.rows).forEach(function (tr) { tbody.removeChild(tr); });
      slice.forEach(function (it) { tbody.appendChild(it.tr); });

      if (pager) {
        var from = total === 0 ? 0 : start + 1;
        var to = Math.min(start + PAGE_SIZE, total);
        pager.querySelector('.tbl-info').textContent =
          'Showing ' + from + '\u2013' + to + ' of ' + total + '  \u00B7  Page ' + state.page + ' of ' + pages;
        pager.querySelector('[data-p="prev"]').disabled = state.page <= 1;
        pager.querySelector('[data-p="next"]').disabled = state.page >= pages;
      }
    }

    render();
  }

  function init() {
    document.querySelectorAll('table').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
