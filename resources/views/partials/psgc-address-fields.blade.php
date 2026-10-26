@php
    $prefix = $prefix ?? '';
    $uid = $uid ?? 'psgc_' . substr(md5($prefix . microtime(true) . random_int(0, 999999)), 0, 8);
    $values = $values ?? [];
    $fields = $fields ?? ['region', 'province', 'city', 'barangay'];
    $required = $required ?? ['region', 'province', 'city', 'barangay'];
    $controlClass = $controlClass ?? '';
    $controlStyle = $controlStyle ?? '';
    $labelClass = $labelClass ?? '';
    $labelStyle = $labelStyle ?? '';
    $colClass = $colClass ?? '';
    $regionFromProvince = $regionFromProvince ?? false;
    $labels = [
        'region' => $labelRegion ?? 'Region',
        'province' => $labelProvince ?? 'Province',
        'city' => $labelCity ?? 'City / Municipality',
        'barangay' => $labelBarangay ?? 'Barangay',
    ];
    $placeholders = [
        'region' => $placeholderRegion ?? 'Select region...',
        'province' => $placeholderProvince ?? 'Select province...',
        'city' => $placeholderCity ?? 'Select city / municipality...',
        'barangay' => $placeholderBarangay ?? 'Select barangay...',
    ];
    $fieldName = fn ($field) => $prefix === '' ? $field : $prefix . '[' . $field . ']';
    $fieldId = fn ($field) => $uid . '_' . $field;
    $savedValues = array_intersect_key($values, array_flip(['region', 'province', 'city', 'barangay']));
@endphp
@once
    <script src="{{ asset('frontend/js/psgc-address.js') }}"></script>
@endonce
@foreach ($fields as $field)
    <div class="{{ $colClass }}">
        <label class="{{ $labelClass }}" style="{{ $labelStyle }}" for="{{ $fieldId($field) }}">{{ $labels[$field] }}</label>
        <select
            class="{{ $controlClass }}"
            style="{{ $controlStyle }}"
            name="{{ $fieldName($field) }}"
            id="{{ $fieldId($field) }}"
            @if (in_array($field, $required, true)) required @endif
            @if ($field !== 'region') disabled @endif
        >
            <option value="">{{ $placeholders[$field] }}</option>
            @if (filled($savedValues[$field] ?? null))
                <option value="{{ $savedValues[$field] }}" selected>{{ $savedValues[$field] }}</option>
            @endif
        </select>
    </div>
@endforeach
<script>
    (function () {
        var config = {
            region: @json($fieldId('region')),
            province: @json($fieldId('province')),
            city: @json($fieldId('city')),
            barangay: @json($fieldId('barangay')),
            values: @json($savedValues),
            placeholders: @json($placeholders),
            regionFromProvince: @json((bool) $regionFromProvince)
        };
        function boot() {
            if (window.initPsgcAddress) {
                window.initPsgcAddress(config);
                return;
            }
            document.addEventListener('DOMContentLoaded', function () {
                if (window.initPsgcAddress) {
                    window.initPsgcAddress(config);
                }
            });
        }
        boot();
    })();
</script>
