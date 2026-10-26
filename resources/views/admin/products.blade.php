@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<style>
  .admin-products .admin-list-stats{margin-top:0}
  .admin-products .admin-list-filters{overflow-x:auto;scrollbar-width:none}
  .admin-products .admin-list-filters::-webkit-scrollbar{display:none}
  .admin-products .cat-pills{margin:0}
  .admin-products .cat-section{margin:0 0 10px;border-top:1px solid var(--list-hair)}
  .admin-products .cat-section:not(.open) .cat-grid{display:none}
  .admin-products .cat-header{display:flex;width:100%;min-height:60px;align-items:center;gap:11px;padding:10px 2px;border:0;border-bottom:1px solid transparent;background:transparent;color:inherit;text-align:left;cursor:pointer;transition:background-color .16s ease}
  .admin-products .cat-header:hover{background:rgba(234,243,242,.55)}
  .admin-products .cat-section.open .cat-header{border-bottom-color:var(--list-hair)}
  .admin-products .cat-icon{display:flex;width:32px;height:32px;flex:0 0 32px;align-items:center;justify-content:center;border-radius:9px;background:#EAF3F2;color:var(--list-deep)}
  .admin-products .cat-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.55;stroke-linecap:round;stroke-linejoin:round}
  .admin-products .cat-heading{min-width:0}
  .admin-products .cat-name{color:var(--list-deep);font-family:'Fraunces',Georgia,serif;font-size:18px;font-weight:560;line-height:1.2;overflow-wrap:anywhere}
  .admin-products .cat-count{margin-top:3px;color:var(--list-muted);font-size:12.5px;line-height:1.3}
  .admin-products .cat-meta{display:flex;min-width:0;align-items:center;gap:8px;margin-left:auto}
  .admin-products .cat-badge{display:inline-flex;min-height:28px;align-items:center;padding:5px 9px;border:1px solid var(--list-hair);border-radius:999px;background:#fff;color:var(--list-deep);font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap;font-variant-numeric:tabular-nums}
  .admin-products .cat-badge--suspended{background:#F5E9E6;color:#8F4038}
  .admin-products .cat-toggle{display:flex;width:36px;height:36px;flex:0 0 36px;align-items:center;justify-content:center;border:1px solid var(--list-hair);border-radius:50%;background:#fff;color:var(--list-deep)}
  .admin-products .cat-toggle svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;transition:transform .18s ease}
  .admin-products .cat-section.open .cat-toggle svg{transform:rotate(180deg)}
  .admin-products .cat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;padding:14px 0 8px}
  .admin-products .prod-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid rgba(15,76,92,.12);border-radius:14px;background:#fff;transition:transform .16s cubic-bezier(.22,1,.36,1),border-color .16s ease,box-shadow .16s ease}
  .admin-products .prod-card:hover{transform:translateY(-2px);border-color:rgba(15,76,92,.28);box-shadow:0 8px 20px rgba(14,74,87,.07)}
  .admin-products .prod-thumb{position:relative;display:flex;width:100%;aspect-ratio:1;align-items:center;justify-content:center;overflow:hidden;background:#F4F3EE}
  .admin-products .prod-thumb>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
  .admin-products .prod-thumb-letter{display:flex;width:48px;height:48px;align-items:center;justify-content:center;border:1px solid var(--list-hair);border-radius:12px;background:#EAF3F2;color:var(--list-deep);font-size:18px;font-weight:650}
  .admin-products .prod-status{position:absolute;top:9px;left:9px;display:inline-flex;min-height:26px;align-items:center;gap:6px;padding:5px 9px;border:1px solid rgba(15,76,92,.12);border-radius:999px;background:rgba(251,249,246,.94);color:var(--list-deep);font-size:11.5px;font-weight:650;line-height:1.2;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
  .admin-products .prod-status::before{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:#16697A;content:""}
  .admin-products .prod-status.suspended{border-color:#E7CFCA;background:rgba(245,233,230,.96);color:#8F4038}
  .admin-products .prod-status.suspended::before{background:#A44F45}
  .admin-products .prod-status.inactive{background:rgba(243,240,233,.96);color:#43575A}
  .admin-products .prod-status.inactive::before{background:#718184}
  .admin-products .prod-body{display:flex;min-width:0;flex:1;flex-direction:column;padding:11px 12px 12px}
  .admin-products .prod-name{min-height:2.6em;color:#1B1B1E;font-size:14.5px;font-weight:600;line-height:1.3;overflow-wrap:anywhere;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
  .admin-products .prod-sku{min-width:0;margin-top:3px;color:var(--list-muted);font-size:12px;line-height:1.3;overflow-wrap:anywhere}
  .admin-products .prod-cat{display:inline-flex;max-width:100%;min-height:26px;align-self:flex-start;align-items:center;gap:5px;margin-top:8px;padding:4px 8px;border:1px solid var(--list-hair);border-radius:999px;background:#fff;color:var(--list-deep);font-size:11.5px;font-weight:550;line-height:1.2;overflow-wrap:anywhere}
  .admin-products .prod-cat svg,.admin-products .prod-seller svg{width:13px;height:13px;flex:0 0 13px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
  .admin-products .prod-seller{display:flex;min-width:0;align-items:center;gap:5px;margin-top:7px;color:var(--list-muted);font-size:12.5px;line-height:1.35}
  .admin-products .prod-seller span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .admin-products .prod-price{margin-top:auto;padding-top:10px;color:var(--list-deep);font-size:15px;font-weight:700;line-height:1.3;font-variant-numeric:tabular-nums;white-space:nowrap}
  .admin-products .product-action-form{display:flex;margin-top:9px}
  .admin-products .prod-action{width:100%;min-height:36px}
  .admin-products .product-empty{padding:28px 16px;border:1px solid var(--list-hair);border-radius:16px;background:#fff;color:var(--list-ink);text-align:center}
  .admin-products .product-empty strong{font-size:14px;font-weight:650}
  .admin-products .product-empty p{margin:5px 0 0;color:var(--list-muted);font-size:13px;line-height:1.45}
  @media(max-width:1199.98px){.admin-products .cat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media(max-width:900px){.admin-products .cat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:767.98px){.admin-products .cat-header{min-height:64px;gap:8px}.admin-products .cat-meta{gap:5px}.admin-products .cat-badge{min-height:27px;padding:5px 7px;font-size:11px}.admin-products .cat-toggle{width:44px;height:44px;flex-basis:44px}.admin-products .cat-grid{gap:12px;padding-top:12px}}
  @media(max-width:339.98px){.admin-products .cat-grid{grid-template-columns:minmax(0,1fr)}}
</style>

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
