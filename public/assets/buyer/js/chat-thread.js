var invoizData_972b0bedf5 = JSON.parse(document.currentScript.dataset.config);

(function(){
  const msgs = document.getElementById('msgs');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('msgInput');
  const sellerId = invoizData_972b0bedf5[0];
  let lastId = invoizData_972b0bedf5[1];

  msgs.scrollTop = msgs.scrollHeight;

  function appendMsg(body, isMe, time, roleLabel){
    const div = document.createElement('div');
    if(isMe){
      div.style.cssText = 'align-self:flex-end;background:#134e4a;color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:65%;font-size:13px;line-height:1.4';
    } else {
      div.style.cssText = 'align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:65%;font-size:13px;line-height:1.4';
    }
    if(!isMe && roleLabel){ const tag=document.createElement('span'); tag.textContent=roleLabel; tag.style.cssText='display:inline-block;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;background:#e0f2f1;color:#0E4A57;margin-bottom:4px'; div.appendChild(tag); }
    const text=document.createElement('div'); text.textContent=body; div.appendChild(text);
    const meta=document.createElement('div'); meta.textContent=(isMe ? 'You: ' : '')+time; meta.style.cssText='font-size:9px;opacity:.65;margin-top:3px;text-align:'+(isMe?'right':'left'); div.appendChild(meta);
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const body = input.value.trim();
    if(!body) return;
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    fetch('/chat/send/' + sellerId, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('input[name=_token]').value,'Accept':'application/json'},
      body: JSON.stringify({body: body})
    }).then(r => r.json()).then(d => {
      if(d.ok){
        const now = new Date();
        const t = now.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
        appendMsg(body, true, t);
        lastId = Number(d.id || lastId);
        input.value = '';
      }
    }).finally(()=>{ btn.disabled = false; });
  });

  // Poll for new messages every 3 seconds
  setInterval(function(){
    const url = '/chat/fetch/' + sellerId + '?after_id=' + lastId;
    fetch(url, {headers:{'Accept':'application/json'}})
      .then(r => r.json())
      .then(msgs => {
        msgs.forEach(m => {
          appendMsg(m.body, m.is_me, new Date(m.created_at).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}), m.sender_label);
          if(Number(m.id) > lastId) lastId = Number(m.id);
        });
      });
  }, 3000);
})();
