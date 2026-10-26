@extends('admin.layout')

@section('content')
<style>
  .admin-chat-grid{--chat-deep:#0E4A57;--chat-teal:#16697A;--chat-ink:#19383E;--chat-muted:#617579;--chat-rule:rgba(15,76,92,.12);display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:16px;height:calc(100vh - 156px);height:calc(100dvh - 156px);min-height:520px}
  .admin-chat-grid *{box-sizing:border-box}
  .admin-chat-grid .panel{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid var(--chat-rule);border-radius:16px;background:#fff}
  .admin-chat-grid .panel-head{display:flex;min-height:60px;align-items:center;gap:10px;padding:14px 18px;color:var(--chat-ink);font-size:16px;font-weight:650}
  .admin-chat-grid .panel-head>svg{width:18px;height:18px;flex:0 0 18px;fill:none;stroke:var(--chat-teal);stroke-width:1.7}
  .contact-count{margin-left:auto;padding:4px 9px;border-radius:999px;background:#EAF3F2;color:var(--chat-deep);font-size:12px;font-variant-numeric:tabular-nums}
  .admin-chat-grid .search-box{padding:0 14px 14px}
  .admin-chat-grid .search-wrap{position:relative}
  .admin-chat-grid .search-wrap svg{position:absolute;top:50%;left:12px;width:16px;height:16px;transform:translateY(-50%);fill:none;stroke:#74868A;stroke-width:1.7}
  .admin-chat-grid .search-box input{width:100%;height:42px;padding:0 12px 0 36px;border:1px solid var(--chat-rule);border-radius:10px;background:#F7F9F8;color:var(--chat-ink);font:inherit;font-size:13px;outline:0}
  .admin-chat-grid .search-box input:focus{border-color:#7AA6AA;background:#fff;box-shadow:0 0 0 3px rgba(22,105,122,.1)}
  .admin-chat-grid .conv-list{flex:1;overflow-y:auto;padding:0 8px 8px}
  .admin-chat-grid .conv{display:flex;align-items:center;gap:11px;min-height:70px;padding:10px;border-radius:11px;color:var(--chat-ink);text-decoration:none;transition:background-color .15s ease}
  .admin-chat-grid .conv:hover{background:#F6F9F8}
  .admin-chat-grid .conv.active{background:#EAF3F2}
  .admin-chat-grid .avatar{display:flex;width:40px;height:40px;align-items:center;justify-content:center;flex:0 0 40px;border:0;border-radius:50%;background:#D9E9E8;color:var(--chat-deep);font-size:13px;font-weight:700}
  .admin-chat-grid .avatar.seller{background:#F1E8D4;color:#775819}
  .admin-chat-grid .avatar.buyer{background:#E7E4F0;color:#514C78}
  .online-dot{position:absolute;right:0;bottom:0;width:11px;height:11px;border:2px solid #fff;border-radius:50%;background:#4B8B69}
  .admin-chat-grid .conv-name{overflow:hidden;color:var(--chat-ink);font-size:13.5px;font-weight:650;line-height:1.3;text-overflow:ellipsis;white-space:nowrap}
  .admin-chat-grid .conv-sub{margin-top:3px;color:var(--chat-muted);font-size:11px;line-height:1.4}
  .admin-chat-grid .role-badge{display:inline-flex;align-items:center;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:650;line-height:1.25;white-space:nowrap;vertical-align:middle}
  .admin-chat-grid .conv-last{overflow:hidden;margin-top:3px;color:#617579;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
  .admin-chat-grid .conv-time{color:var(--chat-muted);font-size:11px;white-space:nowrap}
  .admin-chat-grid .thread-head{display:flex;min-height:72px;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--chat-rule);background:#fff}
  .admin-chat-grid .thread-head .name{color:var(--chat-ink);font-size:14px;font-weight:650;line-height:1.3}
  .thread-role-line{display:flex;align-items:center;gap:6px;margin-top:3px;color:var(--chat-muted);font-size:11.5px}
  .thread-role-line .status-dot{width:7px;height:7px;border-radius:50%;background:#4B8B69}
  .chat-tools{display:flex;gap:4px;margin-left:auto}
  .admin-chat-grid .icon-btn{display:flex;width:36px;height:36px;align-items:center;justify-content:center;flex:0 0 36px;border:0;border-radius:9px;background:transparent;color:#566B6F;cursor:pointer;transition:background-color .15s ease,color .15s ease}
  .admin-chat-grid .icon-btn:hover{background:#EAF3F2;color:var(--chat-deep)}
  .admin-chat-grid .icon-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .admin-chat-grid .thread-body{display:flex;flex:1;flex-direction:column;gap:10px;overflow-y:auto;padding:18px 20px;background:#F7F9F8;scroll-behavior:smooth}
  .admin-chat-grid .date-sep{align-self:center;margin:4px 0 8px}
  .admin-chat-grid .date-sep span{display:inline-flex;padding:5px 11px;border-radius:999px;background:#E7EFED;color:#536A6D;font-size:11px;font-weight:600}
  .admin-chat-grid .bubble{position:relative;max-width:min(72%,560px);padding:10px 13px;border-radius:14px;background:#fff;color:var(--chat-ink);font-size:13.5px;line-height:1.5;overflow-wrap:anywhere}
  .admin-chat-grid .bubble.me{align-self:flex-end;border-bottom-right-radius:4px;background:var(--chat-deep);color:#fff}
  .admin-chat-grid .bubble.them{align-self:flex-start;border-bottom-left-radius:4px}
  .admin-chat-grid .bubble-time{display:flex;align-items:center;justify-content:flex-end;gap:4px;margin-top:5px;color:#D0E2E3;font-size:10px;line-height:1}
  .admin-chat-grid .bubble.them .bubble-time{color:#758588}
  .admin-chat-grid .thread-empty{display:flex;min-height:180px;flex:1;flex-direction:column;align-items:center;justify-content:center;gap:9px;padding:24px;color:var(--chat-muted);text-align:center}
  .admin-chat-grid .thread-empty svg{width:42px;height:42px;fill:none;stroke:#8AA3A5;stroke-width:1.5}
  .thread-empty-title{color:var(--chat-ink);font-weight:650}
  .thread-empty-copy{max-width:330px;font-size:13px;line-height:1.5}
  .admin-chat-grid .composer{display:flex;align-items:center;gap:10px;padding:12px 14px;border-top:1px solid var(--chat-rule);background:#fff}
  .admin-chat-grid .composer-input{display:flex;min-width:0;min-height:46px;flex:1;align-items:center;gap:6px;padding:4px 8px;border:1px solid var(--chat-rule);border-radius:12px;background:#F7F9F8;transition:border-color .15s ease,background-color .15s ease}
  .admin-chat-grid .composer-input:focus-within{border-color:#7AA6AA;background:#fff}
  .admin-chat-grid .composer-input input{min-width:0;flex:1;padding:8px 4px;border:0;outline:0;background:transparent;color:var(--chat-ink);font:inherit;font-size:13.5px}
  .admin-chat-grid .send-btn{display:flex;width:42px;height:42px;align-items:center;justify-content:center;flex:0 0 42px;border:0;border-radius:11px;background:var(--chat-deep);color:#fff;cursor:pointer;transition:background-color .15s ease,transform .15s ease}
  .admin-chat-grid .send-btn:hover{background:var(--chat-teal);transform:translateY(-1px)}
  .admin-chat-grid .send-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
  .admin-chat-grid .send-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
  .admin-chat-grid :focus-visible{outline:2px solid var(--chat-teal);outline-offset:2px}
  .admin-chat-grid .chat-back{display:none}
  @media(max-width:760px){
    .admin-chat-grid{display:block;height:auto;min-height:0}
    .admin-chat-grid .panel{min-height:calc(100dvh - 150px)}
    .admin-chat-grid.has-selection .contacts-panel{display:none}
    .admin-chat-grid:not(.has-selection) .thread-panel{display:none}
    .admin-chat-grid .conv-list{max-height:calc(100dvh - 265px)}
    .admin-chat-grid .thread-panel{height:calc(100dvh - 150px);min-height:460px}
    .admin-chat-grid .thread-head{gap:9px;padding:10px 12px}
    .admin-chat-grid .chat-back{display:inline-flex;align-items:center;min-height:36px;padding:0 8px;color:var(--chat-deep);font-size:12px;font-weight:600;text-decoration:none}
    .admin-chat-grid .chat-tools{gap:0}
    .admin-chat-grid .icon-btn{width:32px;height:32px;flex-basis:32px}
    .admin-chat-grid .thread-body{padding:14px 12px}
    .admin-chat-grid .bubble{max-width:86%}
    .admin-chat-grid .composer{padding:10px}
  }
  @media(prefers-reduced-motion:reduce){.admin-chat-grid *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
</style>

<div class="admin-chat-grid {{ $selectedUser ? 'has-selection' : '' }}">
  <!-- Contacts -->
  <div class="panel contacts-panel">
    <div class="panel-head">
      <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
      Messages
      <span class="contact-count">{{ count($users) }}</span>
    </div>
    <div class="search-box">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="contactSearch" placeholder="Search contacts..." />
      </div>
    </div>
    <div class="conv-list" id="convList">
      @forelse($users as $user)
        @php $isActive = $selectedUser && $selectedUser->id === $user->id; @endphp
        <a href="{{ url('/admin/chat?user_id='.$user->id) }}" class="conv {{ $isActive ? 'active' : '' }}" data-name="{{ strtolower($user->name) }}" data-user-id="{{ $user->id }}">
          <div style="position:relative;">
            <div class="avatar {{ $user->role === 'seller' ? 'seller' : ($user->role === 'buyer' ? 'buyer' : 'admin') }}">{{ strtoupper(mb_substr($user->name,0,1)) }}</div>
            <span class="online-dot" title="Online"></span>
          </div>
          <div style="min-width:0; flex:1;">
            <div class="conv-name">{{ $user->name }}</div>
            <div class="conv-sub conv-role-text" style="font-size:11px; color:#6B7280;">
              @foreach($user->roleBadges() as $badge)
                <span class="role-badge" style="background:{{ $badge[2] }};color:{{ $badge[1] }};margin-right:4px">{{ $badge[0] }}</span>
              @endforeach
              · <span style="color:#2E8B57;">● Online</span>
            </div>
            @if($user->last_message)
              <div class="conv-last" style="font-size:12px; color:#6B7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">{{ Str::limit($user->last_message->body, 28) }}</div>
            @endif
          </div>
          <div style="text-align:right; flex-shrink:0;">
            @if($user->last_message)
              <div class="conv-time" style="font-size:11px; color:#9CA3AF;">{{ $user->last_message->created_at->diffForHumans() }}</div>
            @endif
            @if($isActive)
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            @endif
          </div>
        </a>
      @empty
        <div style="text-align:center; padding:40px 16px; color:#9CA3AF;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin:0 auto 10px; display:block;"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
          <div style="font-weight:600;">No contacts yet</div>
          <div style="font-size:12px; margin-top:4px;">Users will appear here when they message you.</div>
        </div>
      @endforelse
    </div>
  </div>

  <!-- Thread -->
  <div class="panel thread-panel">
    @if($selectedUser)
      <div class="thread-head">
        <a class="chat-back" href="{{ url('/admin/chat') }}">Back</a>
        <div style="position:relative;">
          <div class="avatar {{ $selectedUser->role === 'seller' ? 'seller' : ($selectedUser->role === 'buyer' ? 'buyer' : 'admin') }}" style="width:38px; height:38px;">{{ strtoupper(mb_substr($selectedUser->name,0,1)) }}</div>
          <span class="online-dot"></span>
        </div>
        <div>
          <div class="name">{{ $selectedUser->name }}</div>
          <div class="thread-role-line"><span class="status-dot"></span> Active now ·
            @foreach($selectedUser->roleBadges() as $badge)
              <span class="role-badge" style="background:{{ $badge[2] }};color:{{ $badge[1] }}">{{ $badge[0] }}</span>
            @endforeach
          </div>
        </div>
        <div class="chat-tools">
          <button class="icon-btn" title="Voice call"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.6 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.6c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.7 2z"/></svg></button>
          <button class="icon-btn" title="Video call"><svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7Z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></button>
          <button class="icon-btn" title="Info"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></button>
        </div>
      </div>

      <div id="thread" class="thread-body">
        <div class="date-sep"><span>Today</span></div>
        @forelse($messages as $msg)
          <div class="bubble {{ $msg->sender_id === auth()->id() ? 'me' : 'them' }}">
            <div>{{ $msg->body }}</div>
            <span class="bubble-time">
              {{ $msg->created_at->format('g:i A') }}
              @if($msg->sender_id === auth()->id())
                <x-ui-icon name="checks" :size="12" />
              @endif
            </span>
          </div>
        @empty
          <div id="no-messages" class="thread-empty">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <div class="thread-empty-title">No messages yet</div>
            <div class="thread-empty-copy">Say hello to {{ $selectedUser->name }} — start the conversation!</div>
          </div>
        @endforelse
      </div>

      <form id="composer" method="POST" action="{{ url('/admin/chat/send') }}" data-messages-url="{{ url('/admin/chat/'.$selectedUser->id.'/messages') }}" class="composer">
        @csrf
        <input type="hidden" name="receiver_id" value="{{ $selectedUser->id }}" />
        <div class="composer-input">
          <button type="button" class="icon-btn" title="Attach file"><svg viewBox="0 0 24 24"><path d="M21.4 11.5L12 20.9a5 5 0 0 1-7-7l9.4-9.4a3 3 0 0 1 4.2 4.2L10 17"/><path d="M14 12l-4 4"/></svg></button>
          <input type="text" name="body" placeholder="Type a message..." required autocomplete="off" />
        </div>
        <button type="submit" class="send-btn" title="Send message">
          <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
        </button>
      </form>
    @else
      <div class="thread-empty">
        <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 12h8"/><path d="M12 8v4"/></svg>
        <div class="thread-empty-title">Select a conversation</div>
        <div class="thread-empty-copy">Choose a user from the left to start messaging. Your chats are end-to-end and auto-saved.</div>
      </div>
    @endif
  </div>
</div>

<script>
(function () {
  var thread = document.getElementById('thread');
  var form = document.getElementById('composer');
  var search = document.getElementById('contactSearch');
  if (thread) thread.scrollTop = thread.scrollHeight;
  if (search) {
    search.addEventListener('input', function(){
      var q = search.value.toLowerCase();
      document.querySelectorAll('#convList .conv').forEach(function(el){
        var name = el.getAttribute('data-name') || '';
        el.style.display = name.includes(q) ? '' : 'none';
      });
    });
  }
  if (!thread || !form) return;
  var token = form.querySelector('input[name="_token"]').value;
  var messagesUrl = form.getAttribute('data-messages-url');
  var myId = {{ auth()->id() }};
  var otherId = {{ $selectedUser->id ?? 0 }};
  var lastId = {{ $messages->max('id') ?? 0 }};
  var seen = {};
  var initialIds = {{ $messages->pluck('id')->toJson() }};
  initialIds.forEach(function (id) { seen[id] = true; });
  var sending = false; var polling = false;
  function fmt(iso){ return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); }
  function bumpConversation(userId, text){
    var list = document.getElementById('convList');
    if(!list || !userId) return;
    var el = list.querySelector('.conv[data-user-id="'+userId+'"]');
    if(!el) return;
    var lastEl = el.querySelector('.conv-last');
    if(lastEl) lastEl.textContent = text.length > 28 ? text.substring(0,28) + '...' : text;
    var timeEl = el.querySelector('.conv-time');
    if(timeEl) timeEl.textContent = 'now';
    list.prepend(el);
  }
  function addBubble(msg, mine){
    if (seen[msg.id]) return; seen[msg.id] = true;
    var empty = document.getElementById('no-messages');
    if (empty) empty.remove();
    var wrap = document.createElement('div');
    wrap.className = 'bubble ' + (mine ? 'me' : 'them');
    var txt = document.createElement('div'); txt.textContent = msg.body;
    var time = document.createElement('span'); time.className = 'bubble-time';
    time.textContent = fmt(msg.created_at);
    if (mine) { time.insertAdjacentHTML('beforeend', ' <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M18 6L7 17l-5-5"/><path d="M22 10l-7.5 7.5L13 19"/></svg>'); }
    wrap.appendChild(txt); wrap.appendChild(time);
    thread.appendChild(wrap);
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
  // Enter to send, Shift+Enter for newline (input is single line, so Enter sends)
  var inputEl = form.querySelector('[name=body]');
  if(inputEl){
    inputEl.addEventListener('keydown', function(e){
      if(e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); form.dispatchEvent(new Event('submit')); }
    });
  }
  setInterval(poll, 3000);
})();
</script>
@endsection
