@extends('admin.layout')

@section('content')
<style>
  .manage-sellers{--seller-deep:var(--primary-dark,#0E4A57);--seller-hair:rgba(15,76,92,.12);--seller-ink:var(--text-primary,#1B1B1E);--seller-muted:#56696B;max-width:1280px;margin:0 auto;container-type:inline-size;color:var(--seller-ink);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif}
  .manage-sellers *,.manage-sellers *::before,.manage-sellers *::after{box-sizing:border-box}
  .seller-page-head{margin:0 0 20px}
  .seller-page-head h1{margin:0;color:var(--seller-deep);font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;line-height:1.15;letter-spacing:-.02em}
  .seller-page-head p{max-width:78ch;margin:7px 0 0;color:var(--seller-muted);font-size:14px;line-height:1.55}
  .seller-flash{display:flex;align-items:flex-start;gap:9px;margin:0 0 16px;padding:11px 14px;border-radius:12px;background:#EAF3F2;color:var(--seller-deep);font-size:13px;font-weight:600;line-height:1.5}
  .seller-flash svg{width:17px;height:17px;flex:0 0 17px;margin-top:1px;fill:none;stroke:currentColor;stroke-width:1.8}
  .seller-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));margin:0 0 18px;border:1px solid var(--seller-hair);border-radius:16px;background:#fff;overflow:hidden}
  .manage-sellers .stat-card{position:relative;display:flex;min-width:0;min-height:112px;flex-direction:column;justify-content:center;padding:14px 16px;border:0;border-right:1px solid var(--seller-hair);border-radius:0;background:#fff;color:var(--seller-ink);text-align:left;text-decoration:none;transition:background-color .16s ease,transform .16s cubic-bezier(.22,1,.36,1)}
  .manage-sellers .stat-card:last-child{border-right:0}
  .manage-sellers .stat-card:hover{background:#F6F9F8}
  .manage-sellers .stat-card.active{border-radius:0;background:#EAF3F2;box-shadow:inset 0 -2px 0 var(--seller-deep)}
  .manage-sellers .stat-card span{display:block;color:var(--seller-muted);font-size:12px;font-weight:600;letter-spacing:0;text-transform:none}
  .manage-sellers .stat-card strong{display:block;margin-top:3px;color:var(--seller-deep);font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;line-height:1.1;font-variant-numeric:tabular-nums}
  .manage-sellers .stat-card strong.is-zero{color:#77888A}
  .manage-sellers .stat-card small{margin-top:3px;color:var(--seller-muted);font-size:12.5px;line-height:1.3}
  .seller-controls{display:grid;grid-template-columns:minmax(0,1fr) 360px;align-items:center;gap:16px;margin:0 0 16px}
  .manage-sellers .filter-bar{display:flex;min-width:0;align-items:center;gap:8px;margin:0}
  .manage-sellers .filter-pill{display:inline-flex;min-height:36px;align-items:center;justify-content:center;padding:7px 13px;border:1px solid var(--seller-hair);border-radius:999px;background:#fff;color:var(--seller-deep);font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap;transition:background-color .16s ease,color .16s ease,transform .16s cubic-bezier(.22,1,.36,1)}
  .manage-sellers .filter-pill:hover{background:#EDF3F1}
  .manage-sellers .filter-pill.active{border-color:var(--seller-deep);background:var(--seller-deep);color:#fff}
  .manage-sellers .seller-search{display:flex;min-width:0;align-items:center;gap:8px;margin:0}
  .seller-search-field{display:flex;min-width:0;height:44px;flex:1;align-items:center;gap:8px;padding:0 5px 0 14px;border:1px solid var(--seller-hair);border-radius:999px;background:#fff;transition:border-color .16s ease}
  .seller-search-field:focus-within{border-color:rgba(22,105,122,.55)}
  .seller-search-field>svg{width:16px;height:16px;flex:0 0 16px;color:var(--seller-muted);fill:none;stroke:currentColor;stroke-width:1.6}
  .manage-sellers .seller-search input[type=search]{width:auto;min-width:0;flex:1;padding:0;border:0;border-radius:0;outline:0;background:transparent;color:var(--seller-ink);font-family:inherit;font-size:13px;box-shadow:none}
  .manage-sellers .seller-search input[type=search]:focus{background:transparent;box-shadow:none}
  .manage-sellers .seller-search input[type=search]::-webkit-search-cancel-button{-webkit-appearance:none}
  .manage-sellers .seller-search button{display:inline-flex;min-height:36px;align-items:center;justify-content:center;padding:7px 15px;border:0;border-radius:999px;background:var(--seller-deep);color:#fff;font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer;transition:transform .16s cubic-bezier(.22,1,.36,1),background-color .16s ease}
  .manage-sellers .seller-search button:hover{background:#16697A}
  .manage-sellers .clear-filters{flex:0 0 auto;padding:8px 2px;color:var(--seller-muted);font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap}
  .manage-sellers .clear-filters:hover{text-decoration:underline;text-underline-offset:3px}
  .seller-table-panel{min-width:0;border:1px solid var(--seller-hair);border-radius:16px;background:#fff;overflow:hidden}
  .seller-table-wrap{width:100%;min-width:0;overflow:visible}
  .manage-sellers .seller-table{width:100%;min-width:0;border-collapse:collapse;table-layout:fixed}
  .manage-sellers .seller-table th{padding:12px 8px;border-bottom:1px solid var(--seller-hair);color:var(--seller-muted);font-size:11.5px;font-weight:650;letter-spacing:0;text-align:left;text-transform:none;vertical-align:bottom}
  .manage-sellers .seller-table td{min-width:0;padding:14px 8px;border-bottom:1px solid var(--seller-hair);font-size:13px;line-height:1.4;vertical-align:middle;overflow-wrap:anywhere}
  .manage-sellers .seller-table th:first-child,.manage-sellers .seller-table td:first-child{padding-left:16px}
  .manage-sellers .seller-table th:last-child,.manage-sellers .seller-table td:last-child{padding-right:16px}
  .manage-sellers .seller-row{transition:background-color .16s ease}
  .manage-sellers .seller-row:hover td{background:#F7FAF9}
  .manage-sellers .seller-row:last-child td{border-bottom:0}
  .manage-sellers .store-cell{display:flex;min-width:0;align-items:center;gap:10px}
  .manage-sellers .store-avatar{display:flex;width:40px;height:40px;flex:0 0 40px;align-items:center;justify-content:center;overflow:hidden;border:0;border-radius:10px;background:#EAF3F2;color:var(--seller-deep);font-size:14px;font-weight:700}
  .manage-sellers .store-avatar img{width:100%;height:100%;object-fit:cover}
  .manage-sellers .store-copy{min-width:0}
  .manage-sellers .store-name{display:-webkit-box;overflow:hidden;color:var(--seller-ink);font-size:15px;font-weight:550;line-height:1.3;overflow-wrap:anywhere;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .seller-meta{margin-top:3px;color:var(--seller-muted);font-size:12.5px;line-height:1.35;font-variant-numeric:tabular-nums}
  .seller-owner{color:var(--seller-ink);font-size:14px;font-weight:550;line-height:1.35}
  .seller-email{margin-top:3px;color:var(--seller-muted);font-size:13px;overflow-wrap:anywhere}
  .seller-phone{display:inline-block;margin-top:3px;color:var(--seller-deep);font-size:13px;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
  .seller-primary-data{color:var(--seller-ink);font-size:14px;font-weight:550;line-height:1.35;overflow-wrap:anywhere}
  .seller-secondary-data{margin-top:3px;color:var(--seller-muted);font-size:13px;line-height:1.4;overflow-wrap:anywhere}
  .seller-tin{font-size:13.5px;font-weight:550;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
  .seller-docs{display:flex;flex-wrap:wrap;gap:5px}
  .seller-doc{display:inline-flex;min-height:24px;align-items:center;gap:4px;padding:3px 6px;border:1px solid var(--seller-hair);border-radius:7px;font-size:10.5px;font-weight:650;line-height:1.2;white-space:nowrap}
  .seller-doc svg,.seller-status svg{width:12px;height:12px;flex:0 0 12px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .seller-doc.is-present{background:#EAF3F2;color:var(--seller-deep)}
  .seller-doc.is-missing{border-style:dashed;background:#F5F3EF;color:#5F6E70}
  .seller-status{display:inline-flex;max-width:100%;min-height:28px;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;font-size:11.5px;font-weight:650;line-height:1.25;white-space:nowrap}
  .seller-status--approved{background:#EAF3F2;color:var(--seller-deep)}
  .seller-status--pending,.seller-status--neutral{background:#F3F0E9;color:#43575A}
  .seller-status--suspended,.seller-status--rejected{background:#F3F0E9;color:#8F5048}
  .seller-status-dot{width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:currentColor}
  .seller-rejection-reason{max-width:180px;margin-top:5px;color:#8F5048;font-size:12px;line-height:1.35;overflow-wrap:anywhere}
  .seller-actions{display:flex;min-width:0;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:7px}
  .seller-action-form{display:flex;margin:0}
  .manage-sellers .seller-action{display:inline-flex;min-width:0;min-height:36px;align-items:center;justify-content:center;gap:6px;padding:6px 9px;border:1px solid var(--seller-hair);border-radius:9px;background:#fff;color:var(--seller-deep);font-family:inherit;font-size:12px;font-weight:650;line-height:1.2;text-align:center;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .15s ease,color .15s ease,border-color .15s ease,transform .15s cubic-bezier(.22,1,.36,1)}
  .manage-sellers .seller-action:hover{background:#EDF3F1}
  .manage-sellers .seller-action--view{color:#536366}
  .manage-sellers .seller-action--quiet{border-color:transparent;background:transparent;color:#536366}
  .manage-sellers .seller-action--quiet:hover,.manage-sellers .seller-action--quiet:focus-visible{color:#8F5048}
  .seller-action svg{width:13px;height:13px;flex:0 0 13px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .manage-sellers .seller-action:active,.manage-sellers .filter-pill:active,.manage-sellers .seller-search button:active{transform:scale(.98)}
  .manage-sellers a:focus-visible,.manage-sellers button:focus-visible,.manage-sellers input:focus-visible{outline:2px solid var(--seller-deep);outline-offset:2px}
  .seller-empty{padding:28px 16px!important;color:var(--seller-muted);font-size:13px!important;text-align:center}
  .seller-pagination{margin-top:12px}
  .seller-pagination>div{justify-content:flex-end!important;gap:7px!important;padding:0!important}
  .seller-pagination>div>a[rel=prev],.seller-pagination>div>a[rel=next],.seller-pagination>div>span[style*="cursor:default"]{display:inline-flex!important;min-width:36px!important;height:36px!important;align-items:center!important;justify-content:center!important;padding:0 11px!important;border:1px solid var(--seller-hair)!important;border-radius:9px!important;background:#fff!important;color:var(--seller-deep)!important;font-size:12.5px!important;font-weight:600!important;text-decoration:none!important;white-space:nowrap}
  .seller-pagination>div>a:not([rel]){display:inline-flex!important;width:36px!important;min-width:36px!important;height:36px!important;align-items:center!important;justify-content:center!important;padding:0!important;border:1px solid var(--seller-hair)!important;border-radius:50%!important;background:#fff!important;color:var(--seller-deep)!important;font-size:12.5px!important;text-decoration:none!important}
  .seller-pagination>div>a:first-child,.seller-pagination>div>a:nth-last-child(2),.seller-pagination>div>span[style*="cursor:default"]{width:auto!important;min-width:66px!important;border-radius:9px!important}
  .seller-pagination>div>span[style*="background:#16697A"]{display:inline-flex!important;width:36px!important;min-width:36px!important;height:36px!important;align-items:center!important;justify-content:center!important;padding:0!important;border:1px solid var(--seller-deep)!important;border-radius:50%!important;background:var(--seller-deep)!important;color:#fff!important;font-size:12.5px!important}
  .seller-pagination>div>span[style*="color:#9CA3AF"]{padding:0 3px!important;color:var(--seller-muted)!important}
  .seller-pagination>div>span:last-child{flex:0 0 auto;margin-left:4px!important;color:var(--seller-muted)!important;font-size:13px!important;white-space:nowrap}
  .manage-sellers .clear-filters{position:relative}
  .manage-sellers .seller-table a:not(.seller-action){color:inherit}
  body:has(.manage-sellers){--seller-deep:#0E4A57;--seller-hair:rgba(15,76,92,.12);--seller-muted:#56696B}
  body:has(.manage-sellers) .confirm-overlay{background:rgba(14,74,87,.3);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)}
  body:has(.manage-sellers) .confirm-card{border:1px solid var(--seller-hair);border-radius:20px;background:#fff;box-shadow:0 18px 48px rgba(14,74,87,.16);text-align:left}
  body:has(.manage-sellers) .confirm-icon{width:44px;height:44px;margin:0 0 14px;border-radius:12px;background:#F3F0E9;color:var(--seller-deep)}
  body:has(.manage-sellers) .confirm-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.6}
  body:has(.manage-sellers) .confirm-icon.suspend{background:#F3F0E9;color:#8F5048}
  body:has(.manage-sellers) .confirm-icon.suspend svg{fill:none;stroke:currentColor}
  body:has(.manage-sellers) .confirm-card h3{color:var(--seller-deep);font-family:'Fraunces',Georgia,serif;font-size:21px;font-weight:560;letter-spacing:-.02em}
  body:has(.manage-sellers) .confirm-card p{color:var(--seller-muted)}
  body:has(.manage-sellers) .confirm-actions{justify-content:flex-end}
  body:has(.manage-sellers) .confirm-actions .btn{min-height:40px;border-radius:10px;box-shadow:none}
  body:has(.manage-sellers) .confirm-actions .btn-danger{border:0;background:#B4655A;color:#fff}
  body:has(.manage-sellers) .confirm-actions .btn-danger:hover{background:#994F47}
  @media(max-width:1279px){.seller-controls{grid-template-columns:minmax(0,1fr);gap:10px}.manage-sellers .seller-search{max-width:440px}}
  @container (max-width:1099.98px){.manage-sellers .seller-table,.manage-sellers .seller-table tbody{display:block;width:100%}.manage-sellers .seller-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}.manage-sellers .seller-table tbody{display:grid;gap:10px;padding:10px}.manage-sellers .seller-table tr.seller-row{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"store status" "owner type" "tin documents" "actions actions";align-items:start;column-gap:12px;row-gap:0;margin:0;padding:10px 12px;border:1px solid var(--seller-hair);border-radius:12px;background:#fff}.manage-sellers .seller-table td{display:block;min-width:0;height:auto;padding:9px 4px;border:0;font-size:13px}.manage-sellers .seller-table td::before{display:block;margin:0 0 4px;color:var(--seller-muted);font-size:12px;font-weight:600;line-height:1.3}.manage-sellers .seller-table td.store-column::before,.manage-sellers .seller-table td.status-column::before,.manage-sellers .seller-table td.actions-column::before,.manage-sellers .seller-table td.empty-row::before{display:none}.manage-sellers .seller-table td.store-column{grid-area:store;padding-left:0}.manage-sellers .seller-table td.status-column{grid-area:status;justify-self:end;padding-right:0}.manage-sellers .seller-table td.owner-column{grid-area:owner}.manage-sellers .seller-table td.type-column{grid-area:type}.manage-sellers .seller-table td.tin-column{grid-area:tin}.manage-sellers .seller-table td.documents-column{grid-area:documents}.manage-sellers .seller-table td.actions-column{grid-area:actions;margin-top:4px;padding:10px 0 0;border-top:1px solid var(--seller-hair)}.manage-sellers .seller-table td.owner-column::before,.manage-sellers .seller-table td.type-column::before,.manage-sellers .seller-table td.tin-column::before,.manage-sellers .seller-table td.documents-column::before{content:attr(data-label)}.manage-sellers .seller-actions{justify-content:flex-start;gap:8px}.manage-sellers .seller-action{min-height:44px;padding:8px 11px}.manage-sellers .seller-table tr.empty-table-row{display:block}.manage-sellers .seller-table td.empty-row{display:block;padding:26px 12px!important}}
  @media(max-width:767.98px){.manage-sellers{width:100%}.seller-page-head h1{font-size:24px}.seller-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.manage-sellers .stat-card{min-height:102px;padding:12px;border-right:0;border-bottom:1px solid var(--seller-hair)}.manage-sellers .stat-card:first-child{grid-column:1/-1}.manage-sellers .stat-card:nth-child(2),.manage-sellers .stat-card:nth-child(4){border-right:1px solid var(--seller-hair)}.manage-sellers .stat-card:nth-child(3),.manage-sellers .stat-card:nth-child(5){border-right:0}.manage-sellers .stat-card:nth-last-child(-n+2){border-bottom:0}.seller-controls{gap:12px}.manage-sellers .filter-bar{flex-wrap:wrap;overflow:visible;padding:0;scroll-snap-type:none}.manage-sellers .filter-pill{min-height:38px;padding-inline:12px}.manage-sellers .seller-search{max-width:none;flex-wrap:wrap}.manage-sellers .seller-search-field{height:44px;flex:1 1 220px}.manage-sellers .seller-search button{min-height:36px;padding-inline:13px}.seller-table-panel{overflow:visible}.manage-sellers .seller-table tbody{padding:10px;gap:10px}.manage-sellers .seller-table tr.seller-row{padding:10px}.manage-sellers .store-cell{gap:8px}.manage-sellers .seller-actions{gap:7px}.seller-pagination>div{justify-content:center!important}.seller-pagination>div>span:last-child{flex:0 0 100%;margin:4px 0 0!important;text-align:center;white-space:normal}}
  @media(max-width:420px){.manage-sellers .stat-card{min-height:96px;padding:10px}.manage-sellers .stat-card strong{font-size:26px}.manage-sellers .seller-table td.status-column{padding-left:4px}.manage-sellers .seller-status{padding:5px 7px;font-size:11px}.manage-sellers .seller-docs{gap:4px}.manage-sellers .seller-doc{padding:3px 5px;font-size:10px}.manage-sellers .seller-table tr.seller-row{column-gap:7px;padding:9px}.manage-sellers .seller-actions{gap:6px}.manage-sellers .seller-action{padding-inline:9px}}
  @media(prefers-reduced-motion:no-preference){.seller-page-head,.seller-stats,.seller-controls,.seller-table-panel,.seller-pagination{animation:seller-enter .34s cubic-bezier(.22,1,.36,1) both}.seller-stats{animation-delay:45ms}.seller-controls{animation-delay:90ms}.seller-table-panel{animation-delay:135ms}.manage-sellers .seller-table tbody tr.seller-row:nth-child(-n+10){animation:seller-enter .34s cubic-bezier(.22,1,.36,1) both;animation-delay:150ms}}
  @keyframes seller-enter{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  @media(prefers-reduced-motion:reduce){.manage-sellers *,.manage-sellers *::before,.manage-sellers *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>

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
