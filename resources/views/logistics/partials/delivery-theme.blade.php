@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/logistics/css/logistics-deliveries.css') }}?v={{ @filemtime(public_path('assets/logistics/css/logistics-deliveries.css')) ?: 1 }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/logistics/js/logistics-deliveries.js') }}?v={{ @filemtime(public_path('assets/logistics/js/logistics-deliveries.js')) ?: 1 }}" defer></script>
@endpush
