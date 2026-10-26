// Keep the existing Alpine action state; add keyboard and focus support to its dialogs.
(() => {
    const init = () => {
        document.querySelectorAll('.delivery-filter-details').forEach(details => {
            const mobile = matchMedia('(max-width: 639.98px)');
            const arrange = () => { details.open = !mobile.matches || details.dataset.active === 'true'; };
            arrange();
            mobile.addEventListener('change', arrange);
        });
        document.querySelectorAll('.delivery-modal').forEach((modal, index) => {
            const panel = modal.querySelector('form');
            const heading = panel?.querySelector('h3, h4');
            if (!panel || !heading) return;
            heading.id ||= `delivery-dialog-title-${index}`;
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', heading.id);
            panel.tabIndex = -1;
            const close = () => modal.querySelector(':scope > [class*="absolute"]')?.click();
            const visible = () => getComputedStyle(modal).display !== 'none';
            let wasOpen = false;
            let opener = null;
            const sync = () => {
                const open = visible();
                if (open === wasOpen) return;
                wasOpen = open;
                if (open) {
                    opener = document.activeElement;
                    const target = panel.querySelector('button[type="button"], input:not([type="hidden"]), select, textarea') || panel;
                    target.focus({preventScroll: true});
                } else if (opener?.isConnected) {
                    opener.focus({preventScroll: true});
                }
                document.body.classList.toggle('delivery-dialog-open', [...document.querySelectorAll('.delivery-modal')].some(el => getComputedStyle(el).display !== 'none'));
            };
            new MutationObserver(sync).observe(modal, {attributes: true, attributeFilter: ['style']});
            modal.addEventListener('keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); close(); }
                if (event.key !== 'Tab') return;
                const targets = [...panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])')].filter(el => el.getClientRects().length);
                if (!targets.length) { event.preventDefault(); panel.focus(); return; }
                const first = targets[0], last = targets.at(-1);
                if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            });
            sync();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
    else init();
})();
