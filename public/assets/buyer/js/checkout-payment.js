(() => {
  const form = document.getElementById('checkout-form');
  if (!form) return;
  const fields = document.getElementById('gcash-proof-fields');
  const stores = document.getElementById('gcash-store-payments');
  const template = document.getElementById('gcash-account-template');
  const message = document.getElementById('gcash-quote-message');
  const retry = document.getElementById('gcash-quote-retry');
  const submit = form.querySelector('button[type="submit"]');
  const address = document.getElementById('checkout-address');
  const blockedByAddress = submit.disabled;
  const currency = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(Number(value));
  let currentQuote = JSON.parse(document.getElementById('checkout-initial-quote').textContent);
  let sequence = 0;
  let quoteReady = true;
  let busy = false;
  let controller;
  const previews = new Map();
  const brokenQrs = new Set();
  const loadingQrs = new Set();
  const gcash = () => form.querySelector('[name="payment_method"]:checked')?.value === 'gcash';
  const releasePreviews = () => {
    previews.forEach(url => URL.revokeObjectURL(url));
    previews.clear();
  };
  const refreshButton = () => {
    submit.disabled = blockedByAddress || busy || !quoteReady || (gcash() && (
      !currentQuote.accounts.length || currentQuote.accounts.some(a => !a.available) || brokenQrs.size > 0 || loadingQrs.size > 0
    ));
  };
  const syncMethod = () => {
    fields.hidden = !gcash();
    document.getElementById('checkout-cod-note').hidden = gcash();
    document.getElementById('checkout-payment-note').textContent = gcash()
      ? 'Pay the stores through GCash. Your rider collects no cash.'
      : 'Pay on delivery. Your rider collects this amount.';
    stores.querySelectorAll('[data-seller-id]').forEach(card => {
      const account = currentQuote.accounts.find(a => Number(a.seller_id) === Number(card.dataset.sellerId));
      const input = card.querySelector('input[type="file"]');
      input.disabled = !gcash() || !account?.available || !quoteReady;
      input.required = !input.disabled;
      if (!gcash()) {
        input.value = '';
        input.setCustomValidity('');
        card.querySelector('[data-receipt-preview]').hidden = true;
        card.querySelector('[data-receipt-status]').textContent = '';
        card.querySelector('[data-receipt-error]').textContent = '';
      }
    });
    if (!gcash()) releasePreviews();
    refreshButton();
  };
  const bindCard = (card, account) => {
    const image = card.querySelector('[data-receiving-qr]');
    if (account.qr_url) {
      if (!image.complete || !image.naturalWidth) loadingQrs.add(account.seller_id);
      image.addEventListener('error', () => {
        if (!card.isConnected) return;
        loadingQrs.delete(account.seller_id);
        brokenQrs.add(account.seller_id);
        card.querySelector('[data-qr-error]').hidden = false;
        retry.hidden = false;
        refreshButton();
      });
      image.addEventListener('load', () => {
        if (!card.isConnected) return;
        loadingQrs.delete(account.seller_id);
        brokenQrs.delete(account.seller_id);
        card.querySelector('[data-qr-error]').hidden = true;
        refreshButton();
      });
      if (image.complete && image.currentSrc && !image.naturalWidth) {
        brokenQrs.add(account.seller_id);
        card.querySelector('[data-qr-error]').hidden = false;
        retry.hidden = false;
      }
    }
    const input = card.querySelector('input[type="file"]');
    const preview = card.querySelector('[data-receipt-preview]');
    const error = card.querySelector('[data-receipt-error]');
    const status = card.querySelector('[data-receipt-status]');
    input.addEventListener('change', () => {
      if (previews.has(account.seller_id)) URL.revokeObjectURL(previews.get(account.seller_id));
      previews.delete(account.seller_id);
      preview.hidden = true;
      input.setCustomValidity('');
      error.textContent = '';
      status.textContent = '';
      const file = input.files[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        error.textContent = 'Choose a JPG, PNG or WebP image no larger than 5 MB.';
        input.setCustomValidity(error.textContent);
        return;
      }
      const url = URL.createObjectURL(file);
      previews.set(account.seller_id, url);
      preview.src = url;
      preview.hidden = false;
      status.textContent = `Receipt attached: ${file.name}`;
    });
  };
  const render = data => {
    releasePreviews();
    brokenQrs.clear();
    loadingQrs.clear();
    currentQuote = data;
    stores.replaceChildren();
    data.accounts.forEach(account => {
      const card = template.content.firstElementChild.cloneNode(true);
      card.dataset.sellerId = account.seller_id;
      card.querySelector('[data-store-name]').textContent = account.store_name;
      card.querySelector('[data-store-amount]').textContent = currency(account.amount);
      card.querySelector('[data-account-name]').textContent = account.account_name || 'Not configured';
      const notice = card.querySelector('[data-setup-notice]');
      notice.hidden = account.available;
      notice.textContent = 'This store has not added a GCash receiving QR yet. Choose cash on delivery or contact the store.';
      card.querySelector('[data-qr-column]').hidden = !account.qr_url;
      if (account.qr_url) {
        card.querySelector('[data-qr-link]').href = account.qr_url;
        card.querySelector('[data-qr-open]').href = account.qr_url;
        const image = card.querySelector('[data-receiving-qr]');
        image.alt = `${account.store_name} GCash receiving QR`;
        // Attach listeners before assigning src, including cached image responses.
        bindCard(card, account);
        image.src = account.qr_url;
      } else bindCard(card, account);
      card.querySelector('[data-upload-fields]').hidden = !account.available;
      card.querySelector('input[type="file"]').name = `gcash_proofs[${account.seller_id}]`;
      stores.append(card);
    });
    [['subtotal', data.subtotal], ['shipping', data.shipping_fee], ['total', data.total]].forEach(([name, value]) => {
      const node = form.querySelector(`[data-checkout-${name}]`);
      if (value !== undefined && node) node.textContent = currency(value);
    });
    if (data.discount !== undefined) {
      form.querySelector('[data-checkout-discount-row]').hidden = Number(data.discount) <= 0;
      form.querySelector('[data-checkout-discount]').textContent = `−${currency(data.discount)}`;
    }
    syncMethod();
  };
  const quote = async () => {
    const requestId = ++sequence;
    controller?.abort();
    controller = new AbortController();
    const requestController = controller;
    const timeout = setTimeout(() => requestController.abort(), 15000);
    quoteReady = false;
    stores.querySelectorAll('input[type="file"]').forEach(input => { input.disabled = true; });
    refreshButton();
    message.textContent = 'Checking current payment details...';
    retry.hidden = true;
    try {
      const body = new FormData(form);
      for (const key of [...body.keys()]) if (key.startsWith('gcash_proof')) body.delete(key);
      const response = await fetch(form.dataset.quoteUrl, {method: 'POST', body, headers: {'Accept': 'application/json'}, signal: requestController.signal});
      const data = await response.json().catch(() => null);
      if (requestId !== sequence) return;
      if (!response.ok || !Array.isArray(data?.accounts)) {
        throw Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'Payment details could not load. Reload them before placing your order.');
      }
      quoteReady = true;
      render(data);
      message.textContent = !gcash() ? 'Your order total is up to date.' : data.accounts.every(a => a.available)
        ? 'Amounts include delivery and your selected vouchers.'
        : 'GCash ordering is unavailable for one or more stores. You can choose cash on delivery.';
    } catch (error) {
      if (requestId !== sequence) return;
      message.textContent = error.name === 'AbortError'
        ? 'Payment details took too long to load. Please try again.' : error.message;
      retry.hidden = false;
    } finally {
      clearTimeout(timeout);
      if (requestId === sequence) refreshButton();
    }
  };
  const showAddress = () => {
    const option = address.selectedOptions[0];
    const preview = document.getElementById('checkout-address-preview');
    preview.hidden = !option?.value;
    ['recipient', 'address', 'phone'].forEach(key => {
      preview.querySelector(`[data-${key}]`).textContent = option?.dataset[key] || '';
    });
  };
  stores.querySelectorAll('[data-seller-id]').forEach(card => {
    const account = currentQuote.accounts.find(a => Number(a.seller_id) === Number(card.dataset.sellerId));
    if (account) bindCard(card, account);
  });
  form.addEventListener('change', event => {
    if (event.target.name === 'payment_method') {
      syncMethod();
      if (gcash() || !quoteReady) quote();
    } else if (event.target.name === 'voucher_claim_ids[]') {
      message.textContent = 'Voucher amounts changed. Select receipts again after checking the new total.';
      quote();
    } else if (event.target.name === 'address_id') showAddress();
  });
  retry.addEventListener('click', quote);
  form.addEventListener('submit', event => {
    if (!quoteReady || busy || (gcash() && (brokenQrs.size || loadingQrs.size || currentQuote.accounts.some(a => !a.available)))) {
      event.preventDefault();
      return;
    }
    busy = true;
    submit.textContent = 'Placing your order...';
    refreshButton();
  });
  window.addEventListener('pagehide', releasePreviews);
  window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
  form.querySelector('[name="payment_method"][value="gcash"]').disabled = false;
  syncMethod();
  showAddress();
})();
