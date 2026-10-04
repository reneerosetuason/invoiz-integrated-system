@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  $selId = $selId ?? 0;
  $selMessages = $selMessages ?? collect();
  $selName = $selName ?? '';
@endphp
<h2 style="font-weight:800">Messages</h2>
<p style="color:var(--text3);font-size:12px;margin-top:2px">Chat with sellers you've ordered from</p>
<div style="display:grid;grid-template-columns:320px 1fr;gap:16px;margin-top:14px;min-height:480px">
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
    <div style="padding:12px 14px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px">Chats</div>
    <div style="padding:10px 12px;border-bottom:1px solid var(--border)">
      <input type="search" id="chatSearch" placeholder="Search chats or people..." style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:12px;outline:none;font-family:inherit" autocomplete="off">
    </div>
    <div id="convList" style="padding:8px;max-height:400px;overflow:auto">
      @forelse($conversations as $conv)
        @php
          $sname = \App\Models\Seller::nameFor($conv['other_id']);
          $initial = strtoupper(substr($sname,0,1));
          $colors = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
          $color = $colors[array_sum(array_map('ord', str_split((string)$conv['other_id']))) % count($colors)];
          $isSel = (int)$selId === (int)$conv['other_id'];
          [$crowLabel, $crowFg, $crowBg] = \App\Models\Message::roleBadge($conv['contact_role'] ?? 'seller');
        @endphp
        <a href="{{ url('/messages?seller='.$conv['other_id']) }}" data-search="{{ strtolower($sname.' '.$conv['last']->body.' '.$crowLabel) }}" style="display:flex;gap:10px;align-items:center;padding:10px;border-radius:10px;text-decoration:none;color:inherit;margin-bottom:4px;transition:all .15s;border:1px solid {{ $isSel ? 'var(--green)' : 'transparent' }};background:{{ $isSel ? '#f0fdf4' : 'transparent' }}" onmouseover="this.style.background='#f8f8f8'" onmouseout="this.style.background='{{ $isSel ? '#f0fdf4' : 'transparent' }}'">
          <div style="width:42px;height:42px;background:{{ $color }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0">{{ $initial }}</div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
              <div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $sname }}</div>
              <div style="font-size:10px;color:var(--text3);flex-shrink:0">{{ $conv['last']->created_at->diffForHumans() }}</div>
            </div>
            <div style="margin-top:3px"><span style="display:inline-block;font-size:9px;font-weight:800;padding:1px 8px;border-radius:999px;background:{{ $crowBg }};color:{{ $crowFg }}">{{ $crowLabel }}</span></div>
            <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px">{{ $conv['last_mine'] ? 'You: ' : $conv['last_label'].': ' }}{{ $conv['last']->body }}</div>
          </div>
          @if($conv['unread'] > 0)
            <span style="background:var(--danger);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px;flex-shrink:0">{{ $conv['unread'] }}</span>
          @endif
        </a>
      @empty
        <div style="padding:20px;text-align:center">
          <div style="font-size:24px;margin-bottom:8px"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <div style="font-weight:600;font-size:12px;color:var(--text2)">No conversations yet</div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px">Buy something first, then message the seller from your orders.</div>
          <a href="{{ url('/') }}" style="display:inline-block;margin-top:10px;padding:7px 14px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:11px">Shop now</a>
        </div>
      @endforelse
    </div>
    <script>
    (function(){
      var box = document.getElementById('chatSearch');
      if(!box) return;
      box.addEventListener('input', function(){
        var q = box.value.toLowerCase().trim();
        document.querySelectorAll('#convList > a').forEach(function(a){
          a.style.display = (!q || (a.getAttribute('data-search') || '').indexOf(q) !== -1) ? '' : 'none';
        });
      });
    })();
    </script>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column">
    @if($selId)
      @php
        $sInit = strtoupper(substr($selName,0,1));
        $sColors = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
        $sColor = $sColors[array_sum(array_map('ord', str_split((string)$selId))) % count($sColors)];
      @endphp
      <div style="display:flex;gap:10px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--border)">
        <div style="width:40px;height:40px;background:{{ $sColor }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0">{{ $sInit }}</div>
        <div style="flex:1">
          <div style="font-weight:700;font-size:14px">@if(\App\Models\Seller::where('user_id',$selId)->exists())<a href="{{ url('/store/'.$selId) }}" style="color:inherit;text-decoration:none">{{ $selName }}</a>@else{{ $selName }}@endif</div>
          <div style="font-size:11px;color:var(--text3)">@if(\App\Models\Seller::where('user_id',$selId)->exists())<a href="{{ url('/store/'.$selId) }}" style="color:var(--primary-dark,#0E4A57);font-weight:600;text-decoration:none">View store</a>@else<span style="color:var(--primary-dark,#0E4A57);font-weight:600">Official support</span>@endif</div>
        </div>
      </div>
      <div id="msgs" style="flex:1;min-height:280px;max-height:420px;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:8px">
        @forelse($selMessages as $m)
          @php
            $isMe = $m->sender_id == $buyer['id'];
            [$roleLabel, $roleFg, $roleBg] = \App\Models\Message::roleBadge($m->senderRole());
          @endphp
          @if($isMe)
            <div style="align-self:flex-end;background:var(--green);color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:70%;font-size:13px;line-height:1.4">{{ $m->body }}<div style="font-size:9px;opacity:.6;margin-top:3px;text-align:right">You · {{ $m->created_at->format('h:i A') }}</div></div>
          @else
            <div style="align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:70%;font-size:13px;line-height:1.4"><span style="display:inline-block;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;background:{{ $roleBg }};color:{{ $roleFg }};margin-bottom:4px">{{ $roleLabel }}</span><div>{{ $m->body }}</div><div style="font-size:9px;color:var(--text3);margin-top:3px">{{ $m->created_at->format('h:i A') }}</div></div>
          @endif
        @empty
          <div style="flex:1;display:grid;place-items:center;text-align:center;color:var(--text3);font-size:12px">
            <div>
              <div style="font-weight:600">Start a conversation</div>
              <div>Send a message to {{ $selName }}</div>
            </div>
          </div>
        @endforelse
      </div>
      <form id="chatForm" style="display:flex;gap:8px;padding:12px 14px;border-top:1px solid var(--border)">
        @csrf
        <input id="msgInput" type="text" placeholder="Type your message..." style="flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:999px;outline:none;font-size:13px;font-family:inherit" autocomplete="off">
        <button type="submit" id="sendBtn" style="padding:10px 18px;background:var(--green);color:#fff;border:none;border-radius:999px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit">Send</button>
      </form>
      <script>
      (function(){
        const msgs = document.getElementById('msgs');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('msgInput');
        const sellerId = {{ (int)$selId }};
        let lastTime = '{{ $selMessages->isNotEmpty() ? $selMessages->last()->created_at->toDateTimeString() : '' }}';
        if(msgs) msgs.scrollTop = msgs.scrollHeight;
        function appendMsg(body, isMe, time, roleLabel){
          const div = document.createElement('div');
          if(isMe){
            div.style.cssText = 'align-self:flex-end;background:#134e4a;color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:70%;font-size:13px;line-height:1.4';
            div.innerHTML = body + '<div style="font-size:9px;opacity:.6;margin-top:3px;text-align:right">You · ' + time + '</div>';
          } else {
            div.style.cssText = 'align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:70%;font-size:13px;line-height:1.4';
            const tag = roleLabel ? '<span style="display:inline-block;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;background:#e0f2f1;color:#0E4A57;margin-bottom:4px">' + roleLabel + '</span><div>' : '<div>';
            div.innerHTML = tag + body + '</div><div style="font-size:9px;color:#999;margin-top:3px">' + time + '</div>';
          }
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
              lastTime = now.toISOString().slice(0,19).replace('T',' ');
              input.value = '';
            }
          }).finally(()=>{ btn.disabled = false; });
        });
        setInterval(function(){
          if(!msgs) return;
          const url = '/chat/fetch/' + sellerId + (lastTime ? '?after=' + encodeURIComponent(lastTime) : '');
          fetch(url, {headers:{'Accept':'application/json'}})
            .then(r => r.json())
            .then(list => {
              list.forEach(m => {
                appendMsg(m.body, m.is_me, new Date(m.created_at).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}), m.sender_label);
                if(m.created_at > lastTime) lastTime = m.created_at;
              });
            });
        }, 3000);
      })();
      </script>
    @else
      <div style="flex:1;display:grid;place-items:center;text-align:center;padding:16px">
        <div>
          <div style="width:56px;height:56px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto;color:var(--green)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <div style="font-weight:700;margin-top:10px">Select a conversation</div>
          <div style="font-size:12px;color:var(--text3);margin-top:4px">Pick a chat on the left to continue messaging.</div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
