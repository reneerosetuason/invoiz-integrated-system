@extends('layouts.website')
@section('content')
@php
  $buyer = session('buyer');
  $selId = $selId ?? 0;
  $selMessages = $selMessages ?? collect();
  $selName = $selName ?? '';
@endphp
<div class="messagesx buyerfeature-page">
  <div class="bfp-wrap messages-wrap">
    <header class="messages-head">
      <h1>Messages</h1>
      <p>Chat with sellers you've ordered from</p>
    </header>

    <div class="msg-frame{{ $selId ? ' has-selection' : '' }}">
      <aside class="msg-list-pane" aria-label="Chats">
        <div class="msg-list-head">Chats</div>
        <label class="msg-search-field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
          <input type="search" id="chatSearch" placeholder="Search chats or people..." aria-label="Search chats or people" autocomplete="off">
        </label>
        <div id="convList" class="msg-conversation-list">
          @forelse($conversations as $conv)
            @php
              $sname = \App\Models\Seller::nameFor($conv['other_id']);
              $initial = strtoupper(substr($sname, 0, 1));
              $isSel = (int) $selId === (int) $conv['other_id'];
              [$crowLabel] = \App\Models\Message::roleBadge($conv['contact_role'] ?? 'seller');
            @endphp
            <a href="{{ url('/messages?seller='.$conv['other_id']) }}" data-search="{{ strtolower($sname.' '.$conv['last']->body.' '.$crowLabel) }}" class="msg-conversation{{ $isSel ? ' is-selected' : '' }}{{ $conv['unread'] > 0 ? ' has-unread' : '' }}" @if($isSel) aria-current="page" @endif>
              <span class="msg-avatar" aria-hidden="true">{{ $initial }}</span>
              <span class="msg-conversation-copy">
                <span class="msg-conversation-top">
                  <span class="msg-conversation-name">{{ $sname }}</span>
                  <span class="msg-conversation-time">{{ $conv['last']->created_at->diffForHumans() }}</span>
                </span>
                <span class="msg-conversation-bottom">
                  <span class="msg-role">{{ $crowLabel }}</span>
                  <span class="msg-preview">{{ $conv['last_mine'] ? 'You: ' : $conv['last_label'].': ' }}{{ $conv['last']->body }}</span>
                </span>
              </span>
              @if($conv['unread'] > 0)
                <span class="msg-unread">{{ $conv['unread'] }}</span>
              @endif
            </a>
          @empty
            <div class="msg-list-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <div class="msg-empty-title">No conversations yet</div>
              <p>Buy something first, then message the seller from your orders.</p>
              <a href="{{ url('/') }}" class="msg-shop-link">Shop now</a>
            </div>
          @endforelse
        </div>
      </aside>

      <section class="msg-thread-pane" aria-label="Conversation">
        @if($selId)
          @php
            $sInit = strtoupper(substr($selName, 0, 1));
            $selIsSeller = \App\Models\Seller::where('user_id', $selId)->exists();
            [$selRoleLabel] = \App\Models\Message::roleBadge($selIsSeller ? 'seller' : 'buyer');
          @endphp
          <header class="msg-thread-head">
            <a class="msg-back" href="{{ url('/messages') }}" aria-label="Back to chats">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <span class="msg-avatar msg-avatar-large" aria-hidden="true">{{ $sInit }}</span>
            <span class="msg-thread-identity">
              <span class="msg-thread-name">
                @if($selIsSeller)<a href="{{ url('/store/'.$selId) }}">{{ $selName }}</a>@else{{ $selName }}@endif
              </span>
              <span class="msg-thread-subline">
                <span class="msg-role">{{ $selRoleLabel }}</span>
                @if($selIsSeller)<a href="{{ url('/store/'.$selId) }}" class="msg-store-link">View store</a>@else<span class="msg-store-link">Official support</span>@endif
              </span>
            </span>
          </header>
          <div id="msgs" class="msg-history" role="log" aria-label="Conversation messages">
            @forelse($selMessages as $m)
              @php
                $isMe = $m->sender_id == $buyer['id'];
                [$roleLabel] = \App\Models\Message::roleBadge($m->senderRole());
              @endphp
              @if($isMe)
                <div class="msg-bubble msg-own">{{ $m->body }}<div class="msg-time">You · {{ $m->created_at->format('h:i A') }}</div></div>
              @else
                <div class="msg-bubble msg-other"><span class="msg-role msg-sender-role">{{ $roleLabel }}</span><div>{{ $m->body }}</div><div class="msg-time">{{ $m->created_at->format('h:i A') }}</div></div>
              @endif
            @empty
              <div class="msg-thread-empty">
                <div class="msg-thread-empty-title">Start a conversation</div>
                <div>Send a message to {{ $selName }}</div>
              </div>
            @endforelse
          </div>
          <form id="chatForm" class="msg-composer">
            @csrf
            <input id="msgInput" type="text" placeholder="Type your message..." aria-label="Type your message" autocomplete="off">
            <button type="submit" id="sendBtn" aria-label="Send" class="msg-send">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
            </button>
          </form>
          <script>
          (function(){
            const msgs = document.getElementById('msgs');
            const form = document.getElementById('chatForm');
            const input = document.getElementById('msgInput');
            const sellerId = {{ (int)$selId }};
            let lastId = {{ $selMessages->isNotEmpty() ? (int)$selMessages->last()->id : 0 }};
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
          </script>
        @else
          <div class="msg-no-selection">
            <span class="msg-empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </span>
            <h2>Select a conversation</h2>
            <p>Pick a chat on the left to continue messaging.</p>
          </div>
        @endif
      </section>
    </div>
  </div>
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
@endsection
