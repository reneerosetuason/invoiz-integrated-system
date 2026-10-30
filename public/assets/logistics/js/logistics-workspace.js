document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('.logistics-workspace');

    const confirmAction = (message) => new Promise((resolve) => {
        const backdrop = document.createElement('div');
        backdrop.className = 'lw-confirm-backdrop';
        backdrop.innerHTML = `
            <div class="lw-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="lw-confirm-title">
                <p id="lw-confirm-title" class="lw-confirm-kicker">Confirm Action</p>
                <p class="lw-confirm-message"></p>
                <div class="lw-confirm-actions">
                    <button type="button" class="lw-confirm-cancel">Cancel</button>
                    <button type="button" class="lw-confirm-accept">Confirm</button>
                </div>
            </div>
        `;
        backdrop.querySelector('.lw-confirm-message').textContent = message;
        const close = (result) => {
            backdrop.remove();
            resolve(result);
        };
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) close(false);
        });
        backdrop.querySelector('.lw-confirm-cancel').addEventListener('click', () => close(false));
        backdrop.querySelector('.lw-confirm-accept').addEventListener('click', () => close(true));
        document.addEventListener('keydown', function onKey(event) {
            if (event.key === 'Escape') {
                document.removeEventListener('keydown', onKey);
                close(false);
            }
        });
        document.body.appendChild(backdrop);
        backdrop.querySelector('.lw-confirm-accept').focus();
    });

    window.logisticsConfirm = confirmAction;

    if (!workspace) return;

    workspace.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }
            event.preventDefault();
            const accepted = await confirmAction(form.dataset.confirmMessage || 'Proceed with this action?');
            if (accepted) {
                form.dataset.confirmed = 'true';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });
});
