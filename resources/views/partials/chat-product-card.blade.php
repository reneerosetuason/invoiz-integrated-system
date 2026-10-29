@if(is_array($card ?? null))
    @php
        $imagePath = trim((string) ($card['image'] ?? ''));
        $imageUrl = null;
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } elseif ($imagePath !== '' && ! str_contains($imagePath, ':') && ! str_contains($imagePath, '..')) {
            $imageUrl = asset('storage/'.ltrim(preg_replace('#^(?:public/)?storage/#', '', $imagePath), '/'));
        }
    @endphp
    <a href="{{ url('/product/'.(int) $card['id']) }}" data-product-card="{{ (int) $card['id'] }}"
       style="display:flex;align-items:flex-start;gap:10px;padding:10px;margin-bottom:8px;border:1px solid #e1e7e5;border-radius:12px;background:#faf9f6;color:#173f49;text-decoration:none;min-width:0;">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $card['name'] }}" width="48" height="58"
                 style="width:48px;height:58px;flex-shrink:0;object-fit:cover;border-radius:8px;"
                 onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            <span hidden aria-label="Product photo unavailable" style="width:48px;min-height:58px;background:#eeeae4;border-radius:8px;flex-shrink:0;"></span>
        @else
            <span aria-label="Product photo unavailable" style="width:48px;min-height:58px;background:#eeeae4;border-radius:8px;flex-shrink:0;"></span>
        @endif
        <span style="min-width:0;overflow-wrap:anywhere;">
            <span style="display:block;font-size:13px;font-weight:600;line-height:1.4;">{{ $card['name'] }}</span>
            @if(! empty($card['option']))
                <span style="display:block;margin-top:3px;font-size:12px;color:#52696e;">{{ $card['option'] }}</span>
            @endif
            <span style="display:block;margin-top:4px;font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;">₱{{ number_format((float) $card['price'], 2) }}</span>
        </span>
    </a>
@endif
