@extends('admin.layout')

@section('content')
@php
  $statusPalette = [
    'delivered' => '#0E4A57',
    'out_for_delivery' => '#3F8F9C',
    'pending' => '#A9CFD3',
    'cancelled' => '#B4655A',
  ];
  $pieTotal = array_sum(array_column($statusStats, 'count'));
  $cx = 100; $cy = 100; $outerR = 78; $innerR = 53;
  $ang = -90; $segments = [];
  foreach ($statusStats as $s) {
    $sweep = $pieTotal > 0 ? ($s['count'] / $pieTotal) * 360 : 0;
    $a1 = $ang * pi() / 180; $a2 = ($ang + $sweep) * pi() / 180;
    $x1 = $cx + $outerR * cos($a1); $y1 = $cy + $outerR * sin($a1);
    $x2 = $cx + $outerR * cos($a2); $y2 = $cy + $outerR * sin($a2);
    $ix2 = $cx + $innerR * cos($a2); $iy2 = $cy + $innerR * sin($a2);
    $ix1 = $cx + $innerR * cos($a1); $iy1 = $cy + $innerR * sin($a1);
    $large = $sweep > 180 ? 1 : 0;
    $segments[] = [
      'status' => $s['status'],
      'color' => $statusPalette[$s['status']] ?? '#CFC8BA',
      'path' => "M {$x1},{$y1} A {$outerR},{$outerR} 0 {$large} 1 {$x2},{$y2} L {$ix2},{$iy2} A {$innerR},{$innerR} 0 {$large} 0 {$ix1},{$iy1} Z",
      'full' => $sweep >= 359.999,
    ];
    $ang += $sweep;
  }
  $max = max(1, max(array_column($chart, 'value')));
  $W = 720; $H = 280; $padL = 82; $padR = 22; $padT = 16; $padB = 36;
  $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
  $n = count($chart); $pts = [];
  foreach ($chart as $i => $c) {
    $x = $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
    $y = $padT + $plotH - ($c['value'] / $max) * $plotH;
    $pts[] = [$x, $y, $c];
  }
  $linePoints = [];
  foreach ($pts as [$x, $y]) $linePoints[] = $x.','.$y;
  $linePoints = implode(' ', $linePoints);
  $areaPoints = $padL.','.($padT + $plotH).' '.$linePoints.' '.($padL + $plotW).','.($padT + $plotH);
