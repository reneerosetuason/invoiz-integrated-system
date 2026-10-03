@extends('admin.layout')

@section('content')
<style>
  .buyer-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:18px; }
  .b-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; text-decoration:none; color:inherit; display:block; transition:transform .15s, box-shadow .15s; }
  .b-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .b-stat.active { background:#16697A; color:#fff; border-color:#16697A; }
  .b-stat.active span, .b-stat.active strong, .b-stat.active small { color:#fff; }
  .b-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .b-stat strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .b-stat small { font-size:11px; color:var(--text-secondary); }
  .b-stat.active small { color:rgba(255,255,255,.7); }
  .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
  .filter-pill { padding:7px 14px; border-radius:999px; font-size:12.5px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; }
  .filter-pill:hover { border-color:#16697A; color:#16697A; }
  .filter-pill.active { background:#16697A; color:#fff; border-color:#16697A; }
  .buyer-cell { display:flex; align-items:center; gap:10px; }
  .buyer-avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; border:2px solid #fff; box-shadow:0 2px 8px rgba(16,24,40,.08); }
  .buyer-avatar.suspended { background:linear-gradient(135deg,#9CA3AF 0%,#6B7280 100%); }
  .buyer-name { font-weight:700; font-size:13.5px; line-height:1.2; }
  .buyer-joined { font-size:11.5px; color:var(--text-secondary); }
  .status-dot { width:7px; height:7px; border-radius:50%; display:inline-block; margin-right:6px; vertical-align:middle; }
  .status-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; border:1px solid; }
  .status-active { background:#E8F5EE; color:#166534; border-color:#BFE3D0; }
  .status-active .status-dot { background:#2E8B57; }
  .status-suspended { background:#FEE2E2; color:#991B1B; border-color:#FECACA; }
  .status-suspended .status-dot { background:#DC2626; }
  .purchase-cell { min-width:210px; max-width:260px; }
  .purchase-top { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
  .purchase-count { display:inline-flex; align-items:center; gap:4px; background:#F0FAFA; border:1px solid #E6F1F2; color:#16697A; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
  .purchase-total { font-size:12.5px; font-weight:800; color:#1B1B1E; }
  .purchase-products { font-size:11.5px; color:#6B7280; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.4; }
  .purchase-meta { font-size:11px; color:#9CA3AF; margin-top:2px; }
  .no-purchase { font-size:12.5px; color:#9CA3AF; font-style:italic; }
  @media (max-width: 700px) { .buyer-stats { grid-template-columns:1fr; } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:4px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Buyers</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Review what each buyer has purchased to understand their activity before taking action. Suspend only accounts that violate policies.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="buyer-stats" style="margin-top:18px;">
    <a href="{{ url('/admin/buyers') }}" class="b-stat {{ !request('status') ? 'active' : '' }}">
      <span>All Buyers</span><strong>{{ $stats['all'] }}</strong><small>total accounts</small>
    </a>
    <a href="{{ url('/admin/buyers?status=active') }}" class="b-stat {{ request('status')==='active' ? 'active' : '' }}" style="border-left:3px solid #2E8B57;">
      <span>Active</span><strong style="color:#166534;">{{ $stats['active'] }}</strong><small>can shop & order</small>
    </a>
    <a href="{{ url('/admin/buyers?status=suspended') }}" class="b-stat {{ request('status')==='suspended' ? 'active' : '' }}" style="border-left:3px solid #DC2626;">
      <span>Suspended</span><strong style="color:#991B1B;">{{ $stats['suspended'] }}</strong><small>restricted</small>
    </a>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('status'))<a href="{{ url('/admin/buyers') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear filters</a>@endif
  </form>

  <div class="filter-bar">
    <a href="{{ url('/admin/buyers'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ url('/admin/buyers?status=active'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='active' ? 'active' : '' }}">Active</a>
    <a href="{{ url('/admin/buyers?status=suspended'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='suspended' ? 'active' : '' }}">Suspended</a>
  </div>

  <div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;min-width:860px;">
    <thead>
      <tr style="border-bottom:2px solid #E8E6E0;text-align:left;">
        <th style="padding:12px 10px;">Buyer</th>
        <th style="padding:12px 10px;">Email</th>
        <th style="padding:12px 10px;">Purchases</th>
        <th style="padding:12px 10px;">Status</th>
        <th style="padding:12px 10px;">Registered</th>
        <th style="padding:12px 10px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($buyers as $buyer)
        @php
          $isActive = ($buyer->account_status ?? 'active') === 'active';
          $orderCount = $buyer->orders_count ?? $buyer->orders->count();
          $totalSpent = $buyer->total_spent ?? $buyer->orders->sum('total');
          $productNames = $buyer->orders->flatMap(function($o){ return $o->items->map(fn($it)=>$it->product->name ?? null); })->filter()->unique()->values();
          $lastOrder = $buyer->orders->sortByDesc('created_at')->first();
        @endphp
      <tr style="border-bottom:1px solid #F7F6F2; transition:background .12s;" onmouseover="this.style.background='#FAFAF8'" onmouseout="this.style.background=''">
        <td style="padding:12px 10px;">
          <div class="buyer-cell">
            <div class="buyer-avatar {{ $isActive ? '' : 'suspended' }}">{{ strtoupper(mb_substr($buyer->name,0,1)) }}</div>
            <div>
              <div class="buyer-name">{{ $buyer->name }}</div>
              <div class="buyer-joined">Joined {{ $buyer->created_at->diffForHumans(null,true) }} ago</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 10px;color:#6E6E73;font-size:13px;">{{ $buyer->email }}</td>
        <td style="padding:12px 10px;">
          <div class="purchase-cell">
            @if($orderCount > 0)
              <div class="purchase-top">
                <span class="purchase-count">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg>
                  {{ $orderCount }} order{{ $orderCount>1?'s':'' }}
                </span>
                <span class="purchase-total">₱{{ number_format($totalSpent, 0) }}</span>
              </div>
              @if($productNames->count())
                <div class="purchase-products" title="{{ $productNames->join(', ') }}">
                  {{ $productNames->take(2)->join(', ') }}{{ $productNames->count() > 2 ? ' +'.($productNames->count()-2).' more' : '' }}
                </div>
              @endif
              @if($lastOrder)
                <div class="purchase-meta">Last: {{ $lastOrder->created_at->format('M d') }} · {{ ucfirst($lastOrder->status) }} · {{ $lastOrder->order_number }}</div>
              @endif
            @else
              <span class="no-purchase">No purchases yet</span>
              <div class="purchase-meta">No orders placed</div>
            @endif
          </div>
        </td>
        <td style="padding:12px 10px;">
          @if($isActive)
            <span class="status-badge status-active"><span class="status-dot"></span> Active</span>
          @else
            <span class="status-badge status-suspended"><span class="status-dot"></span> Suspended</span>
          @endif
        </td>
        <td style="padding:12px 10px;">
          <div style="font-size:13px; color:#374151; font-weight:600;">{{ $buyer->created_at->format('M d, Y') }}</div>
          <div style="font-size:11.5px; color:#9CA3AF;">{{ $buyer->created_at->format('h:i A') }}</div>
        </td>
        <td style="padding:12px 10px;">
          @if($isActive)
            <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}" style="display:inline;" data-confirm="This buyer will be suspended and will not be able to log in or place orders. Continue?" data-confirm-title="Suspend Buyer?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
              @csrf
              <input type="hidden" name="action" value="suspend" />
              <button type="submit" style="padding:6px 14px;background:#F0A202;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;box-shadow:0 1px 2px rgba(0,0,0,.06);">Suspend</button>
            </form>
          @else
            <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}" style="display:inline;">
              @csrf
              <input type="hidden" name="action" value="activate" />
              <button type="submit" style="padding:6px 14px;background:#2E8B57;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:700;box-shadow:0 1px 2px rgba(0,0,0,.06);">Activate</button>
            </form>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="padding:36px;text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
        </div>
        <div style="font-weight:600; color:#6B7280;">No buyers found</div>
        <div style="font-size:13px; color:#9CA3AF; margin-top:4px;">Try adjusting your search or filters.</div>
      </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
