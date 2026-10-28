<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat - Invoiz Buyer</title>
<link rel="stylesheet" href="{{ asset('assets/buyer/css/chat.css') }}">
</head>
<body>
  <div class="topbar">
    <div class="logo"><img src="{{ asset('assets/shared/images/logo.png') }}" alt="Invoiz" style="width:100%;height:100%;object-fit:cover;" /></div>
    <h1>Chat / Messaging</h1>
    <a class="logout" href="{{ url('/buyer/chat') }}">Refresh</a>
    <form method="POST" action="{{ url('/buyer/logout') }}" style="margin:0;">
      @csrf
      <button type="submit" class="logout" style="background:none;border:none;cursor:pointer;">Log out</button>
    </form>
  </div>

  <div class="wrap">
    <div class="chat-grid">
      <div class="panel">
        <div class="panel-head">
          <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
          Conversations
          <span style="margin-left:auto; font-size:11px; font-weight:700; background:#EAF4F3; color:#16697A; padding:3px 8px; border-radius:999px;">{{ $conversations->count() + $contacts->count() }}</span>
        </div>
        <div class="search-box">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" id="convSearch" placeholder="Search conversations..." />
          </div>
        </div>
        <div class="conv-list" id="convList">
          @forelse ($conversations as $c)
            <a class="conv {{ ($otherUser && $otherUser->id === $c->user->id) ? 'active' : '' }}" href="/buyer/chat?with={{ $c->user->id }}" data-name="{{ strtolower($c->user->name ?? '') }}" data-user-id="{{ $c->user->id }}">
              <div style="position:relative;">
                <div class="avatar">{{ strtoupper(mb_substr($c->user->name ?? '?', 0, 1)) }}</div>
                <span style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:#2E8B57; border:2px solid #fff;"></span>
              </div>
              <div style="min-width:0; flex:1;">
                @php [$browLabel, $browFg, $browBg] = \App\Models\Message::roleBadge(\App\Models\User::chatRoleFor($c->user)); @endphp
                <div class="conv-name">{{ $c->user->name ?? 'Unknown' }}</div>
                <div style="margin:2px 0;"><span style="display:inline-block;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;background:{{ $browBg }};color:{{ $browFg }}">{{ $browLabel }}</span></div>
                <div class="conv-last">{{ $c->last->body }}</div>
              </div>
              <div class="conv-meta">
                <div class="conv-time">{{ $c->last->created_at->diffForHumans() }}</div>
                @if ($c->unread > 0)<div class="unread">{{ $c->unread }}</div>@endif
              </div>
            </a>
          @empty
          @endforelse

          @if ($contacts->count() > 0)
            <div class="section-label">Start a new chat</div>
            @foreach ($contacts as $u)
              <a class="conv {{ ($otherUser && $otherUser->id === $u->id) ? 'active' : '' }}" href="/buyer/chat?with={{ $u->id }}" data-name="{{ strtolower($u->name ?? '') }}" data-user-id="{{ $u->id }}">
                <div style="position:relative;">
                  <div class="avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</div>
                  <span style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:#2E8B57; border:2px solid #fff;"></span>
                </div>
                <div>
                  <div class="conv-name">{{ $u->name }}</div>
                  <div class="conv-role">{{ ucfirst($u->role ?? 'seller') }} · <span style="color:#2E8B57;">● Online</span></div>
                </div>
              </a>
            @endforeach
          @endif
          @if($conversations->isEmpty() && $contacts->isEmpty())
            <div style="text-align:center; padding:40px 16px; color:#9CA3AF;">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin:0 auto 10px; display:block;"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
              <div style="font-weight:600;">No conversations yet</div>
              <div style="font-size:12px; margin-top:4px;">Sellers will appear here.</div>
            </div>
          @endif
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
              <div class="sub"><span class="status-dot"></span> Active now · {{ ucfirst($otherUser->role ?? 'seller') }}</div>
            </div>
            <div style="margin-left:auto; display:flex; gap:6px;">
              <button class="icon-btn" title="Voice call"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.6 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.6c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.7 2z"/></svg></button>
              <button class="icon-btn" title="Video call"><svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7Z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></button>
            </div>
          </div>
          <div class="thread-body" id="thread">
            <div class="date-sep"><span>Today</span></div>
            @forelse ($thread as $m)
              <div class="bubble {{ $m->sender_id === auth()->id() ? 'me' : 'them' }}">
                <div>{{ $m->body }}</div>
                <span class="bubble-time">{{ $m->created_at->format('g:i A') }} @if($m->sender_id === auth()->id()) <x-ui-icon name="checks" :size="12" /> @endif</span>
              </div>
            @empty
              <div class="thread-empty" id="thread-empty">
                <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                <div style="font-weight:700; color:#374151;">No messages yet</div>
                <div style="font-size:13px;">Say hello to {{ $otherUser->name }} — start the conversation!</div>
              </div>
            @endforelse
          </div>
          <form class="composer" id="composer" method="POST" action="{{ url('/buyer/chat/send') }}" data-messages-url="{{ url('/buyer/chat/'.$otherUser->id.'/messages') }}">
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
            <div style="font-weight:700; color:#374151; margin-top:8px;">Select a seller</div>
            <div style="font-size:13px; max-width:260px; text-align:center; margin-top:4px;">Choose a seller to start chatting. Your conversation is saved automatically.</div>
          </div>
        @endif
      </div>
    </div>
  </div>

<script src="{{ asset('assets/buyer/js/chat.js') }}" data-config="{{ json_encode([(auth()->id()), ($otherUser->id ?? 0), ($thread->max('id') ?? 0), ($thread->pluck('id')->toJson())], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
</body>
</html>
