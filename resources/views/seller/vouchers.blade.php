@extends('seller.layout')

@section('content')
<style>
  .grid-2 { display: grid; grid-template-columns: 1fr 1.6fr; gap: 16px; }
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; letter-spacing: -.3px; }
  label { display: block; font-size: 12px; font-weight: 700; letter-spacing: 1px; color: #374151; margin-bottom: 6px; text-transform: uppercase; }
  input, select { width: 100%; background: #fff; border: 1px solid #D8DBDF; border-radius: 10px; padding: 11px 14px; font-size: 14px; font-family: inherit; margin-bottom: 14px; outline: none; }
  input:focus, select:focus { border-color: #116B78; box-shadow: 0 0 0 3px rgba(17,107,120,.12); }
  .btn { background: #116B78; color: #fff; border: none; border-radius: 10px; padding: 12px 20px; font-weight: 700; font-size: 14px; cursor: pointer; transition: transform .18s ease; }
  .btn:hover { background: #0C555F; transform: scaleX(1.06); }
  .status-msg { background: #E6F5EE; color: #1E7A43; border: 1px solid #BFE3D0; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
  .voucher { display: flex; align-items: center; justify-content: space-between; border: 1px solid #EEEFF1; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; }
  .v-code { font-weight: 800; font-size: 15px; color: #116B78; letter-spacing: .5px; }
  .v-meta { font-size: 12px; color: #6B7280; margin-top: 3px; }
  .v-actions { display: flex; gap: 8px; align-items: center; }
  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
  .pill-on { background: #E6F5EE; color: #1E7A43; }
  .pill-off { background: #F3F4F6; color: #6B7280; }
  .btn-sm { font-size: 12px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: transform .18s ease; }
  .btn-sm:hover { transform: scaleX(1.06); }
  .btn-outline { background: transparent; border: 1px solid #D8DBDF; color: #374151; }
  .btn-outline:hover { background: #F3F4F6; transform: scaleX(1.06); }
  .btn-danger { background: transparent; border: 1px solid #F5C6C6; color: #B3261E; }
  .btn-danger:hover { background: #FDECEC; transform: scaleX(1.06); }
  .empty { text-align: center; color: #9CA3AF; padding: 30px 0; }
  .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
</style>

@if(session('status'))
  <div class="status-msg">{{ session('status') }}</div>
@endif

<div class="grid-2">
  <div class="panel">
    <h3 class="panel-title">Create Voucher</h3>
    <form method="POST" action="{{ url('/seller/vouchers') }}">
      @csrf
      <label>Voucher Code</label>
      <input name="code" placeholder="e.g. SUMMER20" required />
      <div class="row-2">
        <div>
          <label>Type</label>
          <select name="type">
            <option value="fixed">Fixed (₱ off)</option>
            <option value="percent">Percent (% off)</option>
          </select>
        </div>
        <div>
          <label>Value</label>
          <input name="value" type="number" step="0.01" min="0.01" required />
        </div>
      </div>
      <label>Min. Spend (₱)</label>
      <input name="min_spend" type="number" step="0.01" min="0" value="0" />
      <div class="row-2">
        <div>
          <label>Usage Limit</label>
          <input name="usage_limit" type="number" min="1" placeholder="Unlimited" />
        </div>
        <div>
          <label>Starts</label>
          <input name="starts_at" type="date" />
        </div>
      </div>
      <label>Ends</label>
      <input name="ends_at" type="date" />
      <button class="btn" type="submit">Create Voucher</button>
    </form>
  </div>

  <div class="panel">
    <h3 class="panel-title">Active & Past Vouchers</h3>
    @forelse ($vouchers as $v)
      <div class="voucher">
        <div>
          <div class="v-code">{{ $v->code }} · {{ $v->label }}</div>
          <div class="v-meta">
            Min spend ₱{{ number_format($v->min_spend, 2) }} ·
            Used {{ $v->used_count }}{{ $v->usage_limit ? ' / ' . $v->usage_limit : '' }} ·
            {{ $v->ends_at ? 'Ends ' . $v->ends_at->format('M d, Y') : 'No expiry' }}
          </div>
        </div>
        <div class="v-actions">
          <span class="pill {{ $v->is_active ? 'pill-on' : 'pill-off' }}">{{ $v->is_active ? 'Active' : 'Inactive' }}</span>
          <form method="POST" action="{{ url('/seller/vouchers/'.$v->id.'/toggle') }}">@csrf
            <button class="btn-sm btn-outline" type="submit">{{ $v->status ? 'Disable' : 'Enable' }}</button>
          </form>
          <form method="POST" action="{{ url('/seller/vouchers/'.$v->id.'/delete') }}" data-confirm="This voucher will be permanently deleted. Continue?" data-confirm-title="Delete Voucher?" data-confirm-ok="Delete" data-confirm-class="btn-danger">@csrf
            <button class="btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </div>
      </div>
    @empty
      <div class="empty">No vouchers yet. Create your first one.</div>
    @endforelse
  </div>
</div>
@endsection