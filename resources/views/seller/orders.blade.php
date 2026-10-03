@extends('seller.layout')

@section('content')
<style>
  .panel{background:#fff;border:1px solid #E5E5E5;border-radius:16px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
  table{width:100%;border-collapse:collapse}th{text-align:left;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#9CA3AF;padding:0 0 10px;border-bottom:1px solid #EEEFF1}td{padding:12px 0;border-bottom:1px solid #F1F2F4;font-size:14px;vertical-align:top}tr:last-child td{border-bottom:none}
  .pill{display:inline-block;font-size:11px;font-weight:700;padding:4px 12px;border-radius:999px;background:#EAF4F3;color:#0E4A57}
  .btn{background:#116B78;color:#fff;border:none;border-radius:10px;padding:8px 14px;font-weight:600;font-size:13px;cursor:pointer}
  select,input{border:1px solid #E5E5E5;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit}
  .msg{background:#E6F5EE;color:#1E7A43;border:1px solid #BFE3D0;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:14px}
</style>
@if(session('status'))<div class="msg">{{ session('status') }}</div>@endif
<div class="panel">
  <h2 style="margin:0 0 4px">Orders</h2>
  <p style="color:#6B7280;font-size:13px;margin:0 0 16px">Same orders buyers see — update the status to keep buyer, seller and admin in sync.</p>
  <form method="GET" style="margin-bottom:16px">
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      @foreach(['pending','processing','shipped','delivered','cancelled'] as $s)
        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
  </form>
  <div style="overflow-x:auto">
  <table>
    <thead><tr><th>Order</th><th>Buyer</th><th>Items / Total</th><th>Status</th><th>Update</th></tr></thead>
    <tbody>
    @forelse($orders as $o)
      <tr>
        <td><b>#{{ $o->order_number ?? $o->id }}</b><br><span style="color:#9CA3AF;font-size:12px">{{ $o->created_at }}</span></td>
        <td>{{ $o->buyer?->displayName() ?? 'Buyer #'.$o->buyer_id }}</td>
        <td>
          @foreach($o->items as $it)
            <div>{{ $it->product_name ?? $it->product?->name }} × {{ $it->quantity }} — ₱{{ number_format($it->line_total ?? (($it->unit_price ?? $it->price ?? 0) * $it->quantity), 2) }}</div>
          @endforeach
          <b>₱{{ number_format($o->effective_total ?? ($o->total ?? $o->total_amount ?? 0), 2) }}</b>
        </td>
        <td><span class="pill">{{ ucfirst($o->status) }}</span></td>
        <td>
          <form method="POST" action="{{ route('seller.orders.status', $o) }}" style="display:flex;gap:6px">
            @csrf
            <select name="status">
              @foreach(['pending','processing','shipped','delivered','cancelled'] as $s)
                <option value="{{ $s }}" @selected($o->status===$s)>{{ ucfirst($s) }}</option>
              @endforeach
            </select>
            <button class="btn">Save</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="5" style="text-align:center;color:#9CA3AF">No orders yet — buyer checkouts will appear here.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  <div style="margin-top:12px">{{ $orders->links() }}</div>
</div>
@endsection
