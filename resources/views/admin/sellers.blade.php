@extends('admin.layout')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/sellers.css') }}">

<main class="manage-sellers" data-ui="admin-sellers">
  <header class="seller-page-head">
    <h1>Manage Sellers</h1>
    <p>Review seller applications, monitor compliance, and manage store accounts. Suspend sellers that violate category or platform policies.</p>
  </header>

  @if(session('status'))
    <div class="seller-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  <nav class="seller-stats" aria-label="Seller totals">
    <a href="{{ url('/admin/sellers') }}" class="stat-card {{ !request('status') ? 'active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>
      <span>All Sellers</span><strong class="{{ $stats['all'] ? '' : 'is-zero' }}">{{ $stats['all'] }}</strong><small>total</small>
    </a>
    <a href="{{ url('/admin/sellers?status=pending') }}" class="stat-card {{ request('status')==='pending' ? 'active' : '' }}" {{ request('status')==='pending' ? 'aria-current=page' : '' }}>
      <span>Pending</span><strong class="{{ $stats['pending'] ? '' : 'is-zero' }}">{{ $stats['pending'] }}</strong><small>awaiting review</small>
    </a>
    <a href="{{ url('/admin/sellers?status=approved') }}" class="stat-card {{ request('status')==='approved' ? 'active' : '' }}" {{ request('status')==='approved' ? 'aria-current=page' : '' }}>
      <span>Approved</span><strong class="{{ $stats['approved'] ? '' : 'is-zero' }}">{{ $stats['approved'] }}</strong><small>active stores</small>
    </a>
    <a href="{{ url('/admin/sellers?status=suspended') }}" class="stat-card {{ request('status')==='suspended' ? 'active' : '' }}" {{ request('status')==='suspended' ? 'aria-current=page' : '' }}>
      <span>Suspended</span><strong class="{{ $stats['suspended'] ? '' : 'is-zero' }}">{{ $stats['suspended'] }}</strong><small>compliance</small>
    </a>
    <a href="{{ url('/admin/sellers?status=rejected') }}" class="stat-card {{ request('status')==='rejected' ? 'active' : '' }}" {{ request('status')==='rejected' ? 'aria-current=page' : '' }}>
      <span>Rejected</span><strong class="{{ $stats['rejected'] ? '' : 'is-zero' }}">{{ $stats['rejected'] }}</strong><small>declined</small>
    </a>
  </nav>

  <section class="seller-controls" aria-label="Find sellers">
    <nav class="filter-bar" aria-label="Filter sellers by status">
      <a href="{{ url('/admin/sellers'.(request('search')?'?search='.request('search'):'')) }}" class="filter-pill {{ !request('status') ? 'active' : '' }}" {{ !request('status') ? 'aria-current=page' : '' }}>All</a>
      <a href="{{ url('/admin/sellers?status=pending'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='pending' ? 'active' : '' }}" {{ request('status')==='pending' ? 'aria-current=page' : '' }}>Pending</a>
      <a href="{{ url('/admin/sellers?status=approved'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='approved' ? 'active' : '' }}" {{ request('status')==='approved' ? 'aria-current=page' : '' }}>Approved</a>
      <a href="{{ url('/admin/sellers?status=suspended'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='suspended' ? 'active' : '' }}" {{ request('status')==='suspended' ? 'aria-current=page' : '' }}>Suspended</a>
      <a href="{{ url('/admin/sellers?status=rejected'.(request('search')?'&search='.request('search'):'')) }}" class="filter-pill {{ request('status')==='rejected' ? 'active' : '' }}" {{ request('status')==='rejected' ? 'aria-current=page' : '' }}>Rejected</a>
    </nav>

    <form class="seller-search" method="GET" role="search">
      @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
      <div class="seller-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by store, owner or email..." aria-label="Search by store, owner or email">
        <button type="submit">Search</button>
      </div>
      @if(request('search') || request('status'))<a class="clear-filters" href="{{ url('/admin/sellers') }}">Clear filters</a>@endif
    </form>
  </section>

  <section class="seller-table-panel" aria-label="Seller records">
    <div class="seller-table-wrap">
      <table class="seller-table">
        <colgroup>
          <col style="width:16%"><col style="width:17%"><col style="width:12%"><col style="width:15%"><col style="width:13%"><col style="width:11%"><col style="width:16%">
        </colgroup>
        <thead>
          <tr>
            <th scope="col">Store</th>
            <th scope="col">Owner &amp; Contact</th>
            <th scope="col">Type &amp; Category</th>
            <th scope="col">TIN &amp; Location</th>
            <th scope="col">Documents</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sellers as $seller)
            <tr class="seller-row">
              <td class="store-column">
                <div class="store-cell">
                  <div class="store-avatar">
                    @if($seller->logo)
                      <img src="{{ url('storage/'.ltrim($seller->logo,'/')) }}" alt="">
                    @else
                      {{ strtoupper(mb_substr($seller->display_name,0,1)) }}
                    @endif
                  </div>
                  <div class="store-copy">
                    <div class="store-name">{{ $seller->display_name }}</div>
                    <div class="seller-meta">#{{ $seller->id }} &#183; {{ $seller->created_at->format('M d, Y') }}</div>
                  </div>
                </div>
              </td>
              <td class="owner-column" data-label="Owner &amp; Contact">
                <div class="seller-owner">{{ $seller->user ? $seller->user->displayName() : '—' }}</div>
                <div class="seller-email" title="{{ $seller->user->email ?? '—' }}">{{ $seller->user->email ?? '—' }}</div>
                <div class="seller-phone">{{ $seller->user->phone ?? $seller->profile->contact_phone ?? '—' }}</div>
              </td>
              <td class="type-column" data-label="Type &amp; Category">
                <div class="seller-primary-data">{{ $seller->line_of_business ?: 'General' }}</div>
                <div class="seller-secondary-data">{{ $seller->seller_type ?: 'Sole Proprietorship' }}</div>
              </td>
              <td class="tin-column" data-label="TIN &amp; Location">
                <div class="seller-tin">{{ $seller->tin ?: '—' }}</div>
                <div class="seller-secondary-data">
                  {{ $seller->city ?: ($seller->user?->city ?? $seller->user?->municipality ?? '—') }}{{ ($seller->province ?: $seller->user?->province) ? ', ' . ($seller->province ?: $seller->user?->province) : '' }}
                </div>
              </td>
              <td class="documents-column" data-label="Documents">
                @php
                  $hasId = !empty($seller->id_image) || !empty($seller->user?->id_image);
                  $hasDti = !empty($seller->dti_certificate) || !empty($seller->business_permit);
                  $hasBir = !empty($seller->bir_2303);
                @endphp
                <div class="seller-docs">
                  <span class="seller-doc {{ $hasId ? 'is-present' : 'is-missing' }}" role="img" title="Government ID" aria-label="Government ID {{ $hasId ? 'provided' : 'missing' }}">
                    <svg viewBox="0 0 16 16" aria-hidden="true">@if($hasId)<path d="m3 8 3 3 7-7"/>@else<path d="M3 8h10"/>@endif</svg>ID
                  </span>
                  <span class="seller-doc {{ $hasDti ? 'is-present' : 'is-missing' }}" role="img" title="DTI Certificate" aria-label="DTI Certificate {{ $hasDti ? 'provided' : 'missing' }}">
                    <svg viewBox="0 0 16 16" aria-hidden="true">@if($hasDti)<path d="m3 8 3 3 7-7"/>@else<path d="M3 8h10"/>@endif</svg>DTI
                  </span>
                  <span class="seller-doc {{ $hasBir ? 'is-present' : 'is-missing' }}" role="img" title="BIR Form 2303" aria-label="BIR Form 2303 {{ $hasBir ? 'provided' : 'missing' }}">
                    <svg viewBox="0 0 16 16" aria-hidden="true">@if($hasBir)<path d="m3 8 3 3 7-7"/>@else<path d="M3 8h10"/>@endif</svg>BIR
                  </span>
                </div>
              </td>
              <td class="status-column">
                @php
                  $currentStatus = $seller->approval_status ?? $seller->status;
                  $statusTone = in_array($currentStatus, ['approved', 'pending', 'suspended', 'rejected'], true) ? $currentStatus : 'neutral';
                @endphp
                <span class="seller-status seller-status--{{ $statusTone }}">
                  @if($currentStatus === 'approved')
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>
                  @elseif($currentStatus === 'suspended')
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3v10M10 3v10"/></svg>
                  @elseif($currentStatus === 'rejected')
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8m0-8-8 8"/></svg>
                  @else
                    <span class="seller-status-dot" aria-hidden="true"></span>
                  @endif
                  {{ ucfirst($currentStatus) }}
                </span>
                @if($currentStatus === 'rejected' && $seller->rejection_reason)
                  <div class="seller-rejection-reason" title="{{ $seller->rejection_reason }}">Reason: {{ \Illuminate\Support\Str::limit($seller->rejection_reason, 70) }}</div>
                @endif
              </td>
              <td class="actions-column">
                <div class="seller-actions">
                  @if($currentStatus === 'pending')
                    <form class="seller-action-form" method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}">
                      @csrf
                      <button class="seller-action" type="submit" aria-label="Approve {{ $seller->display_name }}">Approve</button>
                    </form>
                    <a class="seller-action seller-action--quiet" href="{{ url('/admin/sellers/'.$seller->id) }}" aria-label="Review or reject {{ $seller->display_name }}">Review / Reject</a>
                  @elseif($currentStatus === 'approved')
                    <form class="seller-action-form" method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/suspend') }}" data-confirm="This seller will be suspended for compliance violation. Their store and products will be hidden. Continue?" data-confirm-title="Suspend Seller?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                      @csrf
                      <button class="seller-action seller-action--quiet" type="submit" aria-label="Suspend {{ $seller->display_name }}">Suspend</button>
                    </form>
                  @elseif($currentStatus === 'suspended')
                    <form class="seller-action-form" method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/reinstate') }}" data-confirm="This seller will be reinstated and their store will be visible again. Continue?" data-confirm-title="Reinstate Seller?" data-confirm-ok="Reinstate" data-confirm-class="btn-primary">
                      @csrf
                      <button class="seller-action" type="submit" aria-label="Reinstate {{ $seller->display_name }}">Reinstate</button>
                    </form>
                  @elseif($currentStatus === 'rejected')
                    <form class="seller-action-form" method="POST" action="{{ url('/admin/sellers/'.$seller->id.'/approve') }}" data-confirm="Approve this seller? Their account will be reactivated." data-confirm-title="Approve Seller?" data-confirm-ok="Approve" data-confirm-class="btn-primary">
                      @csrf
                      <button class="seller-action" type="submit" aria-label="Approve {{ $seller->display_name }}">Approve</button>
                    </form>
                  @endif
                  <a class="seller-action seller-action--view" href="{{ url('/admin/sellers/'.$seller->id) }}" aria-label="View {{ $seller->display_name }}">View</a>
                </div>
              </td>
            </tr>
          @empty
            <tr class="empty-table-row"><td class="empty-row" colspan="7">No sellers found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <div class="seller-pagination">
    @include('admin.partials.pager', ['paginator' => $sellers])
  </div>
</main>
@endsection
