var invoizData_1f5ab3a12b = JSON.parse(document.currentScript.dataset.config);

          (function(){
            const msgs = document.getElementById('msgs');
            const form = document.getElementById('chatForm');
            const input = document.getElementById('msgInput');
            const sellerId = invoizData_1f5ab3a12b[0];
            let lastId = invoizData_1f5ab3a12b[1];
            if(msgs) msgs.scrollTop = msgs.scrollHeight;
            function appendMsg(body, isMe, time, roleLabel){
              const div = document.createElement('div');
              div.className = 'msg-bubble ' + (isMe ? 'msg-own' : 'msg-other');
              if(!isMe && roleLabel){ const tag=document.createElement('span'); tag.textContent=roleLabel; tag.className='msg-role msg-sender-role'; div.appendChild(tag); }
              const text=document.createElement('div'); text.textContent=body; div.appendChild(text);
              const meta=document.createElement('div'); meta.textContent=(isMe ? 'You: ' : '')+time; meta.className='msg-time'; div.appendChild(meta);
              msgs.appendChild(div);
              msgs.scrollTop = msgs.scrollHeight;
            }
            if(form) form.addEventListener('submit', function(e){
              e.preventDefault();
              const body = input.value.trim();
              if(!body) return;
              const btn = document.getElementById('sendBtn');
              btn.disabled = true;
              fetch('/chat/send/' + sellerId, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('#chatForm input[name=_token]').value,'Accept':'application/json'},
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
            setInterval(function(){
              if(!msgs) return;
              const url = '/chat/fetch/' + sellerId + '?after_id=' + lastId;
              fetch(url, {headers:{'Accept':'application/json'}})
                .then(r => r.json())
                .then(list => {
                  list.forEach(m => {
                    appendMsg(m.body, m.is_me, new Date(m.created_at).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}), m.sender_label);
                    if(Number(m.id) > lastId) lastId = Number(m.id);
                  });
                });
            }, 3000);
          })();
