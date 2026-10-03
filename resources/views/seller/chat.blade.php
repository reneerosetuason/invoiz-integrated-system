@extends('seller.layout')

@section('content')
<style>
  .chat-grid { display:grid; grid-template-columns:320px 1fr; gap:16px; height:calc(100vh - 160px); min-height:520px; }
  .panel { background:#fff; border:1px solid #E5E5E5; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,.04); display:flex; flex-direction:column; overflow:hidden; }
  .panel-head { padding:16px 18px; border-bottom:1px solid #EEEFF1; display:flex; align-items:center; gap:10px; font-size:15px; font-weight:800; letter-spacing:-.2px; }
  .panel-head svg{ width:18px; height:18px; stroke:#116B78; fill:none; stroke-width:1.8; }
  .search-box{ padding:12px; border-bottom:1px solid #F1F2F4; }
  .search-box input{ width:100%; padding:9px 12px 9px 34px; border:1px solid #E5E7EB; border-radius:10px; font-size:13px; background:#F9FAFB; outline:none; }
  .search-box input:focus{ border-color:#116B78; background:#fff; box-shadow:0 0 0 3px rgba(17,107,120,.12); }
  .search-wrap{ position:relative; }
  .search-wrap svg{ position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; stroke:#9CA3AF; fill:none; stroke-width:1.8; }
  .conv-list { overflow-y:auto; flex:1; }
  .conv { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #F6F7F8; cursor:pointer; text-decoration:none; color:#1A202C; transition:background .12s; }
  .conv:hover { background:#F9FAFB; }
  .conv.active { background:#E6F1F2; border-left:3px solid #116B78; }
  .avatar { width:40px; height:40px; border-radius:50%; background:#E6F1F2; color:#116B78; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .avatar.online::after{ content:''; position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:#2E8B57; border:2px solid #fff; }
  .conv-name { font-weight:700; font-size:13.5px; line-height:1.2; }
  .conv-last { font-size:12px; color:#6B7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:170px; margin-top:2px; }
  .conv-meta { margin-left:auto; text-align:right; flex-shrink:0; }
  .conv-time { font-size:11px; color:#9CA3AF; }
  .unread { background:#D64545; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; padding:0 5px; margin-top:4px; }
  .thread-head{ padding:14px 18px; border-bottom:1px solid #EEEFF1; display:flex; align-items:center; gap:12px; background:linear-gradient(180deg,#FAFAF8 0%,#fff 100%); }
  .thread-head .name{ font-weight:800; font-size:14px; }
  .thread-head .sub{ font-size:11px; color:#6B7280; display:flex; align-items:center; gap:4px; }
  .status-dot{ width:8px; height:8px; border-radius:50%; background:#2E8B57; display:inline-block; }
  .thread-body { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:10px; background:#F7F6F2; }
  .date-sep{ display:flex; align-items:center; gap:12px; margin:8px 0; }
  .date-sep::before, .date-sep::after{ content:''; flex:1; height:1px; background:#E8E6E0; }
  .date-sep span{ font-size:11px; font-weight:700; color:#9CA3AF; background:#F0EEE9; padding:3px 10px; border-radius:999px; }
  .bubble { max-width:68%; padding:11px 14px; border-radius:16px; font-size:13.5px; line-height:1.5; position:relative; word-wrap:break-word; }
  .bubble.me { align-self:flex-end; background:#116B78; color:#fff; border-bottom-right-radius:4px; box-shadow:0 2px 8px rgba(17,107,120,.18); }
  .bubble.them { align-self:flex-start; background:#fff; color:#1A202C; border:1px solid #E5E5E5; border-bottom-left-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .bubble-time{ display:flex; align-items:center; gap:4px; font-size:10px; margin-top:6px; opacity:.7; }
  .bubble.me .bubble-time{ color:#BFDBFE; justify-content:flex-end; }
  .bubble.them .bubble-time{ color:#9CA3AF; }
  .thread-empty{ display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#9CA3AF; gap:10px; text-align:center; padding:24px; }
  .thread-empty svg{ width:48px; height:48px; stroke:#D1D5DB; fill:none; stroke-width:1.5; }
  .composer{ padding:12px 14px; border-top:1px solid #EEEFF1; display:flex; gap:10px; align-items:end; background:#fff; }
  .composer-input{ flex:1; display:flex; align-items:center; gap:8px; background:#F4F5F7; border:1px solid #E5E5E5; border-radius:24px; padding:6px 6px 6px 14px; transition:border-color .15s; }
  .composer-input:focus-within{ border-color:#116B78; background:#fff; box-shadow:0 0 0 3px rgba(17,107,120,.1); }
  .composer-input input{ flex:1; border:none; background:transparent; outline:none; font-size:13.5px; padding:6px 0; }
  .icon-btn{ width:32px; height:32px; border-radius:50%; border:none; background:transparent; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#6B7280; transition:background .15s; flex-shrink:0; }
  .icon-btn:hover{ background:#F3F4F6; color:#374151; }
  .icon-btn svg{ width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:1.8; }
  .send-btn{ width:40px; height:40px; border-radius:50%; background:#116B78; color:#fff; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:transform .15s, background .15s; box-shadow:0 2px 8px rgba(17,107,120,.2); }
  .send-btn:hover{ background:#0C555F; transform:scale(1.05); }
  .send-btn:disabled{ opacity:.5; cursor:not-allowed; transform:none; }
  .send-btn svg{ width:18px; height:18px; stroke:#fff; fill:none; stroke-width:2; }
  @media (max-width:900px) { .chat-grid { grid-template-columns:1fr; height:auto; } .conv-list { max-height:260px; } }
</style>

<div class="chat-grid">
  <div class="panel">
    <div class="panel-head">
      <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
      Conversations
      <span style="margin-left:auto; font-size:11px; font-weight:700; background:#E6F1F2; color:#116B78; padding:3px 8px; border-radius:999px;">{{ $conversations->count() }}</span>
    </div>
    <div class="search-box">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="convSearch" placeholder="Search conversations..." />
      </div>
    </div>
    <div class="conv-list" id="convList">
      @forelse ($conversations as $c)
        <a class="conv {{ ($otherUser && $otherUser->id === $c->user->id) ? 'active' : '' }}" href="/seller/chat?with={{ $c->user->id }}" data-name="{{ strtolower($c->user->name ?? '') }}" data-user-id="{{ $c->user->id }}">
          <div style="position:relative;">
            <div class="avatar">{{ strtoupper(mb_substr($c->user->name ?? '?', 0, 1)) }}</div>
            <span style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:#2E8B57; border:2px solid #fff;"></span>
          </div>
          <div style="min-width:0; flex:1;">
            <div class="conv-name">{{ $c->user->name ?? 'Unknown' }}</div>
            <div class="conv-last">{{ Str::limit($c->last->body, 28) }}</div>
          </div>
          <div class="conv-meta">
            <div class="conv-time">{{ $c->last->created_at->diffForHumans() }}</div>
            @if ($c->unread > 0)<div class="unread">{{ $c->unread }}</div>@endif
          </div>
        </a>
      @empty
        <div style="text-align:center; padding:40px 16px; color:#9CA3AF;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin:0 auto 10px; display:block;"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
          <div style="font-weight:600;">No conversations yet</div>
          <div style="font-size:12px; margin-top:4px;">Buyers will appear here when<br>they message your store.</div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    @if ($otherUser)
      <div class="thread-head">
        <div style="position:relative;">
          <div class="avatar" style="width:38px; height:38px;">{{ strtoupper(mb_substr($otherUser->name, 0, 1)) }}</div>
          <span style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:#2E8B57; border:2px solid #fff;"></span>
        </div>
        <div>
          <div class="name">{{ $otherUser->name }}</div>
          <div class="sub"><span class="status-dot"></span> Active now · Buyer</div>
        </div>
        <div style="margin-left:auto; display:flex; gap:6px;">
          <button class="icon-btn" title="Voice call"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.6 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.6c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.7 2z"/></svg></button>
          <button class="icon-btn" title="Video call"><svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7Z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></button>
        </div>
      </div>
      <div class="thread-body" id="thread">
        <div class="date-sep"><span>Today</span></div>
        @forelse ($thread as $m)
          <div class="bubble {{ $m->sender_id === Auth::id() ? 'me' : 'them' }}">
            <div>{{ $m->body }}</div>
            <span class="bubble-time">{{ $m->created_at->format('g:i A') }} @if($m->sender_id === Auth::id()) <x-ui-icon name="checks" :size="12" /> @endif</span>
          </div>
        @empty
          <div class="thread-empty" id="thread-empty">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <div style="font-weight:700; color:#374151;">No messages yet</div>
            <div style="font-size:13px;">Say hello to {{ $otherUser->name }} — start the conversation!</div>
          </div>
        @endforelse
      </div>
      <form class="composer" id="composer" method="POST" action="{{ url('/seller/chat/send') }}" data-messages-url="{{ url('/seller/chat/'.$otherUser->id.'/messages') }}">
        @csrf
        <input type="hidden" name="receiver_id" value="{{ $otherUser->id }}" />
        <div class="composer-input">
          <button type="button" class="icon-btn" title="Attach"><svg viewBox="0 0 24 24"><path d="M21.4 11.5L12 20.9a5 5 0 0 1-7-7l9.4-9.4a3 3 0 0 1 4.2 4.2L10 17"/><path d="M14 12l-4 4"/></svg></button>
          <input type="text" name="body" placeholder="Type a message..." required autocomplete="off" />
        </div>
        <button type="submit" class="send-btn" title="Send">
          <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
        </button>
      </form>
    @else
      <div class="thread-empty" style="flex:1; background:#F7F6F2;">
        <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 12h8"/><path d="M12 8v4"/></svg>
        <div style="font-weight:700; color:#374151; margin-top:8px;">Select a conversation</div>
        <div style="font-size:13px; max-width:260px; text-align:center; margin-top:4px;">Choose a buyer from the left to start chatting. Your chats are saved and synced.</div>
      </div>
    @endif
  </div>
</div>

<script>
  var t = document.getElementById('thread');
  if (t) t.scrollTop = t.scrollHeight;
  var search = document.getElementById('convSearch');
  if(search){
    search.addEventListener('input', function(){
      var q = search.value.toLowerCase();
      document.querySelectorAll('#convList .conv').forEach(function(el){
        var name = el.getAttribute('data-name')||'';
        el.style.display = name.includes(q) ? '' : 'none';
      });
    });
  }
</script>

<script>
(function () {
  var thread = document.getElementById('thread');
  var form = document.getElementById('composer');
  if (!thread || !form) return;
  var token = form.querySelector('input[name="_token"]').value;
  var messagesUrl = form.getAttribute('data-messages-url');
  var myId = {{ Auth::id() }};
  var otherId = {{ $otherUser->id ?? 0 }};
  var lastId = {{ $thread->max('id') ?? 0 }};
  var seen = {};
  var initialIds = {{ $thread->pluck('id')->toJson() }};
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
</script>
@endsection