@endphp
<style>
  #adminDashboard{--paper:#FBF9F6;--mist:#EDF3F1;--teal:#16697A;--deep:#0E4A57;--ink:#1B1B1E;--muted:#56696B;--brick:#B4655A;--ease:cubic-bezier(.22,1,.36,1);width:100%;min-width:0;margin:0 auto;color:var(--ink);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif} /* taste-skill-ignore: established INVOIZ brand typeface */
  #adminDashboard *,#adminDashboard *::before,#adminDashboard *::after{box-sizing:border-box}
  #adminDashboard .dash-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:20px;margin:0 0 24px}
  #adminDashboard .dash-date{margin:0 0 8px;color:var(--muted);font-size:13px;font-variant-numeric:tabular-nums}
  #adminDashboard h1{margin:0;color:var(--deep);font-family:'Fraunces',Georgia,'Times New Roman',serif;font-size:28px;font-weight:560;line-height:1.12;letter-spacing:-.02em}
  #adminDashboard .dash-description{max-width:68ch;margin:8px 0 0;color:#40585C;font-size:14px;line-height:1.55}
  #adminDashboard .orders-total{align-self:end;margin-bottom:2px;padding:8px 13px;border-radius:999px;background:var(--mist);color:var(--deep);font-size:13px;font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .cards{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px 20px;margin:0 0 24px;padding:18px;background:#fff;border-radius:16px;min-width:0}
  #adminDashboard .sum-card{position:relative;display:flex;min-width:0;min-height:88px;flex-direction:column;justify-content:center;padding:6px 30px 6px 0;transition:transform .2s var(--ease),filter .2s ease}
  #adminDashboard .sum-card:hover{transform:translateY(-2px);filter:drop-shadow(0 5px 8px rgba(15,76,92,.08))}
  #adminDashboard .sum-card:nth-child(-n+3){grid-column:span 4}
  #adminDashboard .sum-card:nth-child(n+4){grid-column:span 3}
  #adminDashboard .sum-ico{position:absolute;top:5px;right:2px;display:flex;width:18px;height:18px;align-items:center;justify-content:center;margin:0;background:transparent!important;color:#7A999C!important}
  #adminDashboard .sum-ico svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.5}
  #adminDashboard .sum-label{color:#40585C;font-size:13px;font-weight:600;line-height:1.35}
  #adminDashboard .sum-value{display:flex;align-items:center;gap:6px;min-width:0;margin:5px 0 0;color:var(--deep);font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:26px;font-weight:650;line-height:1.15;letter-spacing:-.035em;font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .sum-value.money{font-size:25px;letter-spacing:-.045em}
  #adminDashboard .sum-value .rating-star{display:inline-flex;color:var(--deep)}
  #adminDashboard .sum-value .rating-star svg{width:16px;height:16px;stroke:currentColor;stroke-width:1.6;fill:none}
  #adminDashboard .sum-sub{min-height:18px;margin-top:4px;color:var(--muted);font-size:12.5px;line-height:1.4}
  #adminDashboard .sum-sub.reserved{min-height:36px}
  #adminDashboard .dashboard-row{display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,1fr);gap:24px;margin-top:24px;min-width:0}
  #adminDashboard .panel{min-width:0;padding:20px;background:#fff;border-radius:16px}
  #adminDashboard .panel-head{display:flex;min-width:0;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px}
  #adminDashboard .panel-title{margin:0;color:var(--deep);font-family:'Fraunces',Georgia,serif;font-size:18px;font-weight:560;line-height:1.25;letter-spacing:-.01em}
  #adminDashboard .panel-title svg{width:18px;height:18px;flex:0 0 auto;stroke:#5B858B;fill:none;stroke-width:1.5}
  #adminDashboard .panel-title.with-icon{display:flex;align-items:center;gap:8px}
  #adminDashboard .panel-sub{margin:5px 0 0;color:var(--muted);font-size:13px;line-height:1.45}
  #adminDashboard .delivered-chip{display:inline-flex;min-height:28px;align-items:center;gap:7px;padding:4px 11px;border-radius:999px;background:var(--mist);color:var(--deep);font-size:12px;font-weight:600;white-space:nowrap}
  #adminDashboard .delivered-chip svg{width:13px;height:13px;stroke:currentColor;stroke-width:1.8;fill:none}
  #adminDashboard .sales-chart{display:block;width:100%;height:auto;max-height:280px;overflow:visible;outline:none;pointer-events:all;touch-action:pan-y}
  #adminDashboard .sales-chart:focus-visible{outline:2px solid var(--deep);outline-offset:3px;border-radius:6px}
  #adminDashboard .chart-frame{position:relative;min-width:0}
  #adminDashboard .chart-tooltip{position:absolute;z-index:2;max-width:180px;padding:7px 10px;border-radius:8px;background:var(--deep);color:#fff;font-size:12px;font-weight:600;font-variant-numeric:tabular-nums;line-height:1.3;white-space:nowrap;pointer-events:none;opacity:0;transform:translate(-50%,-115%) scale(.96);transition:opacity .14s ease,transform .14s var(--ease)}
  #adminDashboard .chart-tooltip.is-visible{opacity:1;transform:translate(-50%,-115%) scale(1)}
  #adminDashboard .sales-area{fill:rgba(22,105,122,.08)}
  #adminDashboard .sales-line{fill:none;stroke:var(--deep);stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
  #adminDashboard .sales-tick,#adminDashboard .sales-date-label{fill:#52686A;font-family:'Inter',system-ui,sans-serif;font-size:11.5px;font-variant-numeric:tabular-nums} /* taste-skill-ignore: established INVOIZ brand typeface */
  #adminDashboard .sales-point{fill:#fff;stroke:var(--deep);stroke-width:2;opacity:0;transform-box:fill-box;transform-origin:center;transition:opacity .15s ease,transform .15s ease}
  #adminDashboard .sales-point.is-active{opacity:1;stroke-width:3;transform:scale(1.35)}
  #adminDashboard .sales-chart:focus-visible .sales-point{opacity:.35}
  #adminDashboard .sales-chart:focus-visible .sales-point.is-active{opacity:1}
  #adminDashboard .sales-line{transition:stroke-dashoffset .75s var(--ease)}
  #adminDashboard .status-layout{display:flex;align-items:center;gap:18px;min-width:0}
  #adminDashboard .dashboard-row:first-of-type .panel:last-child{display:flex;flex-direction:column}
  #adminDashboard .dashboard-row:first-of-type .status-layout{flex:1}
  #adminDashboard .donut-chart{display:block;width:156px;height:156px;flex:0 0 156px;overflow:visible}
  #adminDashboard .donut-slice{stroke:none;transform-box:fill-box;transform-origin:center;transition:opacity .2s ease,transform .2s var(--ease)}
  #adminDashboard .donut-slice.is-muted{opacity:.28}
  #adminDashboard .donut-slice.is-active{transform:scale(1.045)}
  #adminDashboard .donut-hole{fill:#fff}
  #adminDashboard .donut-empty{fill:#D9E5E3}
  #adminDashboard .donut-total{fill:var(--deep);font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;font-variant-numeric:tabular-nums}
  #adminDashboard .donut-label{fill:#52686A;font-family:'Inter',system-ui,sans-serif;font-size:12px;font-weight:500} /* taste-skill-ignore: established INVOIZ brand typeface */
  #adminDashboard .legend{flex:1;min-width:0}
  #adminDashboard .legend-row{display:flex;width:100%;min-width:0;align-items:center;justify-content:space-between;gap:8px;padding:7px 8px;border:0;border-radius:8px;background:transparent;color:#40585C;font:inherit;font-size:13px;line-height:1.3;text-align:left;cursor:pointer;transition:background .16s ease,transform .16s var(--ease),color .16s ease}
  #adminDashboard .legend-row:hover,#adminDashboard .legend-row.is-active{background:#EDF3F1;color:var(--deep)}
  #adminDashboard .legend-row:active{transform:scale(.985)}
  #adminDashboard .legend-row:focus-visible,#adminDashboard .clear-order-filter:focus-visible{outline:2px solid var(--deep);outline-offset:2px}
  #adminDashboard .legend-name{display:flex;min-width:0;align-items:center;gap:8px}
  #adminDashboard .legend-dot{width:9px;height:9px;flex:0 0 9px;border-radius:50%}
  #adminDashboard .legend-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  #adminDashboard .legend-values{flex:0 0 auto;color:var(--muted);font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .legend-values strong{margin-left:7px;color:var(--ink);font-weight:700}
  #adminDashboard .link{display:inline-flex;min-height:44px;align-items:center;gap:7px;color:var(--deep);font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap}
  #adminDashboard .link:hover,#adminDashboard .link:focus-visible{text-decoration:underline;text-decoration-thickness:1.5px;text-underline-offset:4px}
  #adminDashboard .link:focus-visible,#adminDashboard .status-chip:focus-visible{outline:2px solid var(--deep);outline-offset:3px}
  #adminDashboard .filter-summary{color:var(--muted);font-size:11.5px;white-space:nowrap}
  #adminDashboard .clear-order-filter{padding:5px 9px;border:0;border-radius:999px;background:#EDF3F1;color:var(--deep);font:inherit;font-size:11.5px;font-weight:600;cursor:pointer}
  #adminDashboard .clear-order-filter:hover{background:#DCEBE8}
  #adminDashboard .order-filter-toolbar{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin:-6px 0 12px}
  #adminDashboard .order-filter-toolbar[hidden]{display:none}
  #adminDashboard .orders-table{width:100%;border-collapse:collapse;table-layout:fixed}
  #adminDashboard .orders-table th{padding:0 10px 10px 0;border-bottom:1px solid rgba(15,76,92,.14);color:#52686A;font-size:11px;font-weight:700;letter-spacing:.12em;text-align:left;text-transform:uppercase}
  #adminDashboard .orders-table th:nth-child(4),#adminDashboard .orders-table th:nth-child(5){text-align:right}
  #adminDashboard .orders-table th:last-child,#adminDashboard .orders-table td:last-child{padding-right:0}
  #adminDashboard .orders-table td{height:58px;padding:9px 10px 9px 0;border-bottom:1px solid rgba(15,76,92,.1);font-size:13px;line-height:1.4;vertical-align:middle}
  #adminDashboard .orders-table tbody tr:last-child td{border-bottom:0}
  #adminDashboard .orders-table tbody tr.recent-order{transition:opacity .18s ease,transform .18s var(--ease),background .18s ease}
  #adminDashboard .orders-table tbody tr.recent-order:hover td{background:#F7FAF9}
  #adminDashboard .orders-table tbody tr.recent-order[hidden],#adminDashboard .filter-empty[hidden]{display:none}
  #adminDashboard .orders-table tbody tr.recent-order.is-entering{animation:record-in .24s var(--ease) both}
  #adminDashboard .order-no{display:block;overflow:hidden;color:var(--deep);font-size:13.5px;font-weight:600;font-variant-numeric:tabular-nums;text-overflow:ellipsis;white-space:nowrap}
  #adminDashboard .order-date{display:block;margin-top:2px;color:var(--muted);font-size:12.5px;font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .buyer-profile{display:flex;min-width:0;align-items:center;gap:8px}
  #adminDashboard .buyer-avatar{display:inline-flex;width:26px;height:26px;flex:0 0 26px;align-items:center;justify-content:center;border-radius:50%;background:#EDF3F1;color:var(--deep);font-size:10px;font-weight:700}
  #adminDashboard .buyer-name{overflow:hidden;color:var(--ink);font-size:13px;font-weight:500;text-overflow:ellipsis;white-space:nowrap}
  #adminDashboard .store-name{display:block;overflow:hidden;color:var(--muted);text-overflow:ellipsis;white-space:nowrap}
  #adminDashboard .total-cell{text-align:right;color:var(--ink);font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .status-cell{text-align:right}
  #adminDashboard .status-chip{display:inline-flex;min-height:28px;align-items:center;justify-content:center;gap:6px;padding:5px 9px;border:0;border-radius:999px;background:#F0EEE9;color:#40585C;font-size:11.5px;font-weight:600;line-height:1.2;white-space:nowrap}
  #adminDashboard .status-chip .dot{width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:currentColor}
  #adminDashboard .status-chip svg{width:12px;height:12px;flex:0 0 12px;stroke:currentColor;stroke-width:2;fill:none}
  #adminDashboard .status-chip.pill-delivered,#adminDashboard .status-chip.status-out_for_delivery{background:#EAF4F3;color:var(--deep)}
  #adminDashboard .status-chip.pill-pending,#adminDashboard .status-chip.pill-new,#adminDashboard .status-chip.pill-confirmed,#adminDashboard .status-chip.pill-processing,#adminDashboard .status-chip.pill-ready_for_delivery,#adminDashboard .status-chip.pill-shipped{background:#F3F0E9;color:#475A5C}
  #adminDashboard .status-chip.pill-cancelled{background:#F3F0E9;color:#9C5A52}
  #adminDashboard .panel-top-products .panel-head{margin-bottom:12px}
  #adminDashboard .top-chip{flex:0 0 auto;padding:6px 11px;border-radius:999px;background:#EDF3F1;color:var(--deep);font-size:12px;font-weight:600;white-space:nowrap}
  #adminDashboard .top-prod{display:flex;min-width:0;align-items:center;gap:14px;padding:12px 0;transition:background .18s ease,transform .18s var(--ease)}
  #adminDashboard .top-prod:hover{background:#F5F9F8}
  #adminDashboard .rank{display:inline-flex;width:24px;height:24px;flex:0 0 24px;align-items:center;justify-content:center;border-radius:7px;background:#EDF3F1;color:#52686A;font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}
  #adminDashboard .product-info{flex:1;min-width:0}
  #adminDashboard .product-name{overflow:hidden;color:var(--ink);font-size:14px;font-weight:550;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
  #adminDashboard .product-progress{display:flex;align-items:center;gap:9px;margin-top:7px}
  #adminDashboard .bar{height:4px;flex:1;max-width:140px;overflow:hidden;border-radius:999px;background:#DCE8E6}
  #adminDashboard .bar-fill{height:100%;border-radius:999px;background:var(--deep)}
  #adminDashboard .units-sold{color:var(--muted);font-size:12px;font-weight:500;white-space:nowrap}
  #adminDashboard .product-price{flex:0 0 auto;color:var(--deep);font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
  #adminDashboard .top-prod+.top-prod{border-top:1px solid rgba(15,76,92,.08)}
  #adminDashboard .empty-state{padding:24px 8px;color:var(--muted);font-size:13px;text-align:center}
  @media(max-width:1279px){#adminDashboard .cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 20px;padding:18px}#adminDashboard .sum-card:nth-child(-n+2){grid-column:span 2}#adminDashboard .sum-card:nth-child(n+3){grid-column:span 1}#adminDashboard .sum-card:last-child{grid-column:span 2}#adminDashboard .sum-card{min-height:92px}}
  @media(max-width:1023.98px){#adminDashboard .dashboard-row{grid-template-columns:minmax(0,1fr)}#adminDashboard .status-layout{justify-content:flex-start}#adminDashboard .donut-chart{width:176px;height:176px;flex-basis:176px}}
  @media(max-width:720px){body:has(#adminDashboard) .content{padding:20px 16px}#adminDashboard .dash-head{grid-template-columns:minmax(0,1fr);align-items:start;gap:12px;margin-bottom:20px}#adminDashboard h1{font-size:24px}#adminDashboard .orders-total{justify-self:start;margin:0}#adminDashboard .cards{gap:10px 16px;padding:16px}#adminDashboard .sum-card{min-height:88px;padding-right:28px}#adminDashboard .sum-value,#adminDashboard .sum-value.money{font-size:24px}#adminDashboard .sum-sub{font-size:12.5px}#adminDashboard .dashboard-row{gap:18px;margin-top:18px}#adminDashboard .panel{padding:16px}#adminDashboard .sales-chart{max-height:220px}#adminDashboard .status-layout{flex-direction:column;align-items:stretch;gap:8px}#adminDashboard .donut-chart{align-self:center;width:184px;height:184px;flex-basis:184px}#adminDashboard .legend-row{padding:6px 8px}#adminDashboard .orders-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap}#adminDashboard .orders-table,#adminDashboard .orders-table tbody{display:block;width:100%}#adminDashboard .orders-table tbody{display:grid;gap:12px}#adminDashboard .orders-table tbody tr.recent-order{display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"order status" "buyer date" "store total";align-items:center;column-gap:8px;row-gap:8px;padding:12px 0;border-bottom:1px solid rgba(15,76,92,.12)}#adminDashboard .orders-table tbody tr.recent-order:last-child{border-bottom:0}#adminDashboard .orders-table td{height:auto;min-width:0;padding:0;border:0}#adminDashboard .orders-table td.order-cell{display:contents}#adminDashboard .order-no{grid-area:order;max-width:100%;font-size:12.5px}#adminDashboard .order-date{grid-area:date;justify-self:end;margin:0;font-size:12px}#adminDashboard .buyer-cell{grid-area:buyer;min-width:0}#adminDashboard .buyer-profile{gap:7px}#adminDashboard .buyer-avatar{width:26px;height:26px;flex-basis:26px}#adminDashboard .buyer-name{font-size:12.5px}#adminDashboard .store-cell{grid-area:store}#adminDashboard .total-cell{grid-area:total;font-size:12.5px}#adminDashboard .status-cell{grid-area:status}#adminDashboard .status-chip{max-width:118px;min-height:28px;white-space:normal;text-align:center}#adminDashboard .orders-table .empty-order{display:block;padding:18px 4px;border:0;text-align:center}#adminDashboard .top-prod{gap:10px;padding:10px 0}#adminDashboard .product-name{font-size:14px}#adminDashboard .product-price{font-size:12.5px}}
  @media(max-width:380px){#adminDashboard .sum-value,#adminDashboard .sum-value.money{font-size:clamp(17px,5.6vw,22px)}#adminDashboard .sum-label{font-size:12.5px}#adminDashboard .sum-sub{font-size:12px}#adminDashboard .panel-title{font-size:17px}#adminDashboard .sales-chart{max-height:220px}#adminDashboard .status-layout{gap:4px}#adminDashboard .legend-row{font-size:12.5px}}
  @media(prefers-reduced-motion:no-preference){#adminDashboard .dash-head,#adminDashboard .cards,#adminDashboard .dashboard-row{animation:admin-dashboard-in .32s var(--ease) both}#adminDashboard .cards{animation-delay:60ms}#adminDashboard .dashboard-row:first-of-type{animation-delay:120ms}#adminDashboard .dashboard-row:last-of-type{animation-delay:180ms}}
  @keyframes admin-dashboard-in{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  @keyframes record-in{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
  @media(prefers-reduced-motion:reduce){#adminDashboard *,#adminDashboard *::before,#adminDashboard *::after{scroll-behavior:auto!important;animation:none!important;transition:none!important}#adminDashboard .dash-head,#adminDashboard .cards,#adminDashboard .dashboard-row{animation:none}}
</style>
<div id="adminDashboard">
  <header class="dash-head">
    <div>
      <p class="dash-date">{{ now()->format('l, F d') }}</p>
      <h1>Welcome back, {{ auth()->user()->name ?? 'Admin' }}</h1>
      <p class="dash-description">Here's what's happening with your marketplace today — revenue, orders, and top products at a glance.</p>
    </div>
    <div class="orders-total">{{ $stats['orders'] }} orders total</div>
  </header>

  <section class="cards" aria-label="Marketplace metrics">
    <div class="sum-card">
      <div class="sum-ico c-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
      <div class="sum-label">Revenue</div>
      <div class="sum-value money">₱{{ number_format($stats['revenue'], 2) }}</div>
      <div class="sum-sub">Settled sales · exact</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.6 14.8 3 12 3S7 4.6 7 6.5c0 4.5 10 4 10 11 0 1.9-2.2 3.5-5 3.5s-5-1.6-5-3.5"/></svg></div>
      <div class="sum-label">Platform Cut</div>
      <div class="sum-value money">₱{{ number_format($stats['commission_earned'], 2) }}</div>
      <div class="sum-sub reserved">10% commission · logistics ₱{{ number_format($stats['logistics_earned'], 2) }}</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h12l1 6H5l1-6Z"/><path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 8Z"/></svg></div>
      <div class="sum-label">Orders</div>
      <div class="sum-value">{{ $stats['orders'] }}</div>
      <div class="sum-sub">{{ $stats['delivered'] }} delivered</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-orange" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
      <div class="sum-label">To Action</div>
      <div class="sum-value">{{ $stats['to_action'] }}</div>
      <div class="sum-sub">Needs your review</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-purple" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11a5 5 0 1 0 6 0M12 6v5M4 17l2-1M20 17l-2-1"/></svg></div>
      <div class="sum-label">To Ship</div>
      <div class="sum-value">{{ $stats['to_ship'] }}</div>
      <div class="sum-sub">Ready to ship</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-dark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4zM9 11h6"/><path d="M7 3l2 4M17 3l-2 4"/></svg></div>
      <div class="sum-label">Products</div>
      <div class="sum-value">{{ $stats['products'] }}</div>
      <div class="sum-sub">{{ $stats['low_stock'] }} low stock</div>
    </div>
    <div class="sum-card">
      <div class="sum-ico c-gold" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 8.7l5.4-.8L12 3Z"/></svg></div>
      <div class="sum-label">Rating</div>
      <div class="sum-value">{{ number_format($stats['rating'], 1) }} <span class="rating-star" aria-hidden="true"><x-ui-icon name="star" :size="16" /></span></div>
      <div class="sum-sub">{{ $stats['sellers'] }} active sellers</div>
    </div>
  </section>

  <div class="dashboard-row">
    <section class="panel">
      <header class="panel-head">
        <h2 class="panel-title with-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg> Sales — Last 30 Days</h2>
        <span class="delivered-chip"><svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>Delivered</span>
      </header>
      <div class="chart-frame">
      <svg id="salesChart" class="sales-chart" viewBox="0 0 {{ $W }} {{ $H }}" role="group" aria-label="Sales over the last 30 days. Use the arrow keys to inspect daily values." tabindex="0">
        @foreach ([0, .25, .5, .75, 1] as $g)
          @php $gy = $padT + $plotH * $g; $val = $max * (1 - $g); @endphp
          <text class="sales-tick" x="{{ $padL - 10 }}" y="{{ $gy + 4 }}" text-anchor="end">₱{{ $val >= 1000 ? number_format($val/1000,1).'k' : number_format($val,0) }}</text>
        @endforeach
        <g class="sales-x-axis">
          @foreach ($pts as $i => [$x, $y, $c])
            @if ($i % 7 === 0 && $i < $n - 2 || $i === $n - 1)
              <text class="sales-date-label" x="{{ $x }}" y="{{ $H - 8 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}">{{ $c['label'] }}</text>
            @endif
          @endforeach
        </g>
        <polygon class="sales-area" points="{{ $areaPoints }}" aria-hidden="true" />
        <polyline class="sales-line" points="{{ $linePoints }}" aria-hidden="true" />
        @foreach ($pts as [$x, $y, $c])<circle class="sales-point" cx="{{ $x }}" cy="{{ $y }}" r="3" aria-hidden="true" />@endforeach
      </svg>
      <div class="chart-tooltip" id="salesChartTooltip" role="status" aria-live="polite" aria-hidden="true"></div>
      </div>
    </section>

    <section class="panel">
      <header class="panel-head"><h2 class="panel-title">Orders by Status</h2></header>
      <div class="status-layout">
        <svg class="donut-chart" viewBox="0 0 200 200" role="img" aria-label="Order totals by status">
          @if ($pieTotal > 0)
            @if (count($segments) === 1 && $segments[0]['full'])
              <circle class="donut-slice" data-order-status="{{ $segments[0]['status'] }}" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $outerR }}" fill="{{ $segments[0]['color'] }}" />
            @else
              @foreach ($segments as $seg)
                <path class="donut-slice" data-order-status="{{ $seg['status'] }}" d="{{ $seg['path'] }}" fill="{{ $seg['color'] }}" />
              @endforeach
            @endif
            <circle class="donut-hole" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $innerR }}" aria-hidden="true" />
          @else
            <circle class="donut-empty" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $outerR }}" />
            <circle class="donut-hole" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $innerR }}" aria-hidden="true" />
          @endif
          <text class="donut-total" x="{{ $cx }}" y="{{ $cy - 2 }}" text-anchor="middle" dominant-baseline="central">{{ $pieTotal }}</text>
          <text class="donut-label" x="{{ $cx }}" y="{{ $cy + 24 }}" text-anchor="middle">TOTAL ORDERS</text>
        </svg>
        <div class="legend">
          @forelse ($statusStats as $s)
            @php $statusColor = $statusPalette[$s['status']] ?? '#CFC8BA'; @endphp
            <button class="legend-row status-filter" type="button" data-status-filter="{{ $s['status'] }}" aria-label="Filter recent orders by {{ $s['label'] }}" aria-pressed="false" aria-controls="recentOrdersBody">
              <span class="legend-name"><span class="legend-dot" style="background:{{ $statusColor }}" aria-hidden="true"></span><span class="legend-label">{{ $s['label'] }}</span></span>
              <span class="legend-values">{{ $s['count'] }}<strong>{{ $s['percent'] }}%</strong></span>
            </button>
          @empty
            <div class="empty-state">No orders.</div>
          @endforelse
        </div>
      </div>
    </section>
  </div>

  <div class="dashboard-row">
    <section class="panel panel-recent-orders">
      <header class="panel-head">
        <h2 class="panel-title with-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg> Recent Orders</h2>
        <a class="link" href="/admin/orders">View all <span aria-hidden="true">→</span></a>
      </header>
      <div class="order-filter-toolbar" id="orderFilterToolbar" hidden>
        <span class="filter-summary" id="orderFilterSummary" role="status" aria-live="polite"></span>
        <button class="clear-order-filter" id="clearOrderFilter" type="button">Clear filter</button>
      </div>
      <table class="orders-table" role="table" aria-label="Recent orders">
        <colgroup><col style="width:17%"><col style="width:22%"><col style="width:20%"><col style="width:16%"><col style="width:25%"></colgroup>
        <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Order</th><th role="columnheader" scope="col">Buyer</th><th role="columnheader" scope="col">Store</th><th role="columnheader" scope="col">Total</th><th role="columnheader" scope="col">Status</th></tr></thead>
        <tbody id="recentOrdersBody" role="rowgroup">
          @forelse ($recentOrders as $o)
            @php $buyerName = $o->buyer->name ?? 'Guest'; $storeName = $o->seller->store_name ?? '—'; @endphp
            <tr class="recent-order" data-order-status="{{ $o->status }}" role="row">
              <td class="order-cell" role="cell"><span class="order-no">#{{ $o->order_number }}</span><span class="order-date">{{ $o->created_at->format('M d') }}</span></td>
              <td class="buyer-cell" role="cell"><div class="buyer-profile"><span class="buyer-avatar" aria-hidden="true">{{ strtoupper(mb_substr($buyerName,0,1)) }}</span><span class="buyer-name" title="{{ $buyerName }}">{{ Str::limit($buyerName, 12) }}</span></div></td>
              <td class="store-cell" role="cell"><span class="store-name" title="{{ $storeName }}">{{ Str::limit($storeName, 14) }}</span></td>
              <td class="total-cell" role="cell">₱{{ number_format($o->total, 2) }}</td>
              <td class="status-cell" role="cell"><span class="pill pill-{{ $o->status }} status-chip status-{{ $o->status }}">@if ($o->status === 'delivered')<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>@else<span class="dot" aria-hidden="true"></span>@endif{{ $o->status === 'out_for_delivery' ? 'Out for delivery' : ucfirst($o->status) }}</span></td>
            </tr>
          @empty
            <tr role="row"><td class="empty-order" role="cell" colspan="5">No orders yet.</td></tr>
          @endforelse
          <tr class="filter-empty" role="row" hidden><td class="empty-order" role="cell" colspan="5">No recent orders match this status.</td></tr>
        </tbody>
      </table>
    </section>

    <section class="panel panel-top-products">
      <header class="panel-head">
        <div><h2 class="panel-title">Top Products</h2><p class="panel-sub">By units sold · this month</p></div>
        <span class="top-chip">Top 5</span>
      </header>
      @php $maxUnits = $topProducts->max('units') ?: 1; @endphp
      @forelse ($topProducts as $idx => $p)
        <div class="top-prod">
          <span class="rank">{{ $idx+1 }}</span>
          <div class="product-info">
            <div class="product-name" title="{{ $p->name }}">{{ $p->name }}</div>
            <div class="product-progress"><div class="bar"><div class="bar-fill" style="width:{{ round(($p->units / $maxUnits)*100) }}%;"></div></div><span class="units-sold">{{ $p->units }} sold</span></div>
          </div>
          <span class="product-price">₱{{ number_format($p->sales, 2) }}</span>
        </div>
      @empty
        <div class="empty-state">No sales yet.</div>
      @endforelse
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var dashboard = document.getElementById('adminDashboard');
    if (!dashboard) return;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function animateMetricValues() {
        if (prefersReducedMotion) return;
        dashboard.querySelectorAll('.sum-value').forEach(function (metric) {
            var textNode = Array.prototype.slice.call(metric.childNodes).find(function (node) {
                return node.nodeType === Node.TEXT_NODE && node.nodeValue.trim();
            });
            if (!textNode) return;
            var match = textNode.nodeValue.match(/^(\s*[^\d-]*)(-?[\d,]+(?:\.\d+)?)(\s*)$/);
            if (!match) return;
            var prefix = match[1], target = Number(match[2].replace(/,/g, '')), suffix = match[3];
            var decimals = (match[2].split('.')[1] || '').length;
            if (!Number.isFinite(target)) return;
            var start = performance.now(), duration = 680;
            function render(now) {
                var progress = Math.min(1, (now - start) / duration);
                var value = progress === 1 ? target : target * (1 - Math.pow(1 - progress, 3));
                textNode.nodeValue = prefix + value.toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}) + suffix;
                if (progress < 1) requestAnimationFrame(render);
            }
            textNode.nodeValue = prefix + (0).toLocaleString('en-US', {minimumFractionDigits: decimals, maximumFractionDigits: decimals}) + suffix;
            requestAnimationFrame(render);
        });
    }
    var metricCards = dashboard.querySelector('.cards');
    if (metricCards && window.IntersectionObserver) {
        var metricObserver = new IntersectionObserver(function (entries) {
            if (entries.some(function (entry) { return entry.isIntersecting; })) {
                animateMetricValues();
                metricObserver.disconnect();
            }
        }, {threshold: .15});
        metricObserver.observe(metricCards);
    } else {
        animateMetricValues();
    }

    var filterButtons = dashboard.querySelectorAll('[data-status-filter]');
    var recentRows = Array.prototype.slice.call(dashboard.querySelectorAll('.recent-order'));
    var filterEmpty = dashboard.querySelector('.filter-empty');
    var filterToolbar = dashboard.querySelector('#orderFilterToolbar');
    var filterSummary = dashboard.querySelector('#orderFilterSummary');
    var clearFilterButton = dashboard.querySelector('#clearOrderFilter');
    var donutSlices = dashboard.querySelectorAll('.donut-slice[data-order-status]');
    var activeStatus = null;
    function applyStatusFilter(status, label) {
        activeStatus = status;
        var visibleCount = 0;
        recentRows.forEach(function (row) {
            var visible = !status || row.getAttribute('data-order-status') === status;
            row.hidden = !visible;
            if (visible) {
                visibleCount++;
                row.classList.remove('is-entering');
                void row.offsetWidth;
                row.classList.add('is-entering');
            }
        });
        if (filterEmpty) filterEmpty.hidden = !status || visibleCount > 0 || recentRows.length === 0;
        if (filterToolbar) filterToolbar.hidden = !status;
        if (filterSummary && status) filterSummary.textContent = 'Showing ' + label + ': ' + visibleCount + ' recent order' + (visibleCount === 1 ? '' : 's');
        filterButtons.forEach(function (button) {
            var selected = button.getAttribute('data-status-filter') === status;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.classList.toggle('is-active', selected);
        });
        donutSlices.forEach(function (slice) {
            var selected = slice.getAttribute('data-order-status') === status;
            slice.classList.toggle('is-muted', Boolean(status) && !selected);
            slice.classList.toggle('is-active', Boolean(status) && selected);
        });
    }
    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var status = button.getAttribute('data-status-filter');
            var labelNode = button.querySelector('.legend-label');
            var label = labelNode ? labelNode.textContent.trim() : status;
            applyStatusFilter(activeStatus === status ? null : status, label);
        });
    });
    if (clearFilterButton) clearFilterButton.addEventListener('click', function () { applyStatusFilter(null, ''); });

    var salesChart = document.getElementById('salesChart');
    var salesSeries = @json($chart);
    if (!salesChart || !salesSeries.length) return;
    var ns = 'http://www.w3.org/2000/svg';
    var mobile = window.matchMedia('(max-width: 720px)');
    var chartFrame = salesChart.parentElement;
    var tooltip = dashboard.querySelector('#salesChartTooltip');
    var pointNodes = [];
    var activePoint = null;
    var hasDrawn = false;
    function createSvgNode(name, attributes, text) {
        var node = document.createElementNS(ns, name);
        Object.keys(attributes || {}).forEach(function (key) { node.setAttribute(key, attributes[key]); });
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function axisLabel(value) {
        if (value >= 1000) return (value / 1000).toLocaleString('en-US', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + 'k';
        return Math.round(value).toLocaleString('en-US');
    }
    function drawSalesChart() {
        var width = Math.max(180, Math.round(salesChart.getBoundingClientRect().width));
        var height = mobile.matches ? 220 : 280;
        var pad = {left: 70, right: 14, top: 16, bottom: 34};
        var plotWidth = Math.max(1, width - pad.left - pad.right);
        var plotHeight = height - pad.top - pad.bottom;
        var max = Math.max(1, Math.max.apply(null, salesSeries.map(function (point) { return Number(point.value); })));
        var pts = salesSeries.map(function (point, index) {
            return {x: pad.left + (salesSeries.length === 1 ? 0 : index / (salesSeries.length - 1) * plotWidth), y: pad.top + plotHeight - Number(point.value) / max * plotHeight, label: point.label, value: Number(point.value)};
        });
        salesChart.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        salesChart.setAttribute('height', height);
        salesChart.replaceChildren();
        pointNodes = [];
        [0, .25, .5, .75, 1].forEach(function (fraction) {
            var y = pad.top + plotHeight * fraction;
            var value = max * (1 - fraction);
            salesChart.appendChild(createSvgNode('text', {class: 'sales-tick', x: pad.left - 9, y: y + 4, 'text-anchor': 'end'}, '₱' + axisLabel(value)));
        });
        var labelCount = Math.min(5, Math.max(2, Math.floor(plotWidth / 48) + 1));
        var labelIndexes = [];
        for (var labelIndex = 0; labelIndex < labelCount; labelIndex++) {
            var pointIndex = Math.round(labelIndex * (pts.length - 1) / (labelCount - 1));
            if (labelIndexes.indexOf(pointIndex) < 0) labelIndexes.push(pointIndex);
        }
        labelIndexes.forEach(function (pointIndex) {
            var point = pts[pointIndex];
            salesChart.appendChild(createSvgNode('text', {class: 'sales-date-label', x: point.x, y: height - 8, 'text-anchor': pointIndex === 0 ? 'start' : (pointIndex === pts.length - 1 ? 'end' : 'middle')}, point.label));
        });
        var areaPoints = [pts[0].x + ',' + (pad.top + plotHeight)].concat(pts.map(function (point) { return point.x + ',' + point.y; })).concat([pts[pts.length - 1].x + ',' + (pad.top + plotHeight)]).join(' ');
        salesChart.appendChild(createSvgNode('polygon', {class: 'sales-area', points: areaPoints, 'aria-hidden': 'true'}));
        var line = createSvgNode('polyline', {class: 'sales-line', points: pts.map(function (point) { return point.x + ',' + point.y; }).join(' '), 'aria-hidden': 'true'});
        salesChart.appendChild(line);
        pts.forEach(function (point, index) {
            var marker = createSvgNode('circle', {class: 'sales-point', cx: point.x, cy: point.y, r: 3, 'aria-hidden': 'true', 'data-point-index': index});
            salesChart.appendChild(marker);
            pointNodes.push(marker);
        });
        if (!hasDrawn && !prefersReducedMotion && line.getTotalLength) {
            var lineLength = line.getTotalLength();
            line.style.strokeDasharray = lineLength;
            line.style.strokeDashoffset = lineLength;
            requestAnimationFrame(function () { line.style.strokeDashoffset = '0'; });
        }
        hasDrawn = true;
        if (activePoint !== null) showPoint(activePoint, document.activeElement === salesChart);
    }

    function showPoint(index, keyboard) {
        if (!pointNodes.length || !tooltip) return;
        activePoint = Math.max(0, Math.min(pointNodes.length - 1, index));
        pointNodes.forEach(function (marker, markerIndex) { marker.classList.toggle('is-active', markerIndex === activePoint); });
        var point = salesSeries[activePoint];
        var amount = Number(point.value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        tooltip.textContent = point.label + ' · ' + String.fromCharCode(0x20B1) + amount;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', keyboard ? 'false' : 'true');
        var markerRect = pointNodes[activePoint].getBoundingClientRect();
        var frameRect = chartFrame.getBoundingClientRect();
        var left = markerRect.left + markerRect.width / 2 - frameRect.left;
        tooltip.style.left = Math.max(80, Math.min(frameRect.width - 80, left)) + 'px';
        tooltip.style.top = Math.max(34, markerRect.top + markerRect.height / 2 - frameRect.top) + 'px';
        salesChart.setAttribute('aria-label', 'Sales on ' + point.label + ': ' + String.fromCharCode(0x20B1) + amount + '. Use the left and right arrow keys to inspect daily values.');
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');
            tooltip.textContent = '';
        }
        pointNodes.forEach(function (marker) { marker.classList.remove('is-active'); });
    }

    function inspectNearestPoint(clientX) {
        var nearestIndex = 0, nearestDistance = Infinity;
        pointNodes.forEach(function (marker, index) {
            var rect = marker.getBoundingClientRect();
            var distance = Math.abs(clientX - (rect.left + rect.width / 2));
            if (distance < nearestDistance) { nearestDistance = distance; nearestIndex = index; }
        });
        showPoint(nearestIndex, false);
    }

    salesChart.addEventListener('pointermove', function (event) { if (event.pointerType !== 'touch') inspectNearestPoint(event.clientX); });
    salesChart.addEventListener('pointerdown', function (event) { if (event.pointerType === 'touch') inspectNearestPoint(event.clientX); });
    salesChart.addEventListener('pointerleave', function (event) { if (event.pointerType !== 'touch' && document.activeElement !== salesChart) hideTooltip(); });
    salesChart.addEventListener('focus', function () { showPoint(activePoint === null ? 0 : activePoint, true); });
    salesChart.addEventListener('blur', hideTooltip);
    salesChart.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        var nextPoint = activePoint === null ? 0 : activePoint + (event.key === 'ArrowRight' ? 1 : -1);
        showPoint(Math.max(0, Math.min(salesSeries.length - 1, nextPoint)), true);
    });
    document.addEventListener('pointerdown', function (event) { if (event.pointerType === 'touch' && !chartFrame.contains(event.target)) hideTooltip(); });

    var resizeObserver = window.ResizeObserver ? new ResizeObserver(drawSalesChart) : null;
    if (resizeObserver) resizeObserver.observe(salesChart.parentElement);
    window.addEventListener('resize', drawSalesChart, {passive: true});
    if (mobile.addEventListener) mobile.addEventListener('change', drawSalesChart);
    else if (mobile.addListener) mobile.addListener(drawSalesChart);
    drawSalesChart();
})();
</script>
@endpush
