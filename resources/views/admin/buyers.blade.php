@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/buyers.css') }}">

<div class="admin-list admin-buyers" data-ui="admin-buyers">
  <header class="admin-list-head">
    <h1>Manage Buyers</h1>
    <p>Review what each buyer has purchased to understand their activity before taking action. Suspend only accounts that violate policies.</p>
  </header>

  @if(session('status'))
    <div class="admin-list-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  <nav class="admin-list-stats admin-list-stats--3" aria-label="Buyer totals">
    <a href="{{ url('/admin/buyers') }}" class="admin-list-stat {{ !request('status') ? 'is-active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">All Buyers</span><strong class="{{ $stats['all'] ? '' : 'is-zero' }}">{{ $stats['all'] }}</strong><small>total accounts</small>
    </a>
    <a href="{{ url('/admin/buyers?status=active') }}" class="admin-list-stat {{ request('status')==='active' ? 'is-active' : '' }}" {{ request('status')==='active' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Active</span><strong class="{{ $stats['active'] ? '' : 'is-zero' }}">{{ $stats['active'] }}</strong><small>can shop &amp; order</small>
    </a>
    <a href="{{ url('/admin/buyers?status=suspended') }}" class="admin-list-stat {{ request('status')==='suspended' ? 'is-active' : '' }}" {{ request('status')==='suspended' ? 'aria-current=page' : '' }}>
      <span class="admin-list-stat-label">Suspended</span><strong class="{{ $stats['suspended'] ? '' : 'is-zero' }}">{{ $stats['suspended'] }}</strong><small>restricted</small>
    </a>
  </nav>

  <section class="admin-list-toolbar" aria-label="Find buyers">
    <nav class="admin-list-filters" aria-label="Filter buyers by status">
      <a href="{{ url('/admin/buyers'.(request('search')?'?search='.request('search'):'')) }}" class="admin-list-filter {{ !request('status') ? 'is-active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>All</a>
      <a href="{{ url('/admin/buyers?status=active'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='active' ? 'is-active' : '' }}" {{ request('status')==='active' ? 'aria-current=page' : '' }}>Active</a>
      <a href="{{ url('/admin/buyers?status=suspended'.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter {{ request('status')==='suspended' ? 'is-active' : '' }}" {{ request('status')==='suspended' ? 'aria-current=page' : '' }}>Suspended</a>
    </nav>

    <form class="admin-list-search" method="GET" role="search">
      @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
      <div class="admin-list-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." aria-label="Search by name or email">
        <button type="submit">Search</button>
      </div>
      @if(request('search') || request('status'))<a class="admin-list-clear" href="{{ url('/admin/buyers') }}">Clear filters</a>@endif
    </form>
  </section>

  <section class="admin-list-panel" aria-label="Buyer records">
    <div class="admin-list-table-wrap">
      <table class="admin-list-table buyer-table" role="table">
        <colgroup>
          <col class="buyer-col"><col class="email-col"><col class="purchase-col"><col class="status-col"><col class="registered-col"><col class="actions-col">
        </colgroup>
        <thead role="rowgroup">
          <tr role="row">
            <th scope="col" role="columnheader">Buyer</th>
            <th scope="col" role="columnheader">Email</th>
            <th scope="col" role="columnheader">Purchases</th>
            <th scope="col" role="columnheader">Status</th>
            <th scope="col" role="columnheader">Registered</th>
            <th scope="col" role="columnheader">Actions</th>
          </tr>
        </thead>
        <tbody role="rowgroup">
          @forelse($buyers as $buyer)
            @php
              $isActive = ($buyer->account_status ?? 'active') === 'active';
              $orderCount = $buyer->orders_count ?? $buyer->orders->count();
              $totalSpent = $buyer->total_spent ?? $buyer->orders->sum('total');
              $productNames = $buyer->orders->flatMap(function($o){ return $o->items->map(fn($it)=>$it->product->name ?? null); })->filter()->unique()->values();
              $lastOrder = $buyer->orders->sortByDesc('created_at')->first();
            @endphp
            <tr class="list-record" role="row">
              <td class="buyer-column" data-label="Buyer" role="cell">
                <div class="buyer-cell">
                  <div class="buyer-avatar {{ $isActive ? '' : 'suspended' }}" aria-hidden="true">{{ strtoupper(mb_substr($buyer->name,0,1)) }}</div>
                  <div class="buyer-copy">
                    <div class="buyer-name">{{ $buyer->name }}</div>
                    <div class="buyer-joined">Joined {{ $buyer->created_at->diffForHumans(null,true) }} ago</div>
                  </div>
                </div>
              </td>
              <td class="email-column" data-label="Email" role="cell">
                <div class="buyer-email" title="{{ $buyer->email }}">{{ $buyer->email }}</div>
              </td>
              <td class="purchase-column" data-label="Purchases" role="cell">
                <div class="purchase-cell">
                  @if($orderCount > 0)
                    <div class="purchase-top">
                      <span class="purchase-count">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg>
                        {{ $orderCount }} order{{ $orderCount>1?'s':'' }}
                      </span>
                      <span class="purchase-total">&#8369;{{ number_format($totalSpent, 0) }}</span>
                    </div>
                    @if($productNames->count())
                      <div class="purchase-products" title="{{ $productNames->join(', ') }}">{{ $productNames->join(', ') }}</div>
                    @endif
                    @if($lastOrder)
                      <div class="purchase-meta">Last: {{ $lastOrder->created_at->format('M d') }} &#183; {{ ucfirst(str_replace('_', ' ', $lastOrder->status)) }} &#183; {{ $lastOrder->order_number }}</div>
                    @endif
                  @else
                    <div class="no-purchase">No purchases yet</div>
                    <div class="purchase-meta">No orders placed</div>
                  @endif
                </div>
              </td>
              <td class="status-column" data-label="Status" role="cell">
                @if($isActive)
                  <span class="admin-list-status admin-list-status--active">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>Active
                  </span>
                @else
                  <span class="admin-list-status admin-list-status--brick">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3v10M10 3v10"/></svg>Suspended
                  </span>
                @endif
              </td>
              <td class="registered-column" data-label="Registered" role="cell">
                <div class="buyer-date">{{ $buyer->created_at->format('M d, Y') }}</div>
                <div class="buyer-time">{{ $buyer->created_at->format('h:i A') }}</div>
              </td>
              <td class="actions-column list-no-label" data-label="Actions" role="cell">
                <div class="buyer-actions">
                  @if($isActive)
                    <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}" data-confirm="This buyer will be suspended and will not be able to log in or place orders. Continue?" data-confirm-title="Suspend Buyer?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                      @csrf
                      <input type="hidden" name="action" value="suspend" />
                      <button class="admin-list-action admin-list-action--quiet" type="submit" aria-label="Suspend {{ $buyer->name }}">
                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3v10M10 3v10"/></svg>Suspend
                      </button>
                    </form>
                  @else
                    <form method="POST" action="{{ url('/admin/manage-accounts/'.$buyer->id.'/action') }}">
                      @csrf
                      <input type="hidden" name="action" value="activate" />
                      <button class="admin-list-action" type="submit" aria-label="Activate {{ $buyer->name }}">
                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>Activate
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr role="row"><td class="list-empty" colspan="6" role="cell">No buyers found. Try adjusting your search or filters.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <div class="admin-list-pagination">
    @include('admin.partials.pager', ['paginator' => $buyers])
  </div>
</div>
@endsection
