@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  $sellerId = $sellerId ?? 2;
  $messages = $messages ?? collect();
@endphp
<a href="{{ url('/messages') }}" style="color:var(--text2);text-decoration:none;font-weight:600;font-size:12px">← Back to messages</a>
<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);margin-top:12px;overflow:hidden">
  <div style="display:flex;gap:10px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--border)">
    @php
      $initial = strtoupper(substr('Seller #'.$sellerId,0,1));
      $colors = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
      $color = $colors[array_sum(array_map('ord', str_split((string)$sellerId))) % count($colors)];
    @endphp
    <div style="width:42px;height:42px;background:{{ $color }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0">{{ $initial }}</div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px">Seller #{{ $sellerId }}</div>
      <div style="font-size:11px;color:var(--text3)">Invoiz Store • typically replies in 5 min</div>
    </div>
    <span style="width:8px;height:8px;background:var(--success);border-radius:50%"></span>
  </div>
  <div id="msgs" style="height:380px;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:8px">
    @forelse($messages as $m)
      @if($m->sender_id == $buyer['id'])
        <div style="align-self:flex-end;background:var(--green);color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:65%;font-size:13px;line-height:1.4">{{ $m->body }}<div style="font-size:9px;opacity:.6;margin-top:3px;text-align:right">{{ $m->created_at->format('h:i A') }}</div></div>
      @else
        <div style="align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:65%;font-size:13px;line-height:1.4">{{ $m->body }}<div style="font-size:9px;color:var(--text3);margin-top:3px">{{ $m->created_at->format('h:i A') }}</div></div>
      @endif
    @empty
      <div style="flex:1;display:grid;place-items:center;text-align:center;color:var(--text3);font-size:12px">
        <div>
          <div style="font-size:28px;margin-bottom:6px">👋</div>
          <div style="font-weight:600">Start a conversation</div>
          <div>Send a message to Seller #{{ $sellerId }}</div>
        </div>
      </div>
    @endforelse
  </div>
  <form id="chatForm" style="display:flex;gap:8px;padding:12px 14px;border-top:1px solid var(--border)">
    @csrf
    <input id="msgInput" type="text" placeholder="Type your message..." style="flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:999px;outline:none;font-size:13px;font-family:inherit" autocomplete="off">
    <button type="submit" id="sendBtn" style="padding:10px 18px;background:var(--green);color:#fff;border:none;border-radius:999px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit">Send</button>
  </form>
</div>
<script>
(function(){
  const msgs = document.getElementById('msgs');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('msgInput');
  const sellerId = {{ $sellerId }};
  let lastTime = '{{ $messages->isNotEmpty() ? $messages->last()->created_at->toDateTimeString() : '' }}';

  msgs.scrollTop = msgs.scrollHeight;

  function appendMsg(body, isMe, time){
    const div = document.createElement('div');
    if(isMe){
      div.style.cssText = 'align-self:flex-end;background:#134e4a;color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:65%;font-size:13px;line-height:1.4';
      div.innerHTML = body + '<div style="font-size:9px;opacity:.6;margin-top:3px;text-align:right">' + time + '</div>';
    } else {
      div.style.cssText = 'align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:65%;font-size:13px;line-height:1.4';
      div.innerHTML = body + '<div style="font-size:9px;color:#999;margin-top:3px">' + time + '</div>';
    }
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
        lastTime = now.toISOString().slice(0,19).replace('T',' ');
        input.value = '';
      }
    }).finally(()=>{ btn.disabled = false; });
  });

  // Poll for new messages every 3 seconds
  setInterval(function(){
    const url = '/chat/fetch/' + sellerId + (lastTime ? '?after=' + encodeURIComponent(lastTime) : '');
    fetch(url, {headers:{'Accept':'application/json'}})
      .then(r => r.json())
      .then(msgs => {
        msgs.forEach(m => {
          appendMsg(m.body, m.is_me, new Date(m.created_at).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}));
          if(m.created_at > lastTime) lastTime = m.created_at;
        });
      });
  }, 3000);
})();
</script>
@endsection
