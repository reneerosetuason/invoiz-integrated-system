<x-app-layout>
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/logistics/css/logistics-dashboard.css') }}?v={{ filemtime(public_path('assets/logistics/css/logistics-dashboard.css')) }}">
    @endpush
    @php
        $dateQuery = array_filter(['date_from' => $dateFrom ?: null, 'date_to' => $dateTo ?: null]);
        $statusMap = array_flip($status_labels);
        $statusPalette = ['waiting_for_rider'=>'#8B620A','assigned'=>'#4F888D','accepted'=>'#5C9295','going_to_pickup'=>'#63868B','arrived_at_shop'=>'#7B9B9C','picked_up'=>'#367D88','out_for_delivery'=>'#16697A','arrived_at_customer'=>'#316770','delivered'=>'#287660','delivery_failed'=>'#A2373A','lost'=>'#7D3337','damaged'=>'#AC5E20','returned_to_seller'=>'#758080','cancelled'=>'#99ABAB'];
        $statusChart['colors'] = array_map(fn ($label) => $statusPalette[$statusMap[$label] ?? ''] ?? '#52676A', $statusChart['labels']);
        $trendsChart['colors'] = ['created'=>'#16697A','delivered'=>'#287660','failed'=>'#A2373A'];
    @endphp
    @include('logistics.partials.dashboard-content')

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="{{ asset('assets/logistics/js/dashboard.js') }}" data-config="{{ json_encode([($trendsRange), ($trendsRange === 30 ? 'Last 30 days' : 'Last 7 days'), ($statusChart), ($dateQuery), (route('deliveries.index')), ($trendsChart), (route('deliveries.index')), (route('deliveries.index')), ($centerPerformanceChart), (route('deliveries.index')), ($statusChart), ($trendsChart), ($centerPerformanceChart), ($detailedRecords), (collect($status_labels)->map(fn($v,$k)=>['value'=>$k,'label'=>$v])->values()), ($centerPerformanceChart['labels'] ? collect($centerPerformanceChart['labels'])->map(fn($l)=>['value'=>$l,'label'=>$l])->values() : [])], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
    @endpush
</x-app-layout>
