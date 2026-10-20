/* Shared sidebar behavior for the seller and buyer sidebars.
   - Entrance stagger (.sb-enter) + pill cleanup.
   - aria-current="page" mirrors the server-rendered .active link.
   - Badge caps: 99+ expanded, 9+ in the collapsed rail (from data-count).
   - Collapsed-rail tooltips via a fixed portal (150ms delay, role="tooltip").
   - Buyer shell: rail toggle on desktop, off-canvas drawer + scrim +
     focus trap + Esc on mobile, collapsed state in localStorage.
   No-JS safe: nav links, badges (server-rendered expanded text) and the
   logout form all work without this file. */
(function () {
    'use strict';

    var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var DESKTOP = window.matchMedia('(min-width: 1024px)');

    function isRail(aside) {
        return aside.classList.contains('sidebar-collapsed') &&
            (DESKTOP.matches || (aside.classList.contains('sb2--admin') && aside.classList.contains('collapsed')));
    }

    /* Badge text from data-count; leaves the .sr-only suffix untouched. */
    function renderBadges(aside) {
        var rail = isRail(aside);
        aside.querySelectorAll('.sb-badge[data-count]').forEach(function (badge) {
            var num = badge.querySelector('.sb-num');
            if (!num) return;
            var n = parseInt(badge.getAttribute('data-count'), 10);
            if (isNaN(n)) return;
            num.textContent = rail ? (n > 9 ? '9+' : String(n)) : (n > 99 ? '99+' : String(n));
        });
    }

    function initAside(aside) {
        if (!REDUCED) {
            requestAnimationFrame(function () { aside.classList.add('sb-enter'); });
            var pill = aside.querySelector('.sb-pill');
            if (pill) {
                pill.addEventListener('animationend', function () { pill.classList.add('sb-pill-in'); }, { once: true });
            }
        }
        aside.querySelectorAll('.sidebar-nav a.active').forEach(function (a) {
            if (!a.hasAttribute('aria-current')) a.setAttribute('aria-current', 'page');
        });
        renderBadges(aside);
    }

    /* ---- Collapsed-rail tooltip portal (one per page) ---- */
    var tip = document.createElement('div');
    tip.id = 'sb-tip';
    tip.className = 'sb-tip';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);
    var tipTimer = null, tipCurrent = null, savedTitle = null;

    function tipHide() {
        if (tipTimer) { clearTimeout(tipTimer); tipTimer = null; }
        tip.classList.remove('show');
        if (tipCurrent) {
            tipCurrent.removeAttribute('aria-describedby');
            if (savedTitle !== null) { tipCurrent.setAttribute('title', savedTitle); savedTitle = null; }
            tipCurrent = null;
        }
    }
    function tipShow(el) {
        var aside = el.closest('aside.sb2');
        if (!aside || !isRail(aside)) return;
        var text = el.getAttribute('data-tip') || el.getAttribute('title');
        if (!text) return;
        tipCurrent = el;
        if (el.hasAttribute('title')) { savedTitle = el.getAttribute('title'); el.removeAttribute('title'); }
        el.setAttribute('aria-describedby', 'sb-tip');
        tip.textContent = text;
        var r = el.getBoundingClientRect();
        tip.style.left = (r.right + 12) + 'px';
        tip.style.top = (r.top + r.height / 2) + 'px';
        tip.style.transform = 'translateY(-50%)';
        tip.classList.add('show');
    }
    function bindTips(scope) {
        scope.querySelectorAll('[data-tip], .sidebar-nav a[title]').forEach(function (el) {
            el.addEventListener('mouseenter', function () { tipHide(); tipTimer = setTimeout(function () { tipShow(el); }, 150); });
            el.addEventListener('mouseleave', tipHide);
            el.addEventListener('focus', function () { tipHide(); tipTimer = setTimeout(function () { tipShow(el); }, 150); });
            el.addEventListener('blur', tipHide);
        });
    }

    /* ---- Buyer shell: toggle + drawer ---- */
    var BUYER_KEY = 'buyerSidebarCollapsed';

    function buyerAside() { return document.querySelector('aside.sb2--buyer'); }
    function buyerScrim() { return document.getElementById('buyerScrim'); }

    function closeBuyerDrawer() {
        var aside = buyerAside();
        if (!aside) return;
        aside.classList.remove('drawer-open');
        document.body.classList.remove('sb-drawer-open');
        var scrim = buyerScrim();
        if (scrim) scrim.hidden = true;
    }
    function toggleBuyer() {
        var aside = buyerAside();
        if (!aside) return;
        var btn = document.getElementById('buyerMenuBtn');
        if (DESKTOP.matches) {
            var collapsed = aside.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem(BUYER_KEY, collapsed ? '1' : '0'); } catch (e) {}
            if (btn) btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            renderBadges(aside);
            tipHide();
        } else {
            var open = aside.classList.toggle('drawer-open');
            document.body.classList.toggle('sb-drawer-open', open);
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            var scrim = buyerScrim();
            if (scrim) scrim.hidden = !open;
            if (open) {
                var first = aside.querySelector('.sidebar-nav a[href], .sb-foot a[href], .sb-foot button');
                if (first) first.focus();
            }
        }
    }

    function initBuyer() {
        var aside = buyerAside();
        if (!aside) return;
        var btn = document.getElementById('buyerMenuBtn');
        if (btn) btn.addEventListener('click', toggleBuyer);
        var scrim = buyerScrim();
        if (scrim) scrim.addEventListener('click', closeBuyerDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeBuyerDrawer(); tipHide(); return; }
            // Focus trap while the mobile drawer is open.
            if (e.key === 'Tab' && aside.classList.contains('drawer-open') && !DESKTOP.matches) {
                var items = Array.prototype.filter.call(
                    aside.querySelectorAll('a[href], button:not([disabled])'),
                    function (el) { return el.getClientRects().length > 0; }
                );
                if (!items.length) return;
                var first = items[0], last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });
        window.addEventListener('scroll', tipHide, true);
        window.addEventListener('resize', function () { tipHide(); renderBadges(aside); });
        // Restore collapsed rail on desktop only; mobile always starts closed.
        try {
            if (DESKTOP.matches && localStorage.getItem(BUYER_KEY) === '1') {
                aside.classList.add('sidebar-collapsed');
                renderBadges(aside);
            }
        } catch (e) {}
        var menuBtn = document.getElementById('buyerMenuBtn');
        if (menuBtn) menuBtn.setAttribute('aria-expanded', DESKTOP.matches && !aside.classList.contains('sidebar-collapsed') ? 'true' : 'false');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('aside.sb2').forEach(function (aside) {
            initAside(aside);
            bindTips(aside);
        });
        initBuyer();
    });
})();
