@php
    $previous = old('variants');
    $variantFormRows = $product ? $product->variants->map(fn($v) => ['id' => $v->id, 'type' => $v->variant_type, 'value' => $v->variant_value, 'adjustment' => $v->price_adjustment, 'stock' => $v->stock])->all() : [];
    if (is_array($previous)) {
        $variantFormRows = [];
        foreach ($previous['type'] ?? [] as $i => $type) {
            $variantFormRows[] = ['id' => $previous['id'][$i] ?? null, 'type' => $type, 'value' => $previous['value'][$i] ?? '', 'adjustment' => $previous['adjustment'][$i] ?? 0, 'stock' => $previous['stock'][$i] ?? 0];
        }
    }
@endphp
<script src="{{ asset('assets/seller/js/products/'.$script.'.js') }}?v={{ filemtime(public_path('assets/seller/js/products/'.$script.'.js')) }}" data-config="{{ json_encode([$variantFormRows, (int) old('stock', $product?->stock ?? 0)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
