@extends('layouts.website')
@section('content')
<h2 style="font-weight:800">Notifications</h2>
<div style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px;margin-top:12px">
  <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--accent);border-radius:10px">
    <div style="width:36px;height:36px;background:var(--primary);border-radius:50%;display:grid;place-items:center;color:#fff">🔔</div>
    <div>
      <div style="font-weight:700">No new notifications</div>
      <div style="font-size:11px;color:var(--text-secondary)">Your order updates and messages will appear here.</div>
    </div>
  </div>
  <div style="margin-top:12px;padding:12px;border:1px dashed var(--border);border-radius:10px;text-align:center;color:var(--text-secondary);font-size:12px">
    Demo: notifications are stored in <code>invoizdb.notifications</code> and fetched via <code>GET /api/notifications</code> when logged in as buyer.
  </div>
</div>
@endsection
