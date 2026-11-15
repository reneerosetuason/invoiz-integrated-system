<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Seller;
use App\Models\SellerNotification;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Payments;
use App\Support\Remittances;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnToSellerService
{
    public const DISPATCHED = 'return_dispatched_to_seller';

    public const RECEIVED = 'return_received_by_seller';

    // Preserve receipt confirmation for seller returns dispatched before the
    // failed-delivery policy changed; new dispatches use the model allowlist.
    private const ELIGIBLE_STATUSES = ['delivery_failed', 'damaged'];

    public function state(Delivery $delivery): array
    {
        $logs = $delivery->statusLogs()
            ->whereIn('status', [self::DISPATCHED, self::RECEIVED])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $dispatched = $logs->firstWhere('status', self::DISPATCHED);
        $received = $logs->firstWhere('status', self::RECEIVED);

        $state = 'not_queued';
        if ($received) {
            $state = 'received';
        } elseif ($dispatched) {
            $state = 'dispatched';
        } elseif ($delivery->status === 'delivery_failed' && $delivery->deliveryAttemptsExhausted()) {
            $state = FailedDeliveryService::caseFor($delivery)?->stage ?? 'awaiting_seller_decision';
        } elseif ($delivery->status === 'delivery_failed'
            || ($delivery->returned_at !== null
                && in_array($delivery->status, ['waiting_for_rider', 'assigned', 'accepted'], true))) {
            $state = 'awaiting_redelivery';
        } elseif ($delivery->returned_at) {
            $state = 'queued';
        }

        return [
            'state' => $state,
            'queued_at' => $delivery->returned_at,
            'dispatched_at' => $dispatched?->created_at,
            'received_at' => $received?->created_at,
            'can_dispatch' => $delivery->returned_at !== null
                && ! $dispatched
                && ! $received
                && $delivery->status === 'damaged',
            'can_confirm' => $dispatched !== null && ! $received,
        ];
    }

    public function dispatch(Delivery $delivery, ?User $actor = null, ?string $reference = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor, $reference) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            $received = DeliveryStatusLog::where('delivery_id', $locked->id)
                ->where('status', self::RECEIVED)
                ->exists();
            if ($received) {
                throw ValidationException::withMessages(['return' => 'This parcel has already been received by the seller.']);
            }

            $dispatched = DeliveryStatusLog::where('delivery_id', $locked->id)
                ->where('status', self::DISPATCHED)
                ->exists();
            if ($dispatched) {
                return $locked->fresh();
            }

            if (! $locked->returned_at) {
                throw ValidationException::withMessages(['return' => 'The parcel must be returned to the center before it can be sent to the seller.']);
            }

            if (! in_array($locked->status, Delivery::RETURN_TO_SELLER_STATUSES, true)) {
                throw ValidationException::withMessages(['return' => 'Failed deliveries stay at the destination Logistics Hub for reassignment. Only damaged parcels can be dispatched back to the seller.']);
            }

            if ($locked->status === 'delivery_failed') {
                $case = FailedDeliveryService::caseFor($locked);
                if (! $case || $case->stage !== 'seller_delivery' || ! $case->funded_at
                    || $case->decision !== 'return' || (int) $case->rider?->user_id !== (int) $actor?->id) {
                    throw ValidationException::withMessages(['return' => 'Use the seller-approved, funded return workflow after three failed attempts.']);
                }
            }

            DeliveryStatusLog::create([
                'delivery_id' => $locked->id,
                'status' => self::DISPATCHED,
                'notes' => 'Return dispatched to seller.'.($reference ? " Reference: {$reference}" : ''),
                'changed_by' => $actor?->id,
            ]);

            Notification::create([
                'type' => 'return_dispatched_to_seller',
                'title' => 'Return Sent to Seller',
                'message' => "{$locked->tracking_number} is on its way back to the seller.",
                'icon' => '!',
                'priority' => 'normal',
                'center_id' => $locked->center_id,
                'audience' => $locked->center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $locked),
            ]);

            if ($locked->seller_id) {
                SellerNotification::create([
                    'seller_id' => Seller::userIdFor((int) $locked->seller_id),
                    'order_id' => $locked->order_id,
                    'type' => 'return_dispatched',
                    'title' => 'Parcel Return On The Way',
                    'body' => "Parcel {$locked->tracking_number} is being returned to you. Confirm receipt when it arrives.",
                ]);
            }

            app(AuditLogger::class)->record(
                $actor,
                'delivery.return_to_seller.dispatched',
                'delivery',
                $locked->id,
                [],
                ['reference' => $reference],
                $reference,
            );

            return $locked->fresh();
        }, 3);
    }

    public function confirm(Delivery $delivery, User $sellerUser, ?string $note = null): Delivery
    {
        $case = FailedDeliveryService::caseFor($delivery);
        if ($case && (! in_array($case->stage, ['seller_arrival', 'completed'], true) || ! $case->funded_at)) {
            throw ValidationException::withMessages(['return' => 'The assigned return rider must record seller handover before receipt confirmation.']);
        }
        $sellerKeys = [(int) $sellerUser->id];

        return DB::transaction(function () use ($delivery, $sellerUser, $sellerKeys, $note) {
            $order = Order::with('items')->whereKey($delivery->order_id)->lockForUpdate()->first();
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            $sellerIds = $order
                ? $order->items->pluck('seller_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [];

            // A parcel belongs to its seller, never to every store in a legacy order.
            $sellerIds = $locked->seller_id ? [(int) $locked->seller_id] : $sellerIds;
            if (count($sellerIds) !== 1) {
                throw ValidationException::withMessages(['return' => 'Resolve the seller identity for this parcel before confirming receipt.']);
            }

            if (! array_intersect($sellerKeys, array_values(array_unique($sellerIds)))) {
                abort(403, 'This return belongs to another store.');
            }

            $received = DeliveryStatusLog::where('delivery_id', $locked->id)
                ->where('status', self::RECEIVED)
                ->exists();
            if ($received) {
                return $locked->fresh();
            }

            $dispatched = DeliveryStatusLog::where('delivery_id', $locked->id)
                ->where('status', self::DISPATCHED)
                ->exists();
            if (! $dispatched) {
                throw ValidationException::withMessages(['return' => 'This parcel has not been dispatched back to the seller yet.']);
            }

            if (! $locked->returned_at || ! in_array($locked->status, self::ELIGIBLE_STATUSES, true)) {
                throw ValidationException::withMessages(['return' => 'This parcel is not eligible for seller return confirmation.']);
            }

            DeliveryStatusLog::create([
                'delivery_id' => $locked->id,
                'status' => self::RECEIVED,
                'notes' => 'Seller confirmed receipt of returned parcel.'.($note ? ' '.$note : ''),
                'changed_by' => $sellerUser->id,
            ]);

            $locked->update(['status' => 'returned_to_seller']);
            $restocked = false;

            if ($order) {
                $items = $order->items
                    ->whereIn('seller_id', array_values(array_unique($sellerIds)))
                    ->values();

                $alreadyRestored = $order->deliveries()->where('id', '!=', $locked->id)
                    ->where('seller_id', $sellerIds[0])->where('status', 'returned_to_seller')->exists();
                foreach ($items as $item) {
                    if ($item instanceof OrderItem && ! $alreadyRestored && $delivery->status !== 'damaged') {
                        Stock::restore($item);
                        $restocked = true;
                    }
                }

                $activeDeliveriesRemaining = $order->deliveries()
                    ->whereNotIn('status', ['returned_to_seller', 'cancelled'])
                    ->where('id', '!=', $locked->id)
                    ->exists();

                if (! $activeDeliveriesRemaining && $order->status !== 'cancelled') {
                    $fromStatus = $order->status;
                    $order->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => 'Parcel returned to seller',
                    ]);
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => $fromStatus,
                        'to_status' => 'cancelled',
                        'note' => 'Order cancelled: parcel confirmed returned to seller.'.($restocked ? ' Stock restored.' : ' Stock was not added to saleable inventory.'),
                    ]);
                    Payments::voidForCancel($order, strict: true);
                }
            }

            Notification::create([
                'type' => 'return_received_by_seller',
                'title' => 'Return Received by Seller',
                'message' => "{$locked->tracking_number} was confirmed received by the seller.",
                'icon' => '!',
                'priority' => 'normal',
                'center_id' => $locked->center_id,
                'audience' => $locked->center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $locked),
            ]);

            SellerNotification::create([
                'seller_id' => $sellerUser->id,
                'order_id' => $locked->order_id,
                'type' => 'return_received',
                'title' => 'Return Received',
                'body' => "You confirmed receipt of returned parcel {$locked->tracking_number}.".($restocked ? ' Stock was restored.' : ' Saleable stock was not increased.'),
            ]);

            app(AuditLogger::class)->record(
                $sellerUser,
                'delivery.return_to_seller.received',
                'delivery',
                $locked->id,
                ['status' => $delivery->status],
                ['status' => 'returned_to_seller'],
                $note,
            );

            return $locked->fresh();
        }, 3);
    }
}
