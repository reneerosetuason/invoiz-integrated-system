@php
  $rawStatus = (string) ($status ?? 'pending');
  $normalizedStatus = strtolower(str_replace(' ', '_', $rawStatus));
  $statusKind = $kind ?? 'fulfillment';
  $statusLabel = ucfirst(str_replace('_', ' ', $rawStatus));

  if ($statusKind === 'payment') {
    [$tone, $icon] = match ($normalizedStatus) {
      'paid' => ['teal', 'check-circle'],
      'pending' => ['neutral', 'clock'],
      'failed' => ['brick', 'x'],
      'refunded' => ['brick', 'refund'],
      default => ['neutral', 'clock'],
    };
  } else {
    [$tone, $icon] = match ($normalizedStatus) {
      'new', 'pending' => ['neutral', 'dot'],
      'processing' => ['teal', 'dot'],
      'shipped' => ['teal', 'truck'],
      'out_for_delivery' => ['mid', 'truck'],
      'delivered' => ['deep', 'check'],
      'cancelled' => ['brick', 'x'],
      default => ['neutral', 'dot'],
    };
  }
@endphp
<span class="order-status-chip order-status-chip--{{ $statusKind }} order-status-chip--{{ $tone }} {{ !empty($large) ? 'order-status-chip--large' : '' }}" data-status="{{ $normalizedStatus }}">
  @if($icon === 'dot')
    <span class="order-status-dot" aria-hidden="true"></span>
  @else
    <svg class="order-status-icon" viewBox="0 0 20 20" aria-hidden="true">
      @if($icon === 'check-circle')
        <circle cx="10" cy="10" r="7"/><path d="m6.5 10 2.2 2.2 4.8-4.8"/>
      @elseif($icon === 'check')
        <path d="m4 10 4 4 8-8"/>
      @elseif($icon === 'clock')
        <circle cx="10" cy="10" r="7"/><path d="M10 6v4l2.5 1.5"/>
      @elseif($icon === 'truck')
        <path d="M2.5 5.5h9v8h-9zM11.5 8h3l3 3v2.5h-6z"/><circle cx="6" cy="15" r="1.6"/><circle cx="14.5" cy="15" r="1.6"/>
      @elseif($icon === 'refund')
        <path d="M5 6H2.5V3.5M3 6a7 7 0 1 1-.5 7"/><path d="M10 6.5v7M12.5 8.2c-.3-.8-1.1-1.2-2.2-1.2-1.2 0-2 .5-2 1.4 0 2.2 4.2 1 4.2 3.2 0 .9-.8 1.5-2 1.5-1.1 0-2-.5-2.3-1.3"/>
      @else
        <path d="m5 5 10 10M15 5 5 15"/>
      @endif
    </svg>
  @endif
  <span>{{ $statusLabel }}</span>
</span>
