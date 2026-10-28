<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ public_path('assets/logistics/css/reports/pdf.css') }}">
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
