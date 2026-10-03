@extends('layouts.website')
@section('content')
@php
  function cFor($n){ $h=array_sum(array_map('ord', str_split($n))); $pal=['#0F766E','#1D4ED8','#7C3AED','#DB2777','#EA580C','#16A34A','#0891B2','#4F46E5']; return $pal[$h%count($pal)]; }
  $buyer = session('buyer');
  $inCart = false;
  if($buyer){
    $inCart = \App\Models\Cart::where('buyer_id',$buyer['id'])->where('product_id',$p->id)->exists();
  }
@endphp
<a href="{{ url('/'.(request('category') ? '?category='.request('category').(request('search') ? '&search='.urlencode(request('search')) : '') : (request('search') ? '?search='.urlencode(request('search')) : ''))) }}" style="color:var(--text2);text-decoration:none;font-weight:600">← Back to store</a>
@if(session('success'))
  <div style="margin-top:10px;padding:10px 14px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:8px;font-size:13px;font-weight:600">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div style="margin-top:10px;padding:10px 14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;font-size:13px;font-weight:600">{{ session('error') }}</div>
@endif
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px">
  @php $hasPImg = $p->image && file_exists(storage_path('app/public/'.$p->image)); @endphp
  <div style="height:420px;background:#fff;border-radius:10px;display:grid;place-items:center;padding:12px;overflow:hidden;text-align:center;border:1px solid var(--border)">
    @if($hasPImg)
      <img src="{{ asset('storage/'.$p->image) }}" alt="{{ $p->name }}" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;display:block">
    @else
      <div style="width:100%;height:100%;background:{{ cFor($p->name) }};border-radius:10px;display:grid;place-items:center;color:#fff;padding:20px">
        <div>
          <div style="width:80px;height:80px;background:rgba(255,255,255,.22);border-radius:18px;display:grid;place-items:center;margin:0 auto;font-weight:800;font-size:28px">{{ strtoupper(substr($p->name,0,2)) }}</div>
          <div style="margin-top:12px;font-weight:700;font-size:18px">{{ $p->name }}</div>
          <div style="opacity:.9">{{ $p->brand }}</div>
        </div>
      </div>
    @endif
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px">
    <div style="color:var(--green);font-weight:800;font-size:24px">₱{{ number_format($p->price,2) }}</div>
    @if($p->cost_price && $p->cost_price > $p->price)
      <div style="color:var(--text3);font-size:13px;text-decoration:line-through">₱{{ number_format($p->cost_price,2) }}</div>
    @endif
    <div style="font-weight:700;margin-top:6px;font-size:16px">{{ $p->name }}</div>
    @if($p->brand)
      <div style="color:var(--text2);font-size:12px;margin-top:2px">{{ $p->brand }}</div>
    @endif
    <div style="color:var(--text3);font-size:12px;margin-top:4px">{{ $p->stock }} left · {{ $p->category->name ?? '' }} · COD Available</div>
    @if($p->short_description)
      <div style="margin-top:10px;color:var(--text2);font-size:13px;line-height:1.5">{{ $p->short_description }}</div>
    @endif
    <div style="margin-top:16px;display:flex;gap:10px">
      @if($inCart)
        <a href="{{ url('/cart') }}" style="flex:1;text-align:center;background:#f0fdf4;color:var(--green);border:1.5px solid var(--green);padding:11px 16px;border-radius:999px;text-decoration:none;font-weight:700">✓ In Cart — View Cart</a>
      @else
        <a href="{{ url('/cart/add/'.$p->id) }}" style="flex:1;text-align:center;background:#fff;color:var(--green);border:1.5px solid var(--green);padding:11px 16px;border-radius:999px;text-decoration:none;font-weight:700">🛒 Add to Cart</a>
      @endif
      <a href="{{ url('/buy/'.$p->id) }}" style="flex:1;text-align:center;background:var(--green);color:#fff;padding:11px 16px;border-radius:999px;text-decoration:none;font-weight:700">⚡ Buy Now</a>
    </div>
    <a href="{{ url('/chat/seller/'.$p->seller_id) }}" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:10px;padding:10px;background:#fff;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:var(--green-dark);font-weight:700;font-size:13px;transition:all .15s" onmouseover="this.style.borderColor='var(--green)';this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='var(--border)';this.style.background='#fff'">
      💬 Message Seller
    </a>
  </div>
</div>
@endsection
