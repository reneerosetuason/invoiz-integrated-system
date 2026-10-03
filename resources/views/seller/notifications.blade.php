@extends('seller.layout')

@section('content')
<style>
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; letter-spacing: -.3px; }
  .notif { display: flex; gap: 14px; padding: 16px 0; border-bottom: 1px solid #F1F2F4; }
  .notif:last-child { border-bottom: none; }
  .notif-ico { width: 40px; height: 40px; border-radius: 12px; background: #E6F1F2; color: #116B78; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700; }
  .notif-subject { font-weight: 700; font-size: 14px; }
  .notif-body { color: #6B7280; font-size: 13px; line-height: 1.5; margin-top: 3px; white-space: pre-line; }
  .notif-time { font-size: 12px; color: #9CA3AF; margin-top: 4px; }
  .pagination { margin-top: 16px; }
  .pagination a, .pagination span { color: #116B78; margin-right: 6px; font-weight: 600; font-size: 14px; text-decoration: none; }
  .pagination .disabled { color: #9CA3AF; }
</style>

<div class="panel">
  <h3 class="panel-title">Notifications</h3>
  @forelse ($notifications as $n)
    <div class="notif">
      <div class="notif-ico">
        @if (str_contains($n->type, 'approved')) <x-ui-icon name="check" :size="15" />
        @elseif (str_contains($n->type, 'reject')) <x-ui-icon name="x" :size="15" />
        @elseif (str_contains($n->type, 'order')) <x-ui-icon name="box" :size="15" />
        @else <x-ui-icon name="bell" :size="15" />
        @endif
      </div>
      <div style="min-width:0;">
        <div class="notif-subject">{{ $n->subject }}</div>
        @if ($n->body)<div class="notif-body">{{ $n->body }}</div>@endif
        <div class="notif-time">{{ $n->created_at->format('M d, Y h:i A') }}</div>
      </div>
    </div>
  @empty
    <div style="text-align:center;color:#9CA3AF;padding:40px 0;">No notifications yet.</div>
  @endforelse
  <div class="pagination">{{ $notifications->links() }}</div>
</div>
@endsection