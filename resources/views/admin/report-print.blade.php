<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }} — Invoiz Report</title>
<link rel="stylesheet" href="{{ asset('assets/admin/css/report-print.css') }}">
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

  <script src="{{ asset('assets/admin/js/report-print.js') }}"></script>
</body>
</html>
