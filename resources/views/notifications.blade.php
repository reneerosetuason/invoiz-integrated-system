@extends('layouts.website')
@section('content')
<h2 style="font-weight:800">Notifications</h2>
<p style="color:var(--text3);font-size:12px;margin-top:2px">Order updates and new seller messages, newest first</p>

@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;font-size:12px;font-weight:600;margin-top:12px">{{ session('success') }}</div>@endif

<div style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px;margin-top:12px">
  @if($items->isNotEmpty())
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
      <form method="POST" action="{{ url('/notifications/read-all') }}">
        @csrf
        <button type="submit" style="background:transparent;border:1px solid var(--border);border-radius:999px;padding:6px 14px;font-size:11px;font-weight:700;color:var(--text2);cursor:pointer;font-family:inherit">Mark all as read</button>
      </form>
    </div>
    @foreach($items as $n)
      <div style="display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid var(--border-light);border-radius:10px;margin-bottom:8px">
        <div style="width:36px;height:36px;background:var(--bg);color:var(--green);border-radius:50%;display:grid;place-items:center;flex-shrink:0">{!! $n['icon'] !!}</div>
        <div style="flex:1;min-width:0">
          <a href="{{ $n['url'] }}" style="font-weight:700;font-size:13px;color:var(--text);text-decoration:none">{{ $n['title'] }}</a>
          <div style="font-size:12px;color:var(--text2);margin-top:2px">{{ $n['body'] }}</div>
          <div style="font-size:10px;color:var(--text3);margin-top:3px">{{ $n['time'] ? $n['time']->diffForHumans() : '' }}</div>
        </div>
        <form method="POST" action="{{ url('/notifications/read') }}">
          @csrf
          <input type="hidden" name="key" value="{{ $n['key'] }}">
          <button type="submit" title="Dismiss" style="background:transparent;border:none;color:var(--text3);cursor:pointer;padding:4px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </form>
      </div>
    @endforeach
  @else
    <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--bg);border-radius:10px">
      <div style="width:36px;height:36px;background:var(--primary,#16697A);border-radius:50%;display:grid;place-items:center;color:#fff"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
      <div>
        <div style="font-weight:700">You're all caught up</div>
        <div style="font-size:11px;color:var(--text-secondary)">Your order updates and messages will appear here.</div>
      </div>
    </div>
  @endif
</div>
@endsection
