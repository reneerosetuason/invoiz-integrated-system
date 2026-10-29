(() => {
    const records = document.querySelector('[data-return-records]');
    const button = document.querySelector('[data-return-refresh]');
    const status = document.querySelector('[data-return-refresh-status]');
    if (!records || !button || !status) return;
    let pending = false;
    let submitting = false;

    const editing = () => records.contains(document.activeElement)
        || Array.from(records.querySelectorAll('input[name="note"]')).some(input => input.value.trim() !== '');

    async function refresh(manual = false) {
        if (pending || submitting || document.hidden) return;
        if (editing()) {
            if (manual) status.textContent = 'Finish or clear your note before refreshing.';
            return;
        }
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(manual ? button.href : window.location.href, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Refresh failed');
            }
            const data = await response.json();
            if (typeof data.html !== 'string' || typeof data.version !== 'string' || !data.counts) {
                throw new Error('Invalid refresh response');
            }
            // Do not replace a form that the seller started editing while the
            // read-only request was in flight.
            if (submitting || editing()) return;
            if (data.version !== records.dataset.version) {
                records.innerHTML = data.html;
                records.dataset.version = data.version;
            }
            document.querySelectorAll('[data-return-count]').forEach(counter => {
                const count = data.counts[counter.dataset.returnCount];
                if (Number.isInteger(count) && count >= 0) counter.textContent = String(count);
            });
            if (manual && window.location.href !== button.href) history.replaceState(null, '', button.href);
            status.textContent = 'Requests are up to date.';
        } catch (_) {
            status.textContent = 'Could not refresh requests. Your current list is kept. Try Refresh requests.';
        } finally {
            clearTimeout(timeout);
            pending = false;
        }
    }

    button.addEventListener('click', event => {
        event.preventDefault();
        refresh(true);
    });
    records.addEventListener('submit', () => { submitting = true; });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    setInterval(() => refresh(), 15000);
})();
