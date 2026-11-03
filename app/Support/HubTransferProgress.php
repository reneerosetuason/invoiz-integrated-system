<?php

namespace App\Support;

use App\Models\HubTransfer;
use App\Models\HubTransferParcel;

/**
 * Single source of truth for manifest journey labels.
 *
 * Transfer-level status and parcel-level progress are deliberately separate:
 * a truck can arrive while every parcel is still awaiting destination-hub
 * verification, and a completed trip may contain a parcel that was recorded
 * missing. Both the logistics web console and the Hub Transport Driver app
 * render these labels so the two surfaces cannot drift apart.
 */
class HubTransferProgress
{
    public const PARCEL_LABELS = [
        'added' => 'Added to manifest',
        'loaded' => 'Loaded / verified',
        'dispatched' => 'Dispatched with transfer',
        'arrived' => 'Arrived with transfer',
        'received' => 'Received and verified',
        'received_damaged' => 'Received with damage',
        'missing' => 'Missing at receiving',
        'removed' => 'Removed from manifest',
    ];

    public static function parcelStage(HubTransfer $transfer, HubTransferParcel $entry): array
    {
        $status = self::parcelStatus($transfer, $entry);

        return [
            'status' => $status,
            'label' => self::PARCEL_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        ];
    }

    public static function parcelStatus(HubTransfer $transfer, HubTransferParcel $entry): string
    {
        if ($entry->loading_status === 'removed') {
            return 'removed';
        }

        if ($entry->receiving_status === 'received') {
            return $entry->condition === 'damaged' ? 'received_damaged' : 'received';
        }

        if ($entry->receiving_status === 'missing') {
            return 'missing';
        }

        // Arrival is a physical milestone; it must never imply that the
        // destination hub verified the parcel. Only receiving_status does.
        if (in_array($transfer->status, ['arrived', 'receiving', 'exception', 'received'], true)
            || $transfer->destination_arrived_at !== null) {
            return 'arrived';
        }

        if (in_array($transfer->status, ['in_transit'], true)) {
            return 'dispatched';
        }

        if ($entry->loading_status === 'loaded') {
            return 'loaded';
        }

        return 'added';
    }

    public static function transferLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'loading' => 'Loading',
            'ready_for_dispatch' => 'Ready for Dispatch',
            'in_transit' => 'In Transit',
            'arrived' => 'Arrived',
            'receiving' => 'Receiving',
            'received' => 'Received / Completed',
            'exception' => 'Exception',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
