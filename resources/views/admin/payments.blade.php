@extends('admin.layout')

@section('content')
<div class="card">
    <h1 class="page-title">Payments</h1>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:8px;">
        <div class="stat-card" style="border-left:4px solid #16697A;">
            <span>All Transactions</span>
            <strong>{{ $stats['all'] }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">₱{{ number_format($stats['all_amount'], 2) }} volume</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #2E8B57;">
            <span>Successful</span>
            <strong style="color:#166534;">{{ $stats['successful'] }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">₱{{ number_format($stats['successful_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #F0A202;">
            <span>Pending</span>
            <strong style="color:#92400e;">{{ $stats['pending'] }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">₱{{ number_format($stats['pending_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #E05A33;">
            <span>Failed</span>
            <strong style="color:#991b1b;">{{ $stats['failed'] }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">₱{{ number_format($stats['failed_amount'], 2) }}</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #8B5CF6;">
            <span>Refunded</span>
            <strong>{{ $stats['refunded'] }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">₱{{ number_format($stats['refunded_amount'], 2) }} returned</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #B5AACB;">
            <span>Partially Refunded</span>
            <strong>{{ $stats['partially_refunded'] }}</strong>
        </div>
        <div class="stat-card" style="border-left:4px solid #0E4A57;">
            <span>Admin Earnings</span>
            <strong style="color:#0E4A57;">₱{{ number_format($stats['admin_earnings'], 2) }}</strong>
            <div style="font-size:12px;color:#6E6E73;margin-top:4px;">Commission + fees</div>
        </div>
    </div>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:24px;">
        <div style="background:#F0EEE9;border-radius:12px;padding:14px 18px;">
            <div style="font-size:12px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.06em;">Platform Commission</div>
            <div style="font-size:20px;font-weight:800;color:#16697A;">₱{{ number_format($stats['commission'], 2) }}</div>
        </div>
        <div style="background:#F0EEE9;border-radius:12px;padding:14px 18px;">
            <div style="font-size:12px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.06em;">Seller Earnings</div>
            <div style="font-size:20px;font-weight:800;color:#2E8B57;">₱{{ number_format($stats['seller_earnings'], 2) }}</div>
        </div>
        <div style="background:#F0EEE9;border-radius:12px;padding:14px 18px;">
            <div style="font-size:12px;font-weight:700;color:#6E6E73;text-transform:uppercase;letter-spacing:.06em;">Rider Fees</div>
            <div style="font-size:20px;font-weight:800;color:#92400e;">₱{{ number_format($stats['rider_fees'], 2) }}</div>
        </div>
    </div>

    <div style="background:#EAF4F3;border:1px solid #CBE3E1;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#0E4A57;">
        <strong>Money flow:</strong> Order → Payment → Seller Earnings → Platform Commission → Rider Fee → Refund. Every transaction below shows the complete breakdown.
    </div>

    <h2 id="transactions" style="font-size:17px;margin-bottom:12px;">All Transactions</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
        @foreach (['' => 'All', 'successful' => 'Successful', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partial'] as $key => $label)
        <a href="{{ $key ? url('/admin/payments?status='.$key) : url('/admin/payments') }}" data-filter="{{ $key }}" class="tx-filter" style="padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;text-decoration:none;{{ ($status ?? '') === $key ? 'background:#16697A;color:#fff;' : 'background:#F0EEE9;color:#374151;' }}">{{ $label }}</a>
        @endforeach
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:28px;">
        <thead>
            <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
                <th style="padding:11px 8px;">Ref</th>
                <th style="padding:11px 8px;">Order</th>
                <th style="padding:11px 8px;">Store</th>
                <th style="padding:11px 8px;">Gateway</th>
                <th style="padding:11px 8px;">Amount</th>
                <th style="padding:11px 8px;">Seller Earning</th>
                <th style="padding:11px 8px;">Commission</th>
                <th style="padding:11px 8px;">Rider Fee</th>
                <th style="padding:11px 8px;">Refunded</th>
                <th style="padding:11px 8px;">Status</th>
            </tr>
        </thead>
        <tbody id="txBody">
            @forelse($transactions as $tx)
            <tr data-status="{{ $tx->status }}" style="border-bottom:1px solid #F7F6F2;">
                <td style="padding:12px 8px;font-family:monospace;font-size:12.5px;">{{ $tx->transaction_ref }}</td>
                <td style="padding:12px 8px;"><a href="{{ url('/admin/orders/'.$tx->order_id) }}" style="color:#16697A;text-decoration:none;font-weight:600;">{{ $tx->order->order_number ?? '-' }}</a></td>
                <td style="padding:12px 8px;">{{ $tx->order->seller->store_name ?? 'N/A' }}</td>
                <td style="padding:12px 8px;color:#6E6E73;">{{ $tx->gateway }}</td>
                <td style="padding:12px 8px;font-weight:600;">₱{{ number_format($tx->amount, 2) }}</td>
                <td style="padding:12px 8px;color:#2E8B57;">₱{{ number_format($tx->seller_earning, 2) }}</td>
                <td style="padding:12px 8px;color:#16697A;">₱{{ number_format($tx->platform_commission, 2) }}</td>
                <td style="padding:12px 8px;color:#92400e;">₱{{ number_format($tx->rider_fee, 2) }}</td>
                <td style="padding:12px 8px;color:#B91C1C;">{{ $tx->refunded_amount > 0 ? '₱'.number_format($tx->refunded_amount, 2) : '—' }}</td>
                <td style="padding:12px 8px;">
                    @php $colors = ['successful' => ['#E8F5EE','#166534'], 'pending' => ['#FDF3E3','#92400e'], 'failed' => ['#FCE9E4','#991b1b'], 'refunded' => ['#F3E8FF','#6b21a8'], 'partially_refunded' => ['#F3E8FF','#6b21a8']]; @endphp
                    <span style="padding:3px 10px;border-radius:12px;font-size:12px;background:{{ $colors[$tx->status][0] }};color:{{ $colors[$tx->status][1] }};">{{ str_replace('_', ' ', ucfirst($tx->status)) }}</span>
                </td>
            </tr>
            @empty
            <tr><td colspan="10" style="padding:20px;text-align:center;color:#6E6E73;">No transactions found.</td></tr>
            @endforelse
            <tr id="txEmpty" style="display:none;"><td colspan="10" style="padding:20px;text-align:center;color:#6E6E73;">No transactions for this status.</td></tr>
        </tbody>
    </table>
    @include('admin.partials.pager', ['paginator' => $transactions])

</div>
<script>
(function(){
  const pills = document.querySelectorAll('.tx-filter');
  const rows = document.querySelectorAll('#txBody tr[data-status]');
  const emptyRow = document.getElementById('txEmpty');
  const title = document.getElementById('transactions');
  const urlParams = new URLSearchParams(window.location.search);
  let current = urlParams.get('status') || '';
  function applyFilter(status, push){
    pills.forEach(p=>{
      const f = p.getAttribute('data-filter');
      const active = f === status;
      p.style.background = active ? '#16697A' : '#F0EEE9';
      p.style.color = active ? '#fff' : '#374151';
    });
    if(title){
      const labels = {'':'All Transactions','successful':'Successful Transactions','pending':'Pending Transactions','failed':'Failed Transactions','refunded':'Refunded Transactions','partially_refunded':'Partial Transactions'};
      title.textContent = labels[status] || 'All Transactions';
    }
    let visible = 0;
    rows.forEach(r=>{
      const show = !status || r.getAttribute('data-status') === status;
      r.style.display = show ? '' : 'none';
      if(show) visible++;
    });
    if(emptyRow) emptyRow.style.display = visible===0 && rows.length>0 ? '' : 'none';
    const url = status ? '/admin/payments?status='+status : '/admin/payments';
    if(push) history.replaceState({}, '', url);
  }
  if(current) applyFilter(current, false);
  pills.forEach(p=>{
    p.addEventListener('click', function(e){
      e.preventDefault();
      const status = p.getAttribute('data-filter');
      const y = window.scrollY;
      applyFilter(status, true);
      window.scrollTo(0, y);
    });
  });
})();
</script>
@endsection
