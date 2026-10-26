@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/logistics-deliveries.css') }}?v={{ filemtime(public_path('css/logistics-deliveries.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/logistics-deliveries.js') }}?v={{ filemtime(public_path('js/logistics-deliveries.js')) }}" defer></script>
@endpush
