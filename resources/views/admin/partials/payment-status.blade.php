@php
    $statusTone = match($paymentStatus) {
        'successful', 'accepted' => 'success',
        'failed', 'voided' => 'danger',
        'pending', 'pending_remittance' => 'pending',
        'remitted' => 'sent',
        default => 'neutral',
    };
    $statusLabel = match($paymentStatus) {
        'held_in_escrow' => 'Held',
        'pending_remittance' => 'Ready',
        'remitted' => '✓ Remitted',
        'accepted' => '✓ Received',
        default => ucwords(str_replace('_', ' ', $paymentStatus)),
    };
@endphp
<span class="po-badge po-badge-{{ $statusTone }}" title="{{ ucwords(str_replace('_', ' ', $paymentStatus)) }}">{{ $statusLabel }}</span>
