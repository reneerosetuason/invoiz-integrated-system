document.addEventListener('DOMContentLoaded', () => {
    const closeRecordModal = () => {
        document.querySelector('.logistics-record-modal-backdrop')?.remove();
    };

    const openRecordModal = (payload) => {
        closeRecordModal();
        const title = payload.title || 'Record Details';
        const kicker = payload.kicker || 'Record Overview';
        const items = Array.isArray(payload.items) ? payload.items : [];
        const url = payload.url || '';
        const urlLabel = payload.urlLabel || 'Open Full Page';

        const backdrop = document.createElement('div');
        backdrop.className = 'logistics-record-modal-backdrop';
        backdrop.innerHTML = `
            <div class="logistics-record-modal" role="dialog" aria-modal="true" aria-label="${title.replace(/"/g, '&quot;')}">
                <div class="logistics-record-modal-head">
                    <div>
                        <p class="logistics-record-modal-kicker"></p>
                        <h3 class="logistics-record-modal-title"></h3>
                    </div>
                    <button type="button" class="logistics-record-modal-close" aria-label="Close details">&times;</button>
                </div>
                <dl class="logistics-record-modal-grid"></dl>
                <div class="logistics-record-modal-foot">
                    ${url ? `<a class="btn btn-outline btn-sm" href="${url}"></a>` : ''}
                    <button type="button" class="btn btn-primary btn-sm logistics-record-modal-done">Close</button>
                </div>
            </div>
        `;

        backdrop.querySelector('.logistics-record-modal-kicker').textContent = kicker;
        backdrop.querySelector('.logistics-record-modal-title').textContent = title;
        if (url) {
            backdrop.querySelector('.logistics-record-modal-foot a').textContent = urlLabel;
        }

        const list = backdrop.querySelector('.logistics-record-modal-grid');
        items.forEach(({ label, value, wide }) => {
            const item = document.createElement('div');
            item.className = 'logistics-record-modal-item' + (wide ? ' logistics-record-modal-item--wide' : '');
            const dt = document.createElement('dt');
            dt.textContent = label || 'Field';
            const dd = document.createElement('dd');
            dd.textContent = (value !== null && value !== undefined && String(value).trim() !== '') ? String(value) : '—';
            item.append(dt, dd);
            list.appendChild(item);
        });

        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) closeRecordModal();
        });
        backdrop.querySelector('.logistics-record-modal-close')?.addEventListener('click', closeRecordModal);
        backdrop.querySelector('.logistics-record-modal-done')?.addEventListener('click', closeRecordModal);
        document.body.appendChild(backdrop);
        backdrop.querySelector('.logistics-record-modal-close')?.focus();
    };

    window.openLogisticsRecordModal = openRecordModal;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-view-record]');
        if (!trigger) return;
        event.preventDefault();
        try {
            const payload = JSON.parse(trigger.getAttribute('data-view-record') || '{}');
            openRecordModal(payload);
        } catch (err) {
            console.error('Invalid data-view-record payload', err);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeRecordModal();
    });
});
