@extends('buyer.layouts.website')
@section('content')
@php
  $notificationCount = $items instanceof \Illuminate\Pagination\LengthAwarePaginator ? $items->total() : count($items);
@endphp
<div class="notifx buyerfeature-page">
  <div class="bfp-wrap">
    <header class="bfp-head">
      <h1>Notifications</h1>
      <span class="bfp-meta">{{ $notificationCount }} {{ $notificationCount === 1 ? 'notification' : 'notifications' }}</span>
    </header>

    <div class="notif-subrow">
      <p>Order updates and new seller messages, newest first</p>
      @if($items->isNotEmpty())
        <form method="POST" action="{{ url('/notifications/read-all') }}">
          @csrf
          <button type="submit" class="notif-mark-all">Mark all as read</button>
        </form>
      @endif
    </div>

    @if(session('success'))
      <div class="bfp-flash bfp-flash-success" role="status">{{ session('success') }}</div>
    @endif

    @if($items->isNotEmpty())
      <div class="notif-list">
        @foreach($items as $n)
          <article class="notif-row">
            <div class="notif-icon" aria-hidden="true">{!! $n['icon'] !!}</div>
            <div class="notif-copy">
              <a href="{{ $n['url'] }}" class="notif-title">{{ $n['title'] }}</a>
              <p class="notif-body">{{ $n['body'] }}</p>
            </div>
            <div class="notif-time">{{ $n['time'] ? $n['time']->diffForHumans() : '' }}</div>
            <form method="POST" action="{{ url('/notifications/read') }}" class="notif-dismiss-form">
              @csrf
              <input type="hidden" name="key" value="{{ $n['key'] }}">
              <button type="submit" class="notif-dismiss" title="Dismiss" aria-label="Dismiss notification">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
              </button>
            </form>
          </article>
        @endforeach
      </div>
    @else
      <div class="bfp-empty notif-empty">
        <svg class="bfp-empty-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h2>You're all caught up</h2>
        <p>Your order updates and messages will appear here.</p>
      </div>
    @endif

    @if($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
      <div class="bfp-pager notif-pager">
        @include('admin.partials.pager', ['paginator' => $items])
      </div>
    @endif
  </div>
</div>
@endsection
