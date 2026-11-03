<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\FulfillmentWorkflow;
use Illuminate\Support\Carbon;

/**
 * Customer-facing (seller/buyer) view of a parcel's lifecycle.
 *
 * Internal hub statuses are only reported once custody is proven by a
 * recorded pickup and hub handoff. A parcel whose parcel_status was advanced
 * without that evidence keeps showing its truthful pickup stage instead of
 * claiming it reached a hub.
 */
class CustomerTracking
{
    public const AUDIENCE_BUYER = 'buyer';

    /**
     * Internal rider progress inside the pickup leg. Sellers watch these
     * steps; buyers only need the delivery-level milestones.
     */
    private const BUYER_HIDDEN_CATEGORIES = ['rider_accepted', 'going_to_pickup', 'arrived_at_shop'];

    public static function verifiedCustody(Delivery $delivery): bool
    {
        return $delivery->order_id === null || FulfillmentWorkflow::hasPickupHandoff($delivery);
    }

    public static function orderLabel(Order $order): string
    {
        return match ($order->status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'ready_for_delivery' => 'Ready for Pickup',
            'shipped' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => order_status_label($order->status),
        };
    }

    public static function label(Order $order, ?Delivery $delivery = null, string $audience = 'seller'): string
    {
        $delivery ??= $order->delivery;

        if ($order->status === 'cancelled') {
            return 'Cancelled';
        }

        if ($delivery) {
            $terminal = match ($delivery->status) {
                'delivered' => 'Delivered',
                'lost' => 'Lost in Transit',
                'damaged' => 'Damaged',
                'delivery_failed' => 'Delivery Failed',
                'returned_to_seller' => 'Returned to Seller',
                default => null,
            };
            if ($terminal !== null) {
                return $terminal;
            }
        }

        if (! $delivery) {
            return self::orderLabel($order);
        }

        if (self::verifiedCustody($delivery)) {
            if (in_array($delivery->status, ['out_for_delivery', 'arrived_at_customer'], true)) {
                return 'Out for Delivery';
            }
            if ($delivery->parcel_status === 'dispatched') {
                return $delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id
                    ? 'In Transit to Destination Hub'
                    : 'Out for Delivery';
            }
            if ($delivery->parcel_status === 'sorted') {
                return 'Sorted at Logistics Hub';
            }
            if ($delivery->parcel_status === 'scanned') {
                return 'Processing at Logistics Hub';
            }
            if ($delivery->parcel_status === 'received') {
                return 'Received at Origin Hub';
            }
        }

        if ($delivery->picked_up_at !== null) {
            return 'Picked Up — En Route to Hub';
        }
        if ($delivery->status === 'going_to_pickup') {
            return $audience === self::AUDIENCE_BUYER ? 'Rider Assigned for Pickup' : 'Rider En Route to Seller';
        }
        if ($delivery->status === 'arrived_at_shop') {
            return $audience === self::AUDIENCE_BUYER ? 'Rider Assigned for Pickup' : 'Rider Arrived at Seller';
        }
        if ($delivery->status === 'accepted') {
            return $audience === self::AUDIENCE_BUYER ? 'Rider Assigned for Pickup' : 'Rider Accepted Pickup';
        }
        if ($delivery->rider_id !== null || $delivery->status === 'assigned') {
            return 'Rider Assigned for Pickup';
        }
        if ($delivery->pickupRequest?->status === 'approved') {
            return 'Pickup Approved — Awaiting Rider';
        }
        if ($delivery->pickupRequest?->status === 'pending') {
            return 'Awaiting Pickup Approval';
        }
        if ($delivery->status === 'waiting_for_rider') {
            return $order->status === 'ready_for_delivery' ? 'Ready for Pickup' : 'Awaiting Pickup';
        }

        return delivery_status_label($delivery->status);
    }

    /**
     * Customer-safe progress bucket: placed, confirmed, in_transit, delivered.
     */
    public static function progressStage(Order $order, ?Delivery $delivery = null): string
    {
        $delivery ??= $order->delivery;

        if ($order->status === 'cancelled') {
            return 'cancelled';
        }
        if ($order->status === 'delivered' || $delivery?->status === 'delivered') {
            return 'delivered';
        }
        if ($delivery) {
            if (in_array($delivery->status, ['picked_up', 'out_for_delivery', 'arrived_at_customer'], true)) {
                return 'in_transit';
            }
            if (self::verifiedCustody($delivery) && in_array($delivery->parcel_status, ['received', 'scanned', 'sorted', 'dispatched'], true)) {
                return 'in_transit';
            }
        }
        if (in_array($order->status, ['processing', 'ready_for_delivery', 'shipped'], true)) {
            return $order->status === 'shipped' ? 'in_transit' : 'confirmed';
        }
        if ($order->status === 'confirmed') {
            return 'confirmed';
        }

        return 'placed';
    }

