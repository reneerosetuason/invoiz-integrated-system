(() => {
  const dialogs = document.querySelectorAll('.po-dialog');
  const syncScroll = () => document.body.classList.toggle('po-dialog-open', [...dialogs].some(dialog => dialog.open));
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-payment-dialog]');
    if (trigger) {
      const dialog = document.getElementById(trigger.dataset.paymentDialog);
      if (!dialog || dialog.open) return;
      dialog.showModal();
      syncScroll();
    }
    if (event.target.closest('[data-payment-close]')) event.target.closest('.po-dialog')?.close();
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
