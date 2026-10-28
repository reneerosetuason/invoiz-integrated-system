var invoizData_6ebe52658f = JSON.parse(document.currentScript.dataset.config);

(function () {
  var thread = document.getElementById('thread');
  var form = document.getElementById('composer');
  var search = document.getElementById('convSearch');
  if (thread) thread.scrollTop = thread.scrollHeight;
  if(search){
    search.addEventListener('input', function(){
      var q = search.value.toLowerCase();
      document.querySelectorAll('#convList .conv').forEach(function(el){
        var name = el.getAttribute('data-name')||'';
        el.style.display = name.includes(q) ? '' : 'none';
      });
    });
  }
  if (!thread || !form) return;
  var token = form.querySelector('input[name="_token"]').value;
  var messagesUrl = form.getAttribute('data-messages-url');
  var myId = invoizData_6ebe52658f[0];
  var otherId = invoizData_6ebe52658f[1];
  var lastId = invoizData_6ebe52658f[2];
  var seen = {};
  var initialIds = invoizData_6ebe52658f[3];
  initialIds.forEach(function (id) { seen[id] = true; });
  var sending = false; var polling = false;
  function fmt(iso){ return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); }
  function bumpConversation(userId, text){
    var list = document.getElementById('convList');
    if(!list || !userId) return;
    var el = list.querySelector('.conv[data-user-id="'+userId+'"]');
    if(!el) return;
    var lastEl = el.querySelector('.conv-last');
    if(lastEl) lastEl.textContent = text.length > 28 ? text.substring(0,28)+'...' : text;
    var timeEl = el.querySelector('.conv-time');
    if(timeEl) timeEl.textContent = 'now';
    list.prepend(el);
  }
  function addBubble(msg, mine){
    if (seen[msg.id]) return; seen[msg.id] = true;
    var empty = document.getElementById('thread-empty');
    if (empty) empty.remove();
    var b = document.createElement('div');
    b.className = 'bubble ' + (mine ? 'me' : 'them');
    var txt = document.createElement('div'); txt.textContent = msg.body;
    var time = document.createElement('span'); time.className = 'bubble-time';
    time.textContent = fmt(msg.created_at);
    if (mine) { time.insertAdjacentHTML('beforeend', ' <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M18 6L7 17l-5-5"/><path d="M22 10l-7.5 7.5L13 19"/></svg>'); }
    b.appendChild(txt); b.appendChild(time);
    thread.appendChild(b);
    lastId = Math.max(lastId, msg.id);
    thread.scrollTop = thread.scrollHeight;
  }
  function poll(){
    if (polling || !messagesUrl) return; polling = true;
    fetch(messagesUrl + '?after=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.messages) d.messages.forEach(function (m) { addBubble(m, m.sender_id === myId); bumpConversation(m.sender_id === myId ? otherId : m.sender_id, m.body); }); })
      .catch(function () {})
      .finally(function () { polling = false; });
  }
  form.addEventListener('submit', function (e){
    e.preventDefault(); if (sending) return;
    var input = form.querySelector('[name=body]');
    var body = input.value.trim(); if (!body) return;
    sending = true; var btn = form.querySelector('.send-btn'); if (btn) btn.disabled = true;
    fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: new FormData(form) })
      .then(function (r) { if (!r.ok) throw new Error('failed'); return r.json(); })
      .then(function (d) { if (d && d.message) { addBubble(d.message, true); bumpConversation(otherId, d.message.body); } input.value = ''; input.focus(); })
      .catch(function () { alert('Message failed to send. Please try again.'); })
      .finally(function () { sending = false; if (btn) btn.disabled = false; });
  });
  var inputEl = form.querySelector('[name=body]');
  if(inputEl){ inputEl.addEventListener('keydown', function(e){ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); form.dispatchEvent(new Event('submit')); } }); }
  setInterval(poll, 3000);
})();
