// Presentation only: preserve routes, form fields, Alpine actions and permissions.
(() => {
    const init = () => {
        const root = document.querySelector('.logistics-shell main');
        if (!root) return;
        root.querySelectorAll('.lw-get-form > span').forEach((span) => {
            if (span.textContent.trim().toLowerCase() === 'to'
                && span.parentElement.querySelectorAll('input[type=date]').length === 2) {
                span.classList.add('ops-date-separator');
            }
        });
        const wrappers = new Set();
        const updateOverflow = (wrap) => {
            const overflow = wrap.scrollWidth > wrap.clientWidth + 2;
            wrap.style.setProperty('--ops-table-viewport-width', `${wrap.clientWidth}px`);
            if (wrap._opsHint) wrap._opsHint.hidden = !overflow;
            if (overflow) wrap.setAttribute('tabindex', '0');
            else wrap.removeAttribute('tabindex');
        };
        const resize = window.ResizeObserver ? new ResizeObserver((entries) => {
            entries.forEach(({ target }) => {
                const wrap = target.closest('.ops-table-scroll');
                if (wrap) updateOverflow(wrap);
            });
        }) : null;
        const prepare = () => root.querySelectorAll('table').forEach((table) => {
            if (!table.tHead || table.closest('#shipping-label, .shipping-label, [data-print-only]')) return;
            table.classList.add('ops-table');
            for (const body of table.tBodies) {
                if (body.rows.length !== 1 || body.rows[0].cells.length !== 1) continue;
                const cell = body.rows[0].cells[0];
                if (cell.colSpan <= 1 || cell.querySelector(':scope > .ops-empty-state')) continue;
                const empty = document.createElement('div');
                empty.className = 'ops-empty-state';
                empty.append(...cell.childNodes);
                cell.append(empty);
            }
            const headers = Array.from(table.tHead.rows[0]?.cells || []);
            headers.forEach((header) => { if (!header.hasAttribute('scope')) header.scope = 'col'; });
            const actions = headers.findIndex((header) => /^(actions?|manage|options)$/i.test(header.textContent.trim()));
            if (actions >= 0) {
                headers[actions].classList.add('ops-action-heading');
                for (const body of table.tBodies) for (const row of body.rows) {
                    if (Array.from(row.cells).some((cell) => cell.colSpan > 1)) continue;
                    row.cells[actions]?.classList.add('ops-action-cell');
                }
            }
            if (table.dataset.opsPrepared) return;
            table.dataset.opsPrepared = 'true';
            let wrap = table.parentElement;
            if (!wrap.matches('.overflow-x-auto, .lw-table-wrap, .ld-table-wrap, .delivery-table-wrap, .overflow-auto')) {
                wrap = document.createElement('div');
                table.before(wrap);
                wrap.append(table);
            }
            wrap.classList.add('ops-table-scroll');
            wrap.setAttribute('role', 'region');
            wrap.setAttribute('aria-label', table.caption?.textContent.trim() || wrap.getAttribute('aria-label') || 'Records table');
            const hint = document.createElement('p');
            hint.className = 'ops-scroll-hint';
            hint.textContent = 'Swipe or scroll to see all columns.';
            hint.hidden = true;
            wrap.before(hint);
            wrap._opsHint = hint;
            wrappers.add(wrap);
            resize?.observe(wrap);
            resize?.observe(table);
            // A table can grow while its viewport stays the same size.
            requestAnimationFrame(() => updateOverflow(wrap));
        });
        prepare();
        let scheduled = false;
        new MutationObserver((records) => {
            if (scheduled || !records.some((record) => record.addedNodes.length)) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                prepare();
                wrappers.forEach((wrap) => {
                    if (!wrap.isConnected) { resize?.unobserve(wrap); wrappers.delete(wrap); }
                    else updateOverflow(wrap);
                });
            });
        }).observe(root, { childList: true, subtree: true });
        window.addEventListener('resize', () => wrappers.forEach(updateOverflow), { passive: true });
        document.fonts?.ready.then(() => wrappers.forEach(updateOverflow));
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
