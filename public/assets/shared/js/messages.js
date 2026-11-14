(() => {
  const root = document.querySelector('[data-message-center]');
  if (!root) return;
  const find = name => root.querySelector(`[data-${name}]`);
  const token = root.querySelector('[name=_token]').value;
  let active = null, page = 1, lastPage = 1, busy = false, timer;
  const error = message => { find('chat-error').textContent = message; find('chat-error').hidden = !message; };
  async function request(path, body) {
    const response = await fetch(`${root.dataset.base}/${path}`, {
      method: body ? 'POST' : 'GET', credentials: 'same-origin',
      headers: {Accept: 'application/json', ...(body instanceof FormData ? {} : {'Content-Type': 'application/json'}), 'X-CSRF-TOKEN': token},
      ...(body ? {body: body instanceof FormData ? body : JSON.stringify(body)} : {})
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Unable to load messages. Please try again.');
    return data;
  }
  const element = (tag, text) => { const el = document.createElement(tag); el.textContent = text; return el; };
  async function inbox() {
    const data = await request('inbox');
    const list = find('thread-list'); list.replaceChildren();
    if (!data.conversations.length) list.append(element('p', 'No messages yet. Choose New message to contact someone.'));
    data.conversations.forEach(thread => {
      const button = element('button', '');
      button.type = 'button'; button.setAttribute('aria-current', String(active?.thread_key === thread.thread_key));
      const avatar = element('span', [...thread.name.trim()][0]?.toLocaleUpperCase() || 'I');
      avatar.className = 'mc-contact-avatar'; avatar.setAttribute('aria-hidden', 'true');
      const copy = element('span', ''); copy.className = 'mc-contact-copy';
      copy.append(element('strong', thread.name));
      const preview = element('span', thread.preview || 'Start a conversation'); preview.className = 'mc-contact-preview';
      copy.append(preview); button.append(avatar, copy);
      if (thread.unread) { const badge = element('span', String(thread.unread)); badge.className = 'mc-unread'; badge.setAttribute('aria-label', `${thread.unread} unread`); button.append(badge); }
      button.addEventListener('click', () => open(thread).catch(e => error(e.message)));
      list.append(button);
    });
    if (active) {
      const current = data.conversations.find(t => t.thread_key === active.thread_key);
      if (current) { active = current; find('chat-form').hidden = !current.can_send; find('chat-readonly').hidden = current.can_send; find('chat-readonly').textContent = current.readonly_reason || 'This contact is no longer available for messaging. Your history is still here.'; }
    }
    return data.conversations;
  }
  function render(messages, prepend = false) {
    const history = find('chat-history');
    const fragment = document.createDocumentFragment();
    [...messages].reverse().forEach(message => {
      const bubble = element('div', message.deleted ? 'Message deleted' : message.body);
      bubble.className = 'mc-bubble'; bubble.dataset.mine = String(message.mine);
      if (!message.mine && message.sender_type === 'logistics' && ['buyer', 'seller'].includes(active?.type)) {
        const sender = element('strong', `${message.sender?.first_name || 'Logistics support'}\n`);
        bubble.prepend(sender);
      }
      if (message.product_card) bubble.prepend(element('strong', `${message.product_card.name}\n`));
      (message.attachments || []).forEach(file => {
        const link = element('a', file.name); link.href = `${root.dataset.base}/attachments/${file.id}`;
        bubble.append(link);
      });
      bubble.append(element('small', `${message.dayLabel || ''} ${message.time || ''}${message.mine && message.is_read ? ' · Read' : ''}`));
      fragment.append(bubble);
    });
    if (prepend) history.prepend(fragment); else history.replaceChildren(fragment);
  }
  async function load(older = false) {
    if (!active || busy || document.hidden) return;
    const key = active.thread_key;
    const history = find('chat-history');
    const nearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 100;
    const data = await request(`threads/${encodeURIComponent(key)}?page=${older ? page + 1 : 1}`);
    if (active?.thread_key !== key) return;
    if (typeof data.can_send === 'boolean') {
      active.can_send = data.can_send;
      find('chat-form').hidden = !data.can_send; find('chat-readonly').hidden = data.can_send;
    }
    render(data.messages, older); page = data.pagination.current_page; lastPage = data.pagination.last_page;
    find('older').hidden = page >= lastPage;
    if (!older && nearBottom) history.scrollTop = history.scrollHeight;
  }
  async function open(thread) {
    active = thread; page = 1; error('');
    find('chat-title').textContent = thread.name;
    find('chat-avatar').textContent = [...thread.name.trim()][0]?.toLocaleUpperCase() || 'I';
    find('chat-context').textContent = thread.subject || 'Direct message';
    find('chat-form').hidden = !thread.can_send; find('chat-readonly').hidden = thread.can_send;
    find('attachment-label').hidden = !thread.thread_key.startsWith('logistics:');
    find('contacts-panel').hidden = true;
    await load(); await inbox();
  }
  async function contacts() {
    const data = await request(`contacts?search=${encodeURIComponent(find('contact-search').value)}`);
    const list = find('contact-list'); list.replaceChildren();
    if (!data.contacts.length) list.append(element('p', 'No eligible contacts found.'));
    data.contacts.forEach(contact => {
      const button = element('button', `${contact.name} · ${contact.role}`); button.type = 'button';
      button.addEventListener('click', async () => {
        button.disabled = true;
        try { const result = await request('start', {recipient: contact.key}); const threads = await inbox(); await open(threads.find(t => t.thread_key === result.thread_key)); }
        catch (e) { error(e.message); } finally { button.disabled = false; }
      }); list.append(button);
    });
  }
  find('new-message').addEventListener('click', () => { find('contacts-panel').hidden = false; contacts().catch(e => error(e.message)); });
  find('close-contacts').addEventListener('click', () => { find('contacts-panel').hidden = true; });
  find('contact-search').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => contacts().catch(e => error(e.message)), 250); });
  find('older').addEventListener('click', () => load(true).catch(e => error(e.message)));
  find('chat-form').addEventListener('submit', async event => {
    event.preventDefault(); if (!active || busy) return;
    const form = event.currentTarget, body = form.elements.body.value.trim(); if (!body) return;
    busy = true; form.querySelector('button').disabled = true;
    const upload = find('chat-attachment').files[0];
    const payload = upload && active.thread_key.startsWith('logistics:') ? new FormData() : {body};
    if (payload instanceof FormData) { payload.append('body', body); payload.append('attachment', upload); }
    try { await request(`threads/${encodeURIComponent(active.thread_key)}`, payload); form.elements.body.value = ''; find('chat-attachment').value = ''; error(''); }
    catch (e) { error(e.message); }
    finally { busy = false; form.querySelector('button').disabled = false; }
    await load().catch(e => error(e.message)); await inbox().catch(e => error(e.message));
  });
  async function parcelContacts() {
    const data = await request('contacts');
    const panel = find('parcel-contacts'), list = find('parcel-contact-list'); list.replaceChildren();
    const parcels = data.contacts.filter(contact => contact.key.startsWith('parcel:'));
    panel.hidden = parcels.length === 0;
    parcels.forEach(contact => {
      const button = element('button', `${contact.action_label} · ${contact.name} · ${contact.tracking}`); button.type = 'button';
      button.addEventListener('click', async () => {
        try { const result = await request('start', {recipient: contact.key}); const threads = await inbox(); await open(threads.find(t => t.thread_key === result.thread_key)); }
        catch (e) { error(e.message); }
      }); list.append(button);
    });
  }
  (async () => {
    await inbox(); await parcelContacts();
    const parcel = new URLSearchParams(window.location.search).get('parcel');
    if (/^[1-9][0-9]*$/.test(parcel || '')) {
      const result = await request('start', {recipient: `parcel:${parcel}`});
      const threads = await inbox(); await open(threads.find(t => t.thread_key === result.thread_key));
    }
  })().catch(e => error(e.message));
  // Preserve loaded history while reading earlier messages.
  setInterval(() => { if (!document.hidden && !busy) { inbox().catch(() => {}); if (page === 1) load().catch(() => {}); parcelContacts().catch(() => {}); } }, 6000);
})();
