@extends('layouts.website')
@section('content')
<div style="max-width:1100px;margin:0 auto;padding:8px 0 30px">
  <div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div><div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.12em">Your saved items</div><h1 style="margin:5px 0 0;font-size:26px;font-weight:800;color:var(--green-dark)">Wishlist</h1></div>
    <span style="font-size:13px;color:var(--text2)">{{ $favorites->total() }} saved {{ $favorites->total() === 1 ? 'product' : 'products' }}</span>
  </div>
  @if(session('success'))<div role="status" style="margin-top:14px;padding:10px 12px;border-radius:9px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
  @if(session('error'))<div role="alert" style="margin-top:14px;padding:10px 12px;border-radius:9px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
  @if($favorites->isEmpty())
    <div style="margin-top:20px;padding:48px 20px;text-align:center;background:#fff;border:1px solid var(--border);border-radius:14px">
      <div style="font-size:38px;color:#94a3b8">♡</div><h2 style="margin:8px 0;font-size:18px">Your wishlist is empty</h2>
      <p style="margin:0;color:var(--text2);font-size:13px">Save products while browsing and they’ll be collected here.</p>
      <a href="{{ url('/shop') }}" style="display:inline-block;margin-top:16px;padding:10px 18px;border-radius:999px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;font-size:13px">Browse products</a>
    </div>
  @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;margin-top:18px">
      @foreach($favorites as $favorite)
        @php($p = $favorite->product)
        @if($p)
          <article style="position:relative;overflow:hidden;background:#fff;border:1px solid var(--border);border-radius:13px">
            <a href="{{ url('/product/'.$p->id) }}" style="display:block;height:190px;background:#f4f5f5;text-decoration:none">
              @if($p->primary_image_url)<img src="{{ $p->primary_image_url }}" alt="{{ $p->name }}" style="width:100%;height:100%;object-fit:cover">@else<div style="height:100%;display:grid;place-items:center;color:var(--text3);font-size:12px">No product photo</div>@endif
            </a>
            <div style="padding:13px">
              <div style="font-size:11px;color:var(--text3)">{{ $p->category->name ?? 'Product' }}</div>
              <a href="{{ url('/product/'.$p->id) }}" style="display:block;margin-top:4px;min-height:38px;color:var(--text);font-size:13px;font-weight:700;text-decoration:none">{{ $p->name }}</a>
              <div style="margin-top:7px;color:var(--green-dark);font-size:15px;font-weight:800">₱{{ number_format($p->price, 2) }}</div>
              <div style="display:flex;gap:7px;margin-top:12px">
                @if($p->status === 'active')<a href="{{ url('/cart/add/'.$p->id) }}" style="flex:1;padding:8px 9px;text-align:center;border-radius:8px;background:var(--green);color:#fff;text-decoration:none;font-size:12px;font-weight:700">Add to cart</a>@else<span style="flex:1;padding:8px 9px;text-align:center;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:12px;font-weight:700">Unavailable</span>@endif
                <form method="POST" action="{{ url('/wishlist/'.$p->id.'/toggle') }}">@csrf<button type="submit" aria-label="Remove {{ $p->name }} from wishlist" title="Remove" style="height:34px;width:38px;border:1px solid var(--border);border-radius:8px;background:#fff;color:#dc2626;font-size:16px;cursor:pointer">♥</button></form>
              </div>
            </div>
          </article>
        @endif
      @endforeach
    </div>
    <div style="margin-top:18px">{{ $favorites->links() }}</div>
  @endif
</div>
@endsection
