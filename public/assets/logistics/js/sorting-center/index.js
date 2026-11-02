document.querySelectorAll('[data-sort-center]').forEach((centerSelect) => {
            const areaSelect = document.getElementById(centerSelect.dataset.areaTarget);
            if (!areaSelect) return;

            const filterAreas = () => {
                Array.from(areaSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = option.dataset.centerId !== centerSelect.value;
                    if (option.hidden && option.selected) areaSelect.value = '';
                });
            };

            centerSelect.addEventListener('change', filterAreas);
            filterAreas();
        });

/**
 * Sort dropdown placement.
 *
 * The panel is portaled to <body> so table overflow containers cannot clip
 * it, then positioned with fixed coordinates against the Sort button. It
 * flips upward when the button is near the bottom of the viewport, caps its
 * height to the space that actually exists, and follows scrolls/resizes.
 */
(() => {
    const GAP = 6;
    const MARGIN = 8;
    const MIN_USABLE_HEIGHT = 96;
    const Z_INDEX = '2147483000';

    const entries = Array.from(document.querySelectorAll('[data-sort-dropdown]'))
        .map((wrapper) => {
            const trigger = wrapper.querySelector('[data-sort-trigger]');
            const panel = wrapper.querySelector('[data-sort-panel]');
            const chevron = wrapper.querySelector('[data-sort-chevron]');
            if (!trigger || !panel) return null;
            return { trigger, panel, chevron, portaled: false };
        })
        .filter(Boolean);

    if (entries.length === 0) return;

    let openEntry = null;

    const reposition = (entry) => {
        const { trigger, panel } = entry;
        const rect = trigger.getBoundingClientRect();
        const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
        const viewportHeight = window.innerHeight;

        panel.style.maxHeight = 'none';
        panel.style.overflowY = 'visible';

        const naturalWidth = panel.offsetWidth || 256;
        const naturalHeight = panel.offsetHeight;
        const width = Math.min(naturalWidth, viewportWidth - MARGIN * 2);
        panel.style.width = `${width}px`;

        const spaceBelow = viewportHeight - rect.bottom - GAP - MARGIN;
        const spaceAbove = rect.top - GAP - MARGIN;
        const openUp = naturalHeight > spaceBelow && spaceAbove >= spaceBelow;

        let height = Math.min(naturalHeight, Math.max(0, openUp ? spaceAbove : spaceBelow));
        if (height < MIN_USABLE_HEIGHT) {
            // The button sits in a very short viewport: use what the screen
            // can give instead of a sliver of panel.
            height = Math.min(naturalHeight, Math.max(0, viewportHeight - MARGIN * 2));
        }

        panel.style.maxHeight = `${height}px`;
        panel.style.overflowY = naturalHeight > height ? 'auto' : 'visible';
        panel.style.overscrollBehavior = 'contain';

        let top = openUp ? rect.top - GAP - height : rect.bottom + GAP;
        top = Math.min(Math.max(MARGIN, top), viewportHeight - height - MARGIN);

        let left = rect.right - width;
        left = Math.min(Math.max(MARGIN, left), viewportWidth - width - MARGIN);

        panel.style.top = `${Math.round(top)}px`;
        panel.style.left = `${Math.round(left)}px`;
    };

    const closePanel = (entry) => {
        if (!entry) return;
        entry.panel.style.display = 'none';
        entry.trigger.setAttribute('aria-expanded', 'false');
        entry.chevron?.classList.remove('rotate-180');
        if (openEntry === entry) openEntry = null;
    };

    const openPanel = (entry) => {
        if (openEntry && openEntry !== entry) closePanel(openEntry);

        const { panel } = entry;
        if (!entry.portaled) {
            document.body.appendChild(panel);
            entry.portaled = true;
        }

        panel.style.position = 'fixed';
        panel.style.zIndex = Z_INDEX;
        panel.style.display = 'flex';
        panel.style.visibility = 'hidden';
        entry.trigger.setAttribute('aria-expanded', 'true');
        entry.chevron?.classList.add('rotate-180');

        reposition(entry);
        panel.style.visibility = '';

        openEntry = entry;
    };

    entries.forEach((entry) => {
        entry.trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (openEntry === entry) {
                closePanel(entry);
            } else {
                openPanel(entry);
            }
        });

        // Closing on submit keeps the panel out of the next page's way while
        // preserving the normal form POST and server-side validation.
        entry.panel.addEventListener('submit', () => closePanel(entry));
    });

    document.addEventListener('pointerdown', (event) => {
        if (!openEntry) return;
        if (openEntry.panel.contains(event.target) || openEntry.trigger.contains(event.target)) return;
        closePanel(openEntry);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !openEntry) return;
        const entry = openEntry;
        closePanel(entry);
        entry.trigger.focus();
    });

    const trackPosition = () => {
        if (openEntry) reposition(openEntry);
    };

    window.addEventListener('scroll', trackPosition, { capture: true, passive: true });
    window.addEventListener('resize', trackPosition);
})();
