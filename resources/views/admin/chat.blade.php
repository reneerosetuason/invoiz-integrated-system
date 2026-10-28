@extends('admin.layout')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/chat.css') }}">

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

<script src="{{ asset('assets/admin/js/chat.js') }}" data-config="{{ json_encode([(auth()->id()), ($selectedUser->id ?? 0), ($messages->max('id') ?? 0), ($messages->pluck('id')->toJson())], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endsection
