@php($chatHolder = \App\Support\ParcelChat::holder($parcel))
@if($chatHolder)
    <a href="{{ route('messaging.index', ['parcel' => $parcel->id]) }}" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;margin:4px 0;">
        {{ $chatHolder['label'] }} · {{ $chatHolder['name'] }}
    </a>
@endif
