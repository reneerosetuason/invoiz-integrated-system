<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.4;
            margin: 0;
            padding: 0 24px;
        }
        .header {
            border-bottom: 3px solid #16697A;
            padding: 16px 0 12px;
            margin-bottom: 14px;
        }
        .brand { font-size: 16px; font-weight: 700; color: #16697A; letter-spacing: 0.5px; }
        .brand small { display: block; font-size: 10px; font-weight: 400; color: #6b7280; letter-spacing: 0; }
        .title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin-top: 6px;
        }
        .meta {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .meta td { padding: 3px 0; font-size: 10.5px; color: #374151; vertical-align: top; }
        .meta .k { color: #6b7280; width: 1%; padding-right: 14px; white-space: nowrap; font-weight: 600; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data th {
            background: #16697A;
            color: #ffffff;
            text-align: left;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 6px 8px;
            border: 1px solid #16697A;
        }
        table.data th.r, table.data td.r { text-align: right; }
        table.data td {
            padding: 5px 8px;
            border: 1px solid #d1d5db;
            font-size: 10.5px;
        }
        table.data tbody tr:nth-child(even) td { background: #f7f8fa; }
        table.data tfoot td {
            font-weight: 700;
            background: #eef3f5;
            border-top: 2px solid #16697A;
        }

        .cards { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .cards td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: center;
        }
        .cards .lbl { display: block; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; font-weight: 600; margin-bottom: 2px; }
        .cards .val { display: block; font-size: 13px; font-weight: 700; color: #111827; }

        .summary { margin-top: 12px; }
        .summary h3 { font-size: 11px; color: #16697A; font-weight: 700; margin: 10px 0 4px; }
        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 8.5px;
            color: #6b7280;
            text-align: center;
        }
        .footer .pagenum:after { content: "Page " counter(page) " of " counter(pages); }
        .empty { padding: 14px; text-align: center; color: #6b7280; border: 1px solid #d1d5db; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">INVOIZ LOGISTICS SYSTEM</div>
        <div class="title">{{ $title }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="k">Generated</td>
            <td>{{ $generatedAt }}</td>
            <td class="k">Prepared by</td>
            <td>{{ $preparedBy }}</td>
        </tr>
        <tr>
            <td class="k">Reporting period</td>
            <td>
                @if($dateFrom || $dateTo)
                    {{ $dateFrom ?: 'Start of records' }} &rarr; {{ $dateTo ?: 'Present' }}
                @else
                    All time
                @endif
            </td>
            <td class="k">Center</td>
            <td>{{ $centerName ?: 'All Centers' }}</td>
        </tr>
    </table>

    {{-- Delivery --}}
    @if($tab === 'delivery')
        @php
            $deliveryRows = [
                ['Total deliveries', $stats['total'] ?? 0],
                ['Received', $stats['received'] ?? 0],
                ['Scanned', $stats['scanned'] ?? 0],
                ['Sorted', $stats['sorted'] ?? 0],
                ['Waiting for rider', $stats['waiting_for_rider'] ?? 0],
                ['Assigned', $stats['assigned'] ?? 0],
                ['Picked up', $stats['picked_up'] ?? 0],
                ['Out for delivery', $stats['out_for_delivery'] ?? 0],
                ['Delivered', $stats['delivered'] ?? 0],
                ['Failed', $stats['failed'] ?? 0],
                ['Cancelled', $stats['cancelled'] ?? 0],
            ];
        @endphp
        <table class="data">
            <thead>
                <tr><th>Status</th><th class="r">Count</th></tr>
            </thead>
            <tbody>
                @foreach($deliveryRows as [$k, $v])
                    <tr><td>{{ $k }}</td><td class="r">{{ $v }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td>Total</td><td class="r">{{ $stats['total'] ?? 0 }}</td></tr>
            </tfoot>
        </table>
    @endif

    {{-- Center --}}
    @if($tab === 'center')
        @php $totalD = $totalA = $totalR = 0; @endphp
        @if(count($stats))
            <table class="data">
                <thead>
                    <tr>
                        <th>Center</th>
                        <th>City</th>
                        <th class="r">Deliveries</th>
                        <th class="r">Service Areas</th>
                        <th class="r">Riders</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats as $c)
                        @php
                            $d = $c['deliveries_count'] ?? 0; $a = $c['service_areas_count'] ?? 0; $r = $c['riders_count'] ?? 0;
                            $totalD += $d; $totalA += $a; $totalR += $r;
                        @endphp
                        <tr>
                            <td>{{ $c['name'] }}</td>
                            <td>{{ $c['city'] ?? '—' }}</td>
                            <td class="r">{{ $d }}</td>
                            <td class="r">{{ $a }}</td>
                            <td class="r">{{ $r }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="r">{{ $totalD }}</td>
                        <td class="r">{{ $totalA }}</td>
                        <td class="r">{{ $totalR }}</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="empty">No centers found.</div>
        @endif
    @endif

    {{-- Service Area --}}
    @if($tab === 'area')
        @php $totalD = $totalR = 0; @endphp
        @if(count($stats))
            <table class="data">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Center</th>
                        <th class="r">Deliveries</th>
                        <th class="r">Riders</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats as $a)
                        @php
                            $d = $a['deliveries_count'] ?? 0; $r = $a['riders_count'] ?? 0;
                            $totalD += $d; $totalR += $r;
                            $center = \App\Models\ServiceArea::find($a['id'])?->logisticsCenter?->name ?? '—';
                        @endphp
                        <tr>
                            <td>{{ $a['name'] }}</td>
                            <td>{{ $center }}</td>
                            <td class="r">{{ $d }}</td>
                            <td class="r">{{ $r }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="r">{{ $totalD }}</td>
                        <td class="r">{{ $totalR }}</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="empty">No service areas found.</div>
        @endif
    @endif

    {{-- Rider --}}
    @if($tab === 'rider')
        @php $totalD = $totalA = $totalC = 0; @endphp
        @if(count($stats))
            <table class="data">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Vehicle</th>
                        <th class="r">Total</th>
                        <th class="r">Active</th>
                        <th class="r">Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats as $r)
                        @php
                            $d = $r['total_deliveries'] ?? 0; $a = $r['active_deliveries'] ?? 0; $c = $r['completed_deliveries'] ?? 0;
                            $totalD += $d; $totalA += $a; $totalC += $c;
                        @endphp
                        <tr>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ ucfirst($r['vehicle_type'] ?? '—') }}</td>
                            <td class="r">{{ $d }}</td>
                            <td class="r">{{ $a }}</td>
                            <td class="r">{{ $c }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="r">{{ $totalD }}</td>
                        <td class="r">{{ $totalA }}</td>
                        <td class="r">{{ $totalC }}</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <div class="empty">No riders found.</div>
        @endif
    @endif

    {{-- Financial --}}
    @if($tab === 'financial')
        @php
            $currency = fn ($v) => '₱' . number_format((float) ($v ?? 0), 2);
        @endphp
        <table class="cards">
            <tr>
                <td><span class="lbl">Transactions</span><span class="val">{{ number_format((int) ($stats['total_transactions'] ?? 0)) }}</span></td>
                <td><span class="lbl">Total Amount</span><span class="val">{{ $currency($stats['total_amount'] ?? 0) }}</span></td>
                <td><span class="lbl">Rider Fees</span><span class="val">{{ $currency($stats['total_rider_fees'] ?? 0) }}</span></td>
                <td><span class="lbl">Commissions</span><span class="val">{{ $currency($stats['total_commissions'] ?? 0) }}</span></td>
            </tr>
        </table>
        <div class="summary">
            <h3>Completed Transactions Breakdown</h3>
            <table class="data">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="r">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Completed Amount</td><td class="r">{{ $currency($stats['completed_amount'] ?? 0) }}</td></tr>
                    <tr><td>Completed Rider Fees</td><td class="r">{{ $currency($stats['completed_rider_fees'] ?? 0) }}</td></tr>
                    <tr><td>Completed Commissions</td><td class="r">{{ $currency($stats['completed_commissions'] ?? 0) }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <span>INVOIZ LOGISTICS SYSTEM</span>
        &nbsp;&middot;&nbsp;
        <span class="pagenum"></span>
    </div>
</body>
</html>