    /**
     * Merge order history with real parcel milestones. Hub-processing logs are
     * only included when pickup+handoff custody exists, so a prematurely
     * advanced parcel never shows customer-facing hub events it did not have.
     *
     * Both audiences read the same records; buyers pass AUDIENCE_BUYER to
     * omit internal rider progress during the pickup leg while keeping every
     * delivery-level milestone and its timestamp.
     *
     * @return array<int, array{title: string, note: ?string, at: Carbon, category: string}>
     */
    public static function timeline(Order $order, ?Delivery $delivery = null, string $audience = 'seller'): array
    {
        $delivery ??= $order->delivery;
        $verified = $delivery ? self::verifiedCustody($delivery) : false;
        $entries = [];

        foreach ($order->statusHistories as $history) {
            [$title, $category] = self::historyEvent($history);
            $entries[] = [
                'title' => $title,
                'note' => $history->note,
                'at' => $history->created_at,
                'category' => $category,
                'priority' => 2,
            ];
        }

        if ($delivery) {
            foreach ($delivery->statusLogs as $log) {
                $event = self::logEvent($log, $delivery, $verified);
                if ($event === null) {
                    continue;
                }
                [$title, $category] = $event;
                $entries[] = [
                    'title' => $title,
                    'note' => self::logNote($log, $delivery),
                    'at' => $log->created_at,
                    'category' => $category,
                    'priority' => 1,
                ];
            }
        }

        // One customer-visible event per milestone per minute: the order
        // history copy wins over the internal delivery log copy.
        $deduped = [];
        foreach ($entries as $entry) {
            $key = $entry['category'].'|'.($entry['at']?->format('Y-m-d H:i') ?? '');
            if (! isset($deduped[$key]) || $entry['priority'] > $deduped[$key]['priority']) {
                $deduped[$key] = $entry;
            }
        }

        $timeline = array_values($deduped);
        usort($timeline, fn ($a, $b) => $a['at']?->getTimestamp() <=> $b['at']?->getTimestamp());

        $timeline = array_map(fn ($entry) => [
            'title' => $entry['title'],
            'note' => $entry['note'],
            'at' => $entry['at'],
            'category' => $entry['category'],
        ], $timeline);

        if ($audience === self::AUDIENCE_BUYER) {
            $timeline = array_values(array_filter(
                $timeline,
                fn (array $entry) => ! in_array($entry['category'], self::BUYER_HIDDEN_CATEGORIES, true)
            ));
        }

        return $timeline;
    }

    /** @return array{0: string, 1: string} */
    private static function historyEvent(OrderStatusHistory $history): array
    {
        $note = (string) $history->note;

        if (str_contains($note, 'in transit between logistics hubs')) {
            return ['In Transit to Destination Hub', 'hub_transfer_dispatched'];
        }
        if (str_contains($note, 'arrived at destination facility')) {
            return ['Arrived at Destination Hub', 'hub_received'];
        }
        if (str_contains($note, 'reported missing')) {
            return ['Parcel Missing', 'lost'];
        }
        if (str_contains($note, 'damage')) {
            return ['Parcel Damaged', 'damaged'];
        }

        return match ($history->to_status) {
            'shipped' => ['Parcel Picked Up by Rider', 'picked_up'],
            'ready_for_delivery' => ['Ready for Pickup', 'ready_for_pickup'],
            default => [order_status_label((string) $history->to_status), (string) $history->to_status],
        };
    }

    /**
     * The pickup scheduling note states that logistics review and rider
     * assignment are still required. Those requirements stop being true once
     * a rider owns the pickup, so the outdated sentence is dropped from the
     * customer view instead of rewriting the historical log.
     */
    private static function logNote(DeliveryStatusLog $log, Delivery $delivery): ?string
    {
        $note = $log->notes;

        if ($log->status !== 'pickup_scheduled' || $note === null) {
            return $note;
        }

        if ($delivery->rider_id === null && $delivery->picked_up_at === null) {
            return $note;
        }

        $note = trim(str_replace('Logistics review and rider assignment remain required.', '', (string) $note));

        return $note === '' ? null : $note;
    }

    /** @return array{0: string, 1: string}|null */
    private static function logEvent(DeliveryStatusLog $log, Delivery $delivery, bool $verified): ?array
    {
        // Rider acceptance and travel are first-mile events. The same
        // statuses may appear on a last-mile reassignment after the parcel was
        // collected, where they must not read as seller-pickup progress. Judge
        // the log by the stage it was written in, not today's parcel state.
        $firstMile = $delivery->picked_up_at === null
            ? $delivery->received_at === null
            : $log->created_at !== null && $log->created_at->lessThanOrEqualTo($delivery->picked_up_at);

        return match ($log->status) {
            'pickup_scheduled' => ['Pickup Scheduled', 'pickup_scheduled'],
            'assigned' => $firstMile ? ['Rider Assigned for Pickup', 'rider_assigned'] : null,
            'accepted' => $firstMile ? ['Rider Accepted Pickup', 'rider_accepted'] : null,
            'going_to_pickup' => $firstMile ? ['Rider En Route to Seller', 'going_to_pickup'] : null,
            'arrived_at_shop' => $firstMile ? ['Rider Arrived at Seller', 'arrived_at_shop'] : null,
            'pickup', 'picked_up' => ['Parcel Picked Up by Rider', 'picked_up'],
            'sorting_center_handoff' => $verified ? ['Handed to Logistics Hub', 'handoff'] : null,
            'received' => $verified ? ['Received at Logistics Hub', 'received'] : null,
            'scanned' => $verified ? ['Scanned at Logistics Hub', 'scanned'] : null,
            'sorted' => $verified ? ['Sorted at Logistics Hub', 'sorted'] : null,
            'dispatched' => $verified
                ? [$delivery->destination_center_id && (int) $delivery->destination_center_id !== (int) $delivery->center_id
                    ? 'Departed Origin Hub'
                    : 'Out for Delivery', 'dispatched']
                : null,
            // The transfer dispatch also writes a customer-facing order note.
            'hub_transfer_dispatched' => null,
            'out_for_delivery' => ['Out for Delivery', 'out_for_delivery'],
            'delivered' => ['Delivered', 'delivered'],
            'delivery_failed' => ['Delivery Failed', 'delivery_failed'],
            'lost' => ['Lost in Transit', 'lost'],
            'damaged' => ['Damaged', 'damaged'],
            'returned_to_seller' => ['Returned to Seller', 'returned_to_seller'],
            default => null,
        };
    }
}
