<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\User;
use App\Services\FulfillmentWorkflow;

/**
 * Single resolver for "where is this parcel and what is the one next step?".
 *
 * The logistics console, the operations workspace and the parcel detail page
 * all read the same Delivery/manifest rows. This class only describes that
 * state; it never writes. Every action it offers points at an existing page
 * where the authoritative controller/service still performs the mutation and
 * re-checks authorization.
 */
class ParcelWorkflow
{
    public const LABELS = [
        'awaiting_handoff' => 'Awaiting Pickup Handoff',
        'awaiting_receipt' => 'Handed Off — Awaiting Hub Receipt',
        'preparing_transfer' => 'Preparing Transfer',
        'in_transit' => 'In Transit',
        'arrived_awaiting_receiving' => 'Arrived — Awaiting Receiving',
        'received_good' => 'Received — Good',
        'received_damaged' => 'Received — Damaged',
        'missing' => 'Missing — Investigation Required',
        'ready_for_sorting' => 'Ready for Sorting',
        'scanned' => 'Scanned — Ready for Sorting',
        'sorted' => 'Sorted',
        'ready_for_rider' => 'Ready for Rider Assignment',
        'rider_assigned' => 'Rider Assigned',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'archived' => 'Archived',
    ];

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     tone: string,
     *     custody: string,
     *     next: array{key: string, label: string, url: ?string, enabled: bool, reason: ?string},
     *     transfer: ?HubTransfer
     * }
     */
    public static function describe(Delivery $delivery, ?HubTransferParcel $entry = null, ?User $user = null): array
    {
        $entry ??= self::manifestEntry($delivery);
        $transfer = $entry?->transfer;

        if ($delivery->archived_at !== null) {
            return self::result('archived', 'gray', 'Archived', self::none(), $transfer);
        }
        if ($delivery->status === 'cancelled') {
            return self::result('cancelled', 'gray', 'Cancelled', self::none(), $transfer);
        }
        if ($delivery->status === 'delivered' || $delivery->delivered_at !== null) {
            return self::result('delivered', 'green', self::label('delivered'), self::openParcel($delivery, $user, 'Delivered'), $transfer);
        }
        if ($delivery->status === 'lost' || $entry?->receiving_status === 'missing' || $entry?->condition === 'missing') {
            $next = $transfer
                ? self::openTransfer($delivery, $transfer, $user, 'Open transfer')
                : self::openParcel($delivery, $user, 'Open parcel');

            return self::result('missing', 'red', self::label('missing'), $next, $transfer);
        }

        if ($transfer) {
            $incoming = in_array($transfer->status, ['in_transit', 'arrived', 'receiving', 'exception'], true);
            $pendingReceiving = $entry && $entry->loading_status !== 'removed' && $entry->receiving_status === 'pending';

            if ($transfer->status === 'in_transit' || ($incoming && $entry && $entry->loading_status === 'loaded' && $transfer->status === 'in_transit')) {
                return self::result('in_transit', 'indigo', self::label('in_transit'), self::openTransfer($delivery, $transfer, $user, 'Open transfer'), $transfer);
            }

            if ($incoming && $pendingReceiving) {
                $next = self::openTransfer($delivery, $transfer, $user, 'Receive & inspect');
                if ($next['enabled'] && ! self::canProcessDestination($user, $transfer)) {
                    $next['enabled'] = false;
                    $next['reason'] = 'Receiving is performed by staff at the destination hub.';
                }

                return self::result('arrived_awaiting_receiving', 'amber', self::label('arrived_awaiting_receiving'), $next, $transfer);
            }
        }

        if (in_array($delivery->parcel_status, ['pending_arrival', null, ''], true)) {
            if ($delivery->order_id && ! FulfillmentWorkflow::hasPickupHandoff($delivery)) {
                return self::result(
                    'awaiting_handoff',
                    'amber',
                    self::label('awaiting_handoff'),
                    self::blocked('Pickup rider must complete the pickup and record the hub handoff.'),
                    $transfer,
                );
            }

            return self::result(
                'awaiting_receipt',
                'amber',
                self::label('awaiting_receipt'),
                self::receiveAtHub($delivery, $user),
                $transfer,
            );
        }

        if ($delivery->parcel_status === 'received') {
            $key = $entry?->condition === 'damaged' ? 'received_damaged' : 'received_good';

            return self::result($key, $entry?->condition === 'damaged' ? 'red' : 'teal', self::label($key), self::scan($delivery, $user), $transfer);
        }

        if ($delivery->parcel_status === 'scanned') {
            return self::result('scanned', 'teal', self::label('scanned'), self::sort($delivery, $user), $transfer);
        }

        if ($delivery->status === 'out_for_delivery') {
            return self::result('out_for_delivery', 'indigo', self::label('out_for_delivery'), self::openParcel($delivery, $user, 'Open delivery'), $transfer);
        }

        if ($delivery->rider_id
            || in_array($delivery->status, Delivery::ACTIVE_STATUSES, true)
            || in_array($delivery->status, ['assigned', 'accepted'], true)) {
            return self::result('rider_assigned', 'indigo', self::label('rider_assigned'), self::openParcel($delivery, $user, 'Open delivery'), $transfer);
        }

        if ($delivery->parcel_status === 'sorted') {
            if ($delivery->destination_center_id
                && (int) $delivery->destination_center_id !== (int) $delivery->center_id) {
                return self::result('preparing_transfer', 'amber', self::label('preparing_transfer'), self::createTransfer($delivery, $user), $transfer);
            }

            return self::result('ready_for_rider', 'teal', self::label('ready_for_rider'), self::assignRider($delivery, $user), $transfer);
        }

        if ($delivery->parcel_status === 'dispatched') {
            return self::result('in_transit', 'indigo', self::label('in_transit'), self::openParcel($delivery, $user, 'Open parcel'), $transfer);
        }

        return self::result(
            'ready_for_sorting',
            'teal',
            self::label('ready_for_sorting'),
            self::openParcel($delivery, $user, 'Open parcel'),
            $transfer,
        );
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function toneFor(?string $label): string
    {
        return match ($label) {
            self::LABELS['missing'], self::LABELS['received_damaged'] => 'red',
            self::LABELS['arrived_awaiting_receiving'], self::LABELS['preparing_transfer'],
            self::LABELS['awaiting_handoff'], self::LABELS['awaiting_receipt'] => 'amber',
            self::LABELS['in_transit'], self::LABELS['rider_assigned'], self::LABELS['out_for_delivery'] => 'indigo',
            self::LABELS['delivered'] => 'green',
            self::LABELS['cancelled'], self::LABELS['archived'] => 'gray',
            default => 'teal',
        };
    }

    public static function manifestEntry(Delivery $delivery): ?HubTransferParcel
    {
        if ($delivery->relationLoaded('latestHubTransferParcel')) {
            return $delivery->latestHubTransferParcel;
        }

        return $delivery->hubTransferParcels()->with('transfer')->latest('id')->first();
    }

    private static function result(string $key, string $tone, string $label, array $next, ?HubTransfer $transfer): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'tone' => $tone,
            'custody' => $transfer
                ? 'Fleet transfer '.$transfer->reference.' — '.HubTransferProgress::transferLabel($transfer->status)
                : 'Hub custody',
            'next' => $next,
            'transfer' => $transfer,
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function none(): array
    {
        return ['key' => 'none', 'label' => 'No action required', 'url' => null, 'enabled' => false, 'reason' => null];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function blocked(string $reason): array
    {
        return ['key' => 'blocked', 'label' => 'Waiting on pickup rider', 'url' => null, 'enabled' => false, 'reason' => $reason];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function openParcel(Delivery $delivery, ?User $user, string $label): array
    {
        $enabled = $user === null || LogisticsAccess::canAccessDelivery($user, $delivery, true);

        return [
            'key' => 'open_parcel',
            'label' => $label,
            'url' => route('deliveries.show', $delivery),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'This parcel belongs to another hub.',
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function openTransfer(Delivery $delivery, HubTransfer $transfer, ?User $user, string $label): array
    {
        return [
            'key' => 'open_transfer',
            'label' => $label,
            'url' => route('hub-transfers.show', $transfer),
            'enabled' => true,
            'reason' => null,
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function receiveAtHub(Delivery $delivery, ?User $user): array
    {
        $enabled = self::canProcessAt($user, $delivery->center_id);

        return [
            'key' => 'receive',
            'label' => 'Receive at hub',
            'url' => route('deliveries.show', $delivery),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'Receiving is performed by staff assigned to this hub.',
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function scan(Delivery $delivery, ?User $user): array
    {
        $enabled = self::canProcessAt($user, $delivery->center_id);

        return [
            'key' => 'scan',
            'label' => 'Scan parcel',
            'url' => route('deliveries.show', $delivery),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'Scanning is performed by staff assigned to the hub holding this parcel.',
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function sort(Delivery $delivery, ?User $user): array
    {
        $enabled = self::canProcessAt($user, $delivery->center_id);

        return [
            'key' => 'sort',
            'label' => 'Sort parcel',
            'url' => route('deliveries.show', $delivery),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'Sorting is performed by staff assigned to the hub holding this parcel.',
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function createTransfer(Delivery $delivery, ?User $user): array
    {
        $enabled = self::canProcessAt($user, $delivery->center_id);

        return [
            'key' => 'create_transfer',
            'label' => 'Add to Hub Transfer',
            'url' => route('hub-transfers.create', [
                'origin_center_id' => $delivery->center_id,
                'parcel' => $delivery->id,
            ]),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'Manifests are created by staff at the origin hub.',
        ];
    }

    /** @return array{key: string, label: string, url: ?string, enabled: bool, reason: ?string} */
    private static function assignRider(Delivery $delivery, ?User $user): array
    {
        $enabled = $user === null || ($user->canManageRiders() && LogisticsAccess::canAccessDelivery($user, $delivery, true));

        return [
            'key' => 'assign_rider',
            'label' => 'Assign rider',
            'url' => route('deliveries.show', $delivery),
            'enabled' => $enabled,
            'reason' => $enabled ? null : 'Rider assignment is performed by an authorized logistics manager.',
        ];
    }

    private static function canProcessAt(?User $user, ?int $centerId): bool
    {
        if ($user === null) {
            return false;
        }

        return LogisticsAccess::canProcessParcels($user)
            && $centerId !== null
            && (int) $user->center_id === (int) $centerId;
    }

    private static function canProcessDestination(?User $user, HubTransfer $transfer): bool
    {
        return self::canProcessAt($user, $transfer->destination_center_id);
    }
}
