<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }} — Invoiz Report</title>
<style>
  @page { size: A4; margin: 14mm 12mm 14mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; color: #1B1B1E; margin: 0; background: #F7F6F2; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page { max-width: 960px; margin: 0 auto; background: #fff; padding: 24px 28px; box-shadow: 0 1px 4px rgba(16,24,40,.06); }
  @media print { body { background: #fff; } .page { box-shadow: none; padding: 0; } .noprint { display: none !important; } }
  .noprint { max-width: 960px; margin: 16px auto; padding: 0 8px; display:flex; gap:10px; align-items:center; }
  .noprint button { padding: 10px 22px; background: #16697A; color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(22,105,122,.18); }
  .noprint a { color: #16697A; font-weight: 600; text-decoration: none; }
  /* Header */
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 3px solid #16697A; padding-bottom: 14px; margin-bottom: 14px; }
  .brand { display: flex; align-items: center; gap: 12px; }
  .logo { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; border: 2px solid #E6F1F2; }
  .brand-text h1 { font-size: 20px; margin: 0; color: #0E4A57; letter-spacing: -.4px; line-height: 1.2; }
  .brand-text .sub { font-size: 11px; color: #6E6E73; letter-spacing: .8px; text-transform: uppercase; font-weight: 700; margin-top: 2px; }
  .head-meta { text-align: right; font-size: 11px; color: #6E6E73; line-height: 1.5; }
  /* Section */
  .section { margin-bottom: 18px; break-inside: avoid; }
  .section-title { font-size: 13px; font-weight: 800; color: #0E4A57; letter-spacing: -.2px; margin: 0 0 8px; display: flex; align-items: center; gap: 8px; }
  .section-title svg { width: 16px; height: 16px; stroke: #16697A; fill: none; stroke-width: 1.8; }
  .section-title .count { margin-left: auto; font-size: 11px; font-weight: 700; background: #F0FAFA; color: #16697A; padding: 3px 8px; border-radius: 999px; border: 1px solid #E6F1F2; }
  .table-wrap { border: 1px solid #E8E6E0; border-radius: 12px; overflow: hidden; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #16697A; color: #fff; text-align: left; padding: 7px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
  td { padding: 7px 10px; font-size: 11.5px; border-bottom: 1px solid #F1F2F4; }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:nth-child(even) td { background: #FAFAF8; }
  tfoot td { font-weight: 800; background: #EAF4F3; border-top: 2px solid #16697A; font-size: 11.5px; color: #0E4A57; }
  .empty { padding: 20px; text-align: center; color: #9CA3AF; font-size: 12px; }
  .foot { margin-top: 18px; padding-top: 10px; border-top: 1px solid #E8E6E0; display: flex; justify-content: space-between; font-size: 9.5px; color: #9CA3AF; }
  .confidential { display: inline-flex; align-items: center; gap: 6px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400e; padding: 4px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
</style>
</head>
<body>
  <div class="noprint">
    <button onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ url('/admin/reports') }}">Back to Reports</a>
    <span style="margin-left:auto; font-size:12px; color:#6E6E73;">Tip: Use landscape for wide tables</span>
  </div>

  <div class="page">
    <div class="head">
      <div class="brand">
        <div class="logo">I</div>
        <div class="brand-text">
          <h1>{{ $title }}</h1>
          <div class="sub">Invoiz Marketplace</div>
        </div>
      </div>
      <div style="text-align:right; font-size:11px; color:#6E6E73; line-height:1.4;">
        <div>{{ now()->format('F d, Y') }}</div>
      </div>
    </div>

    <div class="section">
      <h2 class="section-title">
        <svg viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg>
        {{ $title }}
        <span class="count">{{ count($rows) }} rows</span>
      </h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              @foreach ($headings as $h)
              <th>{{ $h }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $row)
            <tr>
              @foreach ($row as $cell)
              <td>{{ $cell }}</td>
              @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($headings) }}" class="empty">No data for this period.</td></tr>
            @endforelse
          </tbody>
          @if (!empty($totals))
          <tfoot>
            <tr>
              <td>TOTAL</td>
              @foreach ($headings as $i => $h)
              @if ($i > 0)
              <td>{{ $totals[$i] ?? '' }}</td>
              @endif
              @endforeach
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>

    @if(($type ?? '') === 'sales' && !empty($byCategory) && count($byCategory))
    <div class="section">
      <h2 class="section-title">
        <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
        Sales by Category
        <span class="count">{{ count($byCategory) }} categories</span>
      </h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Category</th><th>Units</th><th>Sales</th><th>Share</th></tr></thead>
          <tbody>
            @php $catTotal = $byCategory->sum('sales'); @endphp
            @foreach($byCategory as $c)
            <tr>
              <td style="font-weight:600;">{{ $c->name }}</td>
              <td>{{ $c->units }}</td>
              <td style="font-weight:700;">₱{{ number_format($c->sales, 2) }}</td>
              <td style="color:#6E6E73;">{{ $catTotal > 0 ? number_format(($c->sales / $catTotal)*100,1) : 0 }}%</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot><tr><td>TOTAL</td><td>{{ $byCategory->sum('units') }}</td><td>₱{{ number_format($byCategory->sum('sales'), 2) }}</td><td>100%</td></tr></tfoot>
        </table>
      </div>
    </div>
    @endif

    @if(($type ?? '') === 'sales' && !empty($byProduct) && count($byProduct))
    <div class="section">
      <h2 class="section-title">
        <svg viewBox="0 0 24 24"><path d="M12 3l7 4v5c0 5-3.5 9-7 11-3.5-2-7-6-7-11V7l7-4Z"/><path d="M9 12l2 2 4-4"/></svg>
        Top Products by Sales
        <span class="count">Top {{ count($byProduct) }}</span>
      </h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Product</th><th>Units</th><th>Sales</th></tr></thead>
          <tbody>
            @foreach($byProduct as $idx => $p)
            <tr>
              <td style="color:#6E6E73; font-weight:700;">{{ $idx+1 }}</td>
              <td style="font-weight:600;">{{ $p->name }}</td>
              <td>{{ $p->units }}</td>
              <td style="font-weight:700;">₱{{ number_format($p->sales, 2) }}</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot><tr><td colspan="2">TOTAL</td><td>{{ $byProduct->sum('units') }}</td><td>₱{{ number_format($byProduct->sum('sales'), 2) }}</td></tr></tfoot>
        </table>
      </div>
    </div>
    @endif

    <div class="foot">
      <span>Generated automatically by the Invoiz Reporting System · {{ now()->format('Y') }} Invoiz Marketplace</span>
      <span>Page 1 · {{ $title }}</span>
    </div>
  </div>

  <script>window.onload = function(){ setTimeout(()=>window.print(), 400); };</script>
</body>
</html>
