(() => {
  const dialogs = document.querySelectorAll('.po-dialog');
  const syncScroll = () => document.body.classList.toggle('po-dialog-open', [...dialogs].some(dialog => dialog.open));
  const accountRequests = new WeakMap();
  const loadPayoutAccount = async section => {
    if (!section) return;
    accountRequests.get(section)?.abort();
    const controller = new AbortController();
    accountRequests.set(section, controller);
    const content = section.querySelector('[data-payout-account-content]');
    content.textContent = 'Loading the recipient\'s saved details…';
    try {
      const response = await fetch(section.dataset.payoutAccountUrl, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}, signal:controller.signal});
      if (!response.ok) throw new Error('Account lookup failed');
      const {account} = await response.json();
      if (controller.signal.aborted) return;
      content.replaceChildren();
      if (!account?.details) {
        const message = document.createElement('p');
        message.textContent = 'No payout account saved yet. The recipient can add it in their regular Remittances page.';
        content.append(message);
        return;
      }
      const list = document.createElement('dl');
      list.className = 'po-detail-list';
      for (const [label, value] of [['Account for',account.label],['Bank / e-wallet',account.details.provider],['Account holder',account.details.holder],['Account number',account.details.account],['Updated',account.updated_at ? new Date(account.updated_at).toLocaleString() : 'Not recorded']]) {
        const row = document.createElement('div'), term = document.createElement('dt'), detail = document.createElement('dd');
        term.textContent = label; detail.textContent = value;
        row.append(term, detail); list.append(row);
      }
      content.append(list);
    } catch (error) {
      if (error.name !== 'AbortError') content.textContent = 'Could not load current bank details. Use Refresh bank details to retry.';
    }
  };
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-payment-dialog]');
    if (trigger) {
      const dialog = document.getElementById(trigger.dataset.paymentDialog);
      if (!dialog || dialog.open) return;
      dialog.showModal();
      syncScroll();
      loadPayoutAccount(dialog.querySelector('[data-payout-account-url]'));
    }
    if (event.target.closest('[data-payment-close]')) event.target.closest('.po-dialog')?.close();
    if (event.target.closest('[data-refresh-payout-account]')) loadPayoutAccount(event.target.closest('[data-payout-account-url]'));
  });
  dialogs.forEach(dialog => {
    dialog.addEventListener('close', syncScroll);
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const bounds = dialog.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
  });
  document.addEventListener('submit', event => {
    const form = event.target.closest('[data-payout-confirm]');
    if (form && !window.confirm(form.dataset.payoutConfirm)) event.preventDefault();
  });
})();
