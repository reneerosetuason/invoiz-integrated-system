<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerNotification;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Remittances;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnToSellerService
{
    public const DISPATCHED = 'return_dispatched_to_seller';

    public const RECEIVED = 'return_received_by_seller';

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
                && in_array($delivery->status, self::ELIGIBLE_STATUSES, true),
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

            if (! in_array($locked->status, self::ELIGIBLE_STATUSES, true)) {
                throw ValidationException::withMessages(['return' => 'Only failed or damaged parcels can be returned to the seller.']);
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
        $sellerKeys = array_map('intval', Remittances::sellerKeys($sellerUser->id));

        return DB::transaction(function () use ($delivery, $sellerUser, $sellerKeys, $note) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $order = Order::with('items')->find($locked->order_id);

            $sellerIds = $order
                ? $order->items->pluck('seller_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [];

            if ($locked->seller_id) {
                $sellerIds[] = (int) $locked->seller_id;
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

            if ($order) {
                $items = $order->items
                    ->whereIn('seller_id', array_values(array_unique($sellerIds)))
                    ->values();

                foreach ($items as $item) {
                    if ($item instanceof OrderItem) {
                        Stock::restore($item);
                    }
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
                'body' => "You confirmed receipt of returned parcel {$locked->tracking_number}. Stock was restored.",
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
