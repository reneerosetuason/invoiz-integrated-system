@extends('buyer.layouts.website')
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
      $sname = \App\Models\Seller::nameFor($sellerId);
      $initial = strtoupper(substr($sname,0,1));
      $colors = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
      $color = $colors[array_sum(array_map('ord', str_split((string)$sellerId))) % count($colors)];
    @endphp
    <div style="width:42px;height:42px;background:{{ $color }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0">{{ $initial }}</div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px"><a href="{{ url('/store/'.$sellerId) }}" style="color:inherit;text-decoration:none">{{ $sname }}</a></div>
      <div style="font-size:11px;color:var(--text3)"><a href="{{ url('/store/'.$sellerId) }}" style="color:var(--primary-dark,#0E4A57);font-weight:600;text-decoration:none">View store</a> • typically replies in 5 min</div>
    </div>
    <span style="width:8px;height:8px;background:var(--success);border-radius:50%"></span>
  </div>
  <div id="msgs" style="height:380px;overflow:auto;padding:16px;display:flex;flex-direction:column;gap:8px">
    @forelse($messages as $m)
      @php
        $isMe = $m->sender_id == $buyer['id'];
        [$roleLabel, $roleFg, $roleBg] = \App\Models\Message::roleBadge($m->senderRole());
      @endphp
      @if($isMe)
        <div style="align-self:flex-end;background:var(--green);color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;max-width:65%;font-size:13px;line-height:1.4">{{ $m->body }}<div style="font-size:9px;opacity:.6;margin-top:3px;text-align:right">You · {{ $m->created_at->format('h:i A') }}</div></div>
      @else
        <div style="align-self:flex-start;background:#f0f0f0;padding:10px 14px;border-radius:12px 12px 12px 2px;max-width:65%;font-size:13px;line-height:1.4"><span style="display:inline-block;font-size:9px;font-weight:800;padding:1px 7px;border-radius:999px;background:{{ $roleBg }};color:{{ $roleFg }};margin-bottom:4px">{{ $roleLabel }}</span><div>{{ $m->body }}</div><div style="font-size:9px;color:var(--text3);margin-top:3px">{{ $m->created_at->format('h:i A') }}</div></div>
      @endif
    @empty
      <div style="flex:1;display:grid;place-items:center;text-align:center;color:var(--text3);font-size:12px">
        <div>
          <div style="margin-bottom:6px;color:var(--green)"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <div style="font-weight:600">Start a conversation</div>
          <div>Send a message to {{ \App\Models\Seller::nameFor($sellerId) }}</div>
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
<script src="{{ asset('assets/buyer/js/chat-thread.js') }}" data-config="{{ json_encode([($sellerId), ($messages->isNotEmpty() ? (int)$messages->last()->id : 0)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endsection
