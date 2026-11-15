<?php

namespace App\Support;

use App\Models\HubTransferEvent;
use Illuminate\Support\Str;

/** Readable event text; the stored audit record is never changed. */
final class TransferHistory
{
    public static function describe(HubTransferEvent $event): array
    {
        $notes = trim((string) $event->notes);
        $snapshotText = $notes;
        $reason = '';
        if ($event->event_type === 'payment_reversed' && str_contains($notes, ' Snapshot:')) {
            [$reason, $snapshotText] = explode(' Snapshot:', $notes, 2);
        }
        $snapshot = json_decode(trim($snapshotText), true);
        $structured = is_array($snapshot);

        if ($event->event_type === 'payment_reserved') {
            $amount = $structured && is_numeric($snapshot['total'] ?? null) && is_finite((float) $snapshot['total'])
                ? 'PHP '.number_format($snapshot['total'] / 100, 2) : 'Trip payment';
            $parcels = $structured && is_array($snapshot['parcels'] ?? null) ? $snapshot['parcels'] : [];
            $count = $structured ? (int) ($snapshot['parcel_count'] ?? count($parcels)) : 0;
            $summary = $amount.' reserved'.($count > 0 ? ' for '.$count.' parcel'.($count === 1 ? '' : 's') : '').'.';
        } elseif ($event->event_type === 'payment_reversed') {
            $reason = trim($reason ?: ($structured ? '' : $notes));
            $summary = 'Payment reservation reversed'.($reason !== '' ? ': '.Str::limit($reason, 120) : '.');
        } else {
            $summary = $structured ? 'Event details recorded.' : Str::limit($notes, 160);
        }

        $details = null;
        if ($structured) {
            $details = ($reason !== '' ? $reason."\n\n" : '').json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($notes !== $summary && $notes !== '') {
            $details = $notes;
        }

        return ['summary' => $summary, 'details' => $details];
    }
}
