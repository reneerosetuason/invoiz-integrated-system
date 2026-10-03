@extends('layouts.website')
@section('content')
@php $buyer = session('buyer'); @endphp
<h2 style="font-weight:800">Messages</h2>
<p style="color:var(--text3);font-size:12px;margin-top:2px">Chat with sellers you've ordered from</p>
<div style="display:grid;grid-template-columns:320px 1fr;gap:16px;margin-top:14px;min-height:420px">
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
    <div style="padding:12px 14px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px">Chats</div>
    <div style="padding:8px;max-height:380px;overflow:auto">
      @forelse($conversations as $conv)
        @php
          $initial = strtoupper(substr('S',0,1));
          $colors = ['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C'];
          $color = $colors[array_sum(array_map('ord', str_split((string)$conv['other_id']))) % count($colors)];
        @endphp
        <a href="{{ url('/chat/seller/'.$conv['other_id']) }}" style="display:flex;gap:10px;align-items:center;padding:10px;border-radius:10px;text-decoration:none;color:inherit;margin-bottom:4px;transition:all .15s;border:1px solid {{ request()->is('chat/seller/'.$conv['other_id']) ? 'var(--green)' : 'transparent' }};background:{{ request()->is('chat/seller/'.$conv['other_id']) ? '#f0fdf4' : 'transparent' }}" onmouseover="this.style.background='#f8f8f8'" onmouseout="this.style.background='{{ request()->is('chat/seller/'.$conv['other_id']) ? '#f0fdf4' : 'transparent' }}'">
          <div style="width:42px;height:42px;background:{{ $color }};border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0">{{ $initial }}</div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="font-weight:700;font-size:13px">Seller #{{ $conv['other_id'] }}</div>
              <div style="font-size:10px;color:var(--text3)">{{ $conv['last']->created_at->diffForHumans() }}</div>
            </div>
            <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px">{{ $conv['last']->body }}</div>
          </div>
          @if($conv['unread'] > 0)
            <span style="background:var(--danger);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:999px;flex-shrink:0">{{ $conv['unread'] }}</span>
          @endif
        </a>
      @empty
        <div style="padding:20px;text-align:center">
          <div style="font-size:24px;margin-bottom:8px">💬</div>
          <div style="font-weight:600;font-size:12px;color:var(--text2)">No conversations yet</div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px">Buy something first, then message the seller from your orders.</div>
          <a href="{{ url('/') }}" style="display:inline-block;margin-top:10px;padding:7px 14px;background:var(--green);color:#fff;border-radius:999px;text-decoration:none;font-weight:700;font-size:11px">Shop now</a>
        </div>
      @endforelse
    </div>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px;display:grid;place-items:center;text-align:center">
    <div>
      <div style="width:56px;height:56px;background:#f0fdf4;border-radius:50%;display:grid;place-items:center;margin:0 auto;color:var(--green);font-size:22px">💬</div>
      <div style="font-weight:700;margin-top:10px">Select a conversation</div>
      <div style="font-size:12px;color:var(--text3);margin-top:4px">Pick a chat on the left to continue messaging.</div>
    </div>
  </div>
</div>
@endsection
