@extends('seller.layout')

@section('content')
<style>
  .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
  .sum-card { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .sum-label { font-size: 11px; letter-spacing: 1.4px; font-weight: 700; color: #6B7280; text-transform: uppercase; }
  .sum-value { font-size: 24px; font-weight: 800; margin-top: 6px; }
  .panel { background: #fff; border: 1px solid #E5E5E5; border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
  .panel-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; letter-spacing: -.3px; }
  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
  .pill-in { background: #E6F5EE; color: #1E7A43; }
  .pill-low { background: #FFF3E0; color: #C77700; }
  .pill-out { background: #FDECEC; color: #B3261E; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #9CA3AF; font-weight: 700; padding: 0 0 10px; border-bottom: 1px solid #EEEFF1; }
  td { padding: 13px 0; border-bottom: 1px solid #F1F2F4; font-size: 14px; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  .stock-input { width: 90px; background: #F4F5F7; border: 1px solid #E5E5E5; border-radius: 10px; padding: 9px 12px; font-size: 14px; font-family: inherit; }
  .stock-btn { background: #116B78; color: #fff; border: none; border-radius: 10px; padding: 9px 16px; font-weight: 600; font-size: 13px; cursor: pointer; transition: transform .18s ease; }
  .stock-btn:hover { background: #0C555F; transform: scaleX(1.06); }
  .status-msg { background: #E6F5EE; color: #1E7A43; border: 1px solid #BFE3D0; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }
  .prod-cell { display: flex; align-items: center; gap: 12px; }
  .prod-thumb { width: 40px; height: 40px; border-radius: 10px; background: #E6F1F2; color: #116B78; display: flex; align-items: center; justify-content: center; font-weight: 700; }
  .prod-name { font-weight: 600; }
  .prod-sku { font-size: 12px; color: #9CA3AF; }
  .cat { color: #6B7280; font-size: 13px; }
  @media (max-width: 900px) { .cards { grid-template-columns: repeat(2, 1fr); } table { display: block; overflow-x: auto; white-space: nowrap; } }
</style>

@if(session('status'))
  <div class="status-msg">{{ session('status') }}</div>
@endif

<div class="cards">
  <div class="sum-card"><div class="sum-label">Total Products</div><div class="sum-value">{{ $stats['total'] }}</div></div>
  <div class="sum-card"><div class="sum-label">In Stock</div><div class="sum-value" style="color:#2E8B57">{{ $stats['in_stock'] }}</div></div>
  <div class="sum-card"><div class="sum-label">Low Stock</div><div class="sum-value" style="color:#E07B00">{{ $stats['low'] }}</div></div>
  <div class="sum-card"><div class="sum-label">Out of Stock</div><div class="sum-value" style="color:#D64545">{{ $stats['out'] }}</div></div>
</div>

<div class="panel">
  <h3 class="panel-title">Products & Stock</h3>
  <table>
    <thead><tr><th>Product</th><th>Category</th><th>SKU</th><th>Stock</th><th>Status</th><th>Update</th></tr></thead>
    <tbody>
      @forelse ($products as $p)
        <tr>
          <td>
            <div class="prod-cell">
              <div class="prod-thumb">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</div>
              <div><div class="prod-name">{{ $p->name }}</div><div class="prod-sku">₱{{ number_format($p->price, 2) }}</div></div>
            </div>
          </td>
          <td class="cat">{{ $p->category->name ?? '—' }}</td>
          <td class="cat">{{ $p->sku }}</td>
          <td><strong>{{ $p->stock }}</strong></td>
          <td>
            <span class="pill pill-{{ $p->stock_status }}">
              {{ $p->stock_status === 'in' ? 'In stock' : ($p->stock_status === 'low' ? 'Low stock' : 'Out of stock') }}
            </span>
          </td>
          <td>
            <form method="POST" action="{{ url('/seller/inventory/'.$p->id.'/stock') }}" style="display:flex;gap:8px;align-items:center;">
              @csrf
              <input class="stock-input" type="number" name="stock" min="0" value="{{ $p->stock }}" />
              <button class="stock-btn" type="submit">Save</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:30px;">No products yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection