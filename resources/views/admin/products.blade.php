@extends('admin.layout')

@section('content')
<style>
  .prod-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
  .p-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; transition:transform .15s, box-shadow .15s; }
  .p-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .p-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .p-stat strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .cat-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
  .cat-pill { padding:7px 14px; border-radius:999px; font-size:12.5px; font-weight:700; border:1px solid var(--border); background:#fff; color:#374151; text-decoration:none; transition:all .15s; display:inline-flex; align-items:center; gap:6px; }
  .cat-pill:hover { border-color:#16697A; color:#16697A; }
  .cat-pill.active { background:#16697A; color:#fff; border-color:#16697A; }
  .cat-pill small { font-weight:600; opacity:.7; }
  .cat-pill.active small { color:#fff; opacity:.8; }
  .cat-section { background:#fff; border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); }
  .cat-header { display:flex; align-items:center; gap:12px; padding:14px 18px; cursor:pointer; user-select:none; background:linear-gradient(180deg,#FAFAF8 0%,#fff 100%); border-bottom:1px solid transparent; transition:background .15s; }
  .cat-header:hover { background:#F8F9FA; }
  .cat-section.open .cat-header { border-bottom-color:var(--border); }
  .cat-icon { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .cat-icon svg { width:18px; height:18px; stroke:#fff; fill:none; stroke-width:1.8; }
  .cat-name { font-size:14px; font-weight:800; letter-spacing:-.2px; }
  .cat-count { font-size:11.5px; color:var(--text-secondary); font-weight:600; }
  .cat-meta { margin-left:auto; display:flex; gap:10px; align-items:center; }
  .cat-badge { background:#F0FAFA; border:1px solid #E6F1F2; color:#16697A; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; }
  .cat-toggle { width:28px; height:28px; border-radius:50%; background:#F3F4F6; display:flex; align-items:center; justify-content:center; transition:transform .2s; }
  .cat-section.open .cat-toggle { transform:rotate(180deg); }
  .cat-toggle svg { width:14px; height:14px; stroke:#6B7280; fill:none; stroke-width:2; }
  .cat-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; padding:14px; }
  .cat-section:not(.open) .cat-grid { display:none; }
  .prod-card { background:#fff; border:1px solid #E8E6E0; border-radius:14px; overflow:hidden; transition:transform .15s, box-shadow .15s, border-color .15s; }
  .prod-card:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(16,24,40,.08); border-color:#D1D5DB; }
  .prod-thumb { height:120px; background:linear-gradient(135deg,#E6F1F2 0%,#F0FAFA 100%); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
  .prod-thumb-letter { width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:19px; box-shadow:0 4px 12px rgba(22,105,122,.15); }
  .prod-status { position:absolute; top:8px; right:8px; font-size:10px; font-weight:700; padding:4px 8px; border-radius:999px; border:1px solid; }
  .prod-status.active { background:#E8F5EE; color:#166534; border-color:#BFE3D0; }
  .prod-status.suspended { background:#FEE2E2; color:#991B1B; border-color:#FECACA; }
  .prod-status.inactive { background:#F3F4F6; color:#6B7280; border-color:#E5E7EB; }
  .prod-body { padding:12px; }
  .prod-name { font-size:13.5px; font-weight:700; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .prod-sku { font-size:11px; color:#9CA3AF; font-family:monospace; margin-top:2px; }
  .prod-cat { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; background:#F0FAFA; color:#16697A; border:1px solid #E6F1F2; padding:2px 7px; border-radius:999px; margin-top:8px; }
  .prod-seller { font-size:11.5px; color:#6B7280; margin-top:6px; display:flex; align-items:center; gap:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .prod-price { font-size:15px; font-weight:800; color:#16697A; margin-top:8px; }
  @media (max-width: 900px) { .prod-stats { grid-template-columns:repeat(2,1fr); } .cat-grid { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:4px;">
    <div>
      <h1 class="page-title" style="margin-bottom:4px;">Manage Products</h1>
      <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">All products grouped by category — toys with toys, jewelry with jewelry. Review, filter, and manage each collection at a glance.</p>
    </div>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="prod-stats" style="margin-top:18px;">
    <div class="p-stat">
      <span>All Products</span><strong>{{ $stats['all'] }}</strong><small>total items</small>
    </div>
    <div class="p-stat" style="border-left:3px solid #2E8B57;">
      <span>Active</span><strong style="color:#166534;">{{ $stats['active'] }}</strong><small>visible in store</small>
    </div>
    <div class="p-stat" style="border-left:3px solid #DC2626;">
      <span>Suspended</span><strong style="color:#991B1B;">{{ $stats['suspended'] }}</strong><small>hidden</small>
    </div>
    <div class="p-stat" style="border-left:3px solid #16697A;">
      <span>Categories</span><strong>{{ $stats['categories'] }}</strong><small>collections</small>
    </div>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by product name or SKU..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search') || request('category'))<a href="{{ url('/admin/products') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear</a>@endif
  </form>

  <div class="cat-pills">
    <a href="{{ url('/admin/products'.(request('search')?'?search='.request('search'):'')) }}" class="cat-pill {{ !request('category') ? 'active' : '' }}">All <small>{{ $stats['all'] }}</small></a>
    @foreach($categories as $cat)
      @if($cat->products_count > 0)
      <a href="{{ url('/admin/products?category='.$cat->slug.(request('search')?'&search='.request('search'):'')) }}" class="cat-pill {{ request('category')===$cat->slug || request('category')===$cat->name ? 'active' : '' }}">
        {{ $cat->name }} <small>{{ $cat->products_count }}</small>
      </a>
      @endif
    @endforeach
  </div>

  @if($products->isEmpty())
    <div style="text-align:center; padding:48px 20px; background:#fff; border:1px solid var(--border); border-radius:16px;">
      <div style="width:56px; height:56px; border-radius:50%; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
      </div>
      <div style="font-weight:700; color:#374151;">No products found</div>
      <div style="font-size:13px; color:#9CA3AF; margin-top:4px;">Try a different search or category.</div>
    </div>
  @else
    @foreach($grouped as $categoryName => $catProducts)
      <div class="cat-section open">
        <div class="cat-header" onclick="this.parentElement.classList.toggle('open')">
          <div class="cat-icon">
            <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
          </div>
          <div>
            <div class="cat-name">{{ $categoryName }}</div>
            <div class="cat-count">{{ $catProducts->count() }} product{{ $catProducts->count()>1?'s':'' }}</div>
          </div>
          <div class="cat-meta">
            <span class="cat-badge">{{ $catProducts->where('status','active')->count() }} active</span>
            @if($catProducts->where('status','suspended')->count() > 0)
              <span class="cat-badge" style="background:#FEE2E2; border-color:#FECACA; color:#991B1B;">{{ $catProducts->where('status','suspended')->count() }} suspended</span>
            @endif
            <span class="cat-toggle"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span>
          </div>
        </div>
        <div class="cat-grid">
          @foreach($catProducts as $product)
            @php $img = $product->images->first(); @endphp
            <div class="prod-card">
              <div class="prod-thumb" style="{{ $img ? 'background:#fff;' : '' }}">
                @if($img)
                  <img src="{{ asset('storage/'.$img->path) }}" alt="{{ $product->name }}" style="width:100%; height:100%; object-fit:cover; display:block;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                  <div class="prod-thumb-letter" style="display:none;">{{ strtoupper(mb_substr($product->name,0,1)) }}</div>
                @else
                  <div class="prod-thumb-letter">{{ strtoupper(mb_substr($product->name,0,1)) }}</div>
                @endif
                @php $st = $product->status; @endphp
                <span class="prod-status {{ $st === 'active' ? 'active' : ($st === 'suspended' ? 'suspended' : 'inactive') }}">{{ ucfirst($st) }}</span>
              </div>
              <div class="prod-body">
                <div class="prod-name" title="{{ $product->name }}">{{ $product->name }}</div>
                <div class="prod-sku">{{ $product->sku }}</div>
                <span class="prod-cat">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4Z"/></svg>
                  {{ $product->category->name ?? 'Uncategorized' }}
                </span>
                <div class="prod-seller" title="{{ $product->seller->store_name ?? '' }}">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4Z"/></svg>
                  {{ $product->seller->store_name ?? 'No seller' }}
                </div>
                <div class="prod-price">₱{{ number_format($product->price, 2) }}</div>
                <div style="margin-top:10px; display:flex; gap:6px;">
                  @if($product->status === 'active')
                    <form method="POST" action="{{ url('/admin/products/'.$product->id.'/status') }}" style="display:inline;" data-confirm="This product will be suspended and hidden from the store. Continue?" data-confirm-title="Suspend Product?" data-confirm-ok="Suspend" data-confirm-class="btn-danger" data-confirm-icon="suspend">
                      @csrf
                      <input type="hidden" name="status" value="suspended" />
                      <button type="submit" style="padding:5px 10px;background:#E05A33;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:11px;font-weight:700;">Suspend</button>
                    </form>
                  @else
                    <form method="POST" action="{{ url('/admin/products/'.$product->id.'/status') }}" style="display:inline;">
                      @csrf
                      <input type="hidden" name="status" value="active" />
                      <button type="submit" style="padding:5px 10px;background:#2E8B57;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:11px;font-weight:700;">Activate</button>
                    </form>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  @endif
</div>
@endsection
