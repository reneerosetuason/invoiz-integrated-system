@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/products.css') }}">

<div class="admin-list admin-products" data-ui="admin-products">
  <header class="admin-list-head">
    <h1>Manage Products</h1>
    <p>All products grouped by category — toys with toys, jewelry with jewelry. Review, filter, and manage each collection at a glance.</p>
  </header>

  @if(session('status'))
    <div class="admin-list-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  <nav class="admin-list-stats admin-list-stats--4" aria-label="Product totals">
    <div class="admin-list-stat"><span class="admin-list-stat-label">All Products</span><strong class="{{ $stats['all'] ? '' : 'is-zero' }}">{{ $stats['all'] }}</strong><small>total items</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">Active</span><strong class="{{ $stats['active'] ? '' : 'is-zero' }}">{{ $stats['active'] }}</strong><small>visible in store</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">Suspended</span><strong class="{{ $stats['suspended'] ? '' : 'is-zero' }}">{{ $stats['suspended'] }}</strong><small>hidden</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">Categories</span><strong class="{{ $stats['categories'] ? '' : 'is-zero' }}">{{ $stats['categories'] }}</strong><small>collections</small></div>
  </nav>

  <section class="admin-list-toolbar" aria-label="Find products">
    <nav class="admin-list-filters cat-pills" aria-label="Filter products by category">
      <a href="{{ url('/admin/products'.(request('search')?'?search='.request('search'):'')) }}" class="admin-list-filter cat-pill {{ !request('category') ? 'is-active active' : '' }}" {{ !request('category') ? 'aria-current=page' : '' }}>All <span class="admin-list-filter-count">{{ $stats['all'] }}</span></a>
      @foreach($categories as $cat)
        @if($cat->products_count > 0)
          <a href="{{ url('/admin/products?category='.$cat->slug.(request('search')?'&search='.request('search'):'')) }}" class="admin-list-filter cat-pill {{ request('category')===$cat->slug || request('category')===$cat->name ? 'is-active active' : '' }}" {{ request('category')===$cat->slug || request('category')===$cat->name ? 'aria-current=page' : '' }}>
            {{ $cat->name }} <span class="admin-list-filter-count">{{ $cat->products_count }}</span>
          </a>
        @endif
      @endforeach
    </nav>

    <form class="admin-list-search" method="GET" role="search">
      @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
      <div class="admin-list-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by product name or SKU..." aria-label="Search by product name or SKU">
        <button type="submit">Search</button>
      </div>
      @if(request('search') || request('category'))<a class="admin-list-clear" href="{{ url('/admin/products') }}">Clear</a>@endif
    </form>
  </section>

  @if($products->isEmpty())
    <div class="product-empty">
      <strong>No products found</strong>
      <p>Try a different search or category.</p>
    </div>
  @else
    @foreach($grouped as $categoryName => $catProducts)
      @php $groupId = 'product-category-grid-'.$loop->index; @endphp
      <section class="cat-section open admin-list-category-section" aria-label="{{ $categoryName }} products">
        <button type="button" class="cat-header" aria-expanded="true" aria-controls="{{ $groupId }}" aria-label="Collapse {{ $categoryName }} products" onclick="var section=this.closest('.cat-section');section.classList.toggle('open');var expanded=section.classList.contains('open');this.setAttribute('aria-expanded',expanded?'true':'false');this.setAttribute('aria-label',(expanded?'Collapse ':'Expand ')+section.querySelector('.cat-name').textContent.trim()+' products')">
          <span class="cat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
          </span>
          <span class="cat-heading">
            <span class="cat-name">{{ $categoryName }}</span>
            <span class="cat-count">{{ $catProducts->count() }} product{{ $catProducts->count()>1?'s':'' }}</span>
          </span>
          <span class="cat-meta">
            <span class="cat-badge">{{ $catProducts->where('status','active')->count() }} active</span>
            @if($catProducts->where('status','suspended')->count() > 0)
              <span class="cat-badge cat-badge--suspended">{{ $catProducts->where('status','suspended')->count() }} suspended</span>
            @endif
            <span class="cat-toggle" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
          </span>
        </button>
        <div class="cat-grid" id="{{ $groupId }}">
          @foreach($catProducts as $product)
            @php $img = $product->images->first(); $st = $product->status; @endphp
            <article class="prod-card">
              <div class="prod-thumb">
                @if($img)
                  <img src="{{ asset('storage/'.$img->path) }}" alt="{{ $product->name }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                  <div class="prod-thumb-letter" style="display:none;" aria-hidden="true">{{ strtoupper(mb_substr($product->name,0,1)) }}</div>
                @else
                  <div class="prod-thumb-letter" aria-hidden="true">{{ strtoupper(mb_substr($product->name,0,1)) }}</div>
                @endif
                <span class="prod-status {{ $st === 'active' ? 'active' : ($st === 'suspended' ? 'suspended' : 'inactive') }}">
                  {{ ucfirst(str_replace('_', ' ', $st)) }}
                </span>
              </div>
              <div class="prod-body">
                <div class="prod-name" title="{{ $product->name }}">{{ $product->name }}</div>
                <div class="prod-sku">{{ $product->sku }}</div>
                <span class="prod-cat">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4Z"/></svg>
                  <span>{{ $product->category->name ?? 'Uncategorized' }}</span>
                </span>
                <div class="prod-seller" title="{{ $product->seller->store_name ?? '' }}">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
                  <span>{{ $product->seller->store_name ?? 'No seller' }}</span>
                </div>
                <div class="prod-price">&#8369;{{ number_format($product->price, 2) }}</div>
                @if($product->status === 'active')
                  <form class="product-action-form" method="POST" action="{{ url('/admin/products/'.$product->id.'/status') }}" data-confirm="This product will be suspended and hidden from the store. Continue?" data-confirm-title="Suspend Product?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                    @csrf
                    <input type="hidden" name="status" value="suspended" />
                    <button class="admin-list-action admin-list-action--quiet prod-action" type="submit" aria-label="Suspend {{ $product->name }}">
                      <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3v10M10 3v10"/></svg>Suspend
                    </button>
                  </form>
                @else
                  <form class="product-action-form" method="POST" action="{{ url('/admin/products/'.$product->id.'/status') }}">
                    @csrf
                    <input type="hidden" name="status" value="active" />
                    <button class="admin-list-action prod-action" type="submit" aria-label="Activate {{ $product->name }}">
                      <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>Activate
                    </button>
                  </form>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      </section>
    @endforeach
  @endif
  <div class="admin-list-pagination">
    @include('admin.partials.pager', ['paginator' => $products])
  </div>
</div>
@endsection
