<?php

namespace App\Console\Commands;

use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Services\FulfillmentWorkflow;
use App\Services\OrderShipmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RepairOrderFulfillment extends Command
{
    protected $signature = 'orders:repair-fulfillment {--apply : Apply reviewed repairs; otherwise report only} {--order= : Limit to one order ID}';

    protected $description = 'Backfill shipment snapshots and close stale pre-pickup work on cancelled orders, without changing stock or money.';

    public function handle(OrderShipmentService $shipments): int
    {
        $apply = (bool) $this->option('apply');
        $backupDir = storage_path('app/private/fulfillment-repairs/'.now()->format('Ymd-His').'-'.Str::random(6));
        $counts = ['inspected' => 0, 'shipments_backfilled' => 0, 'stale_cancellations_closed' => 0, 'pickup_requests_added' => 0];
        $review = [];
        Order::query()->when($this->option('order'), fn ($q, $id) => $q->whereKey($id))->orderBy('id')->chunkById(100, function ($orders) use ($apply, $shipments, $backupDir, &$counts, &$review) {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order, $apply, $shipments, $backupDir, &$counts, &$review) {
                    $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $order->load(['items', 'deliveries.items', 'deliveries.pickupRequest']);
                    $counts['inspected']++;
                    $groups = $order->items->pluck('seller_id')->filter()->unique();
                    if ($groups->count() !== 1 || $order->deliveries->count() > 1) {
                        $review[$order->id][] = 'Missing items or legacy shared-store ownership needs review.';

                        return;
                    }
                    $delivery = $order->deliveries->first();
                    if (! $delivery && in_array($order->status, ['cancelled', 'delivered'], true)) {
                        $review[$order->id][] = 'Historical terminal order has no shipment; no new pickup job was created.';

                        return;
                    }
                    if ($delivery && (int) $delivery->seller_id !== (int) $groups->first()) {
                        $review[$order->id][] = 'Delivery and item store ownership do not match.';

                        return;
                    }
                    $backfill = ! $delivery || blank($delivery->sender_address) || blank($delivery->recipient_address) || $delivery->items->isEmpty();
                    $missingPickup = $order->status !== 'cancelled' && $order->status !== 'delivered' && (! $delivery || (! $delivery->picked_up_at && ! $delivery->pickupRequest
                        && in_array($delivery->status, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop'], true)));
                    $close = $order->status === 'cancelled' && $delivery && ! $delivery->picked_up_at
                        && in_array($delivery->status, ['waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop'], true);
                    if ($backfill) {
                        $counts['shipments_backfilled']++;
                    }
                    if ($missingPickup) {
                        $counts['pickup_requests_added']++;
                    }
                    if ($close) {
                        $counts['stale_cancellations_closed']++;
                    }
                    if ($apply && ($backfill || $missingPickup || $close)) {
                        File::ensureDirectoryExists($backupDir);
                        File::put($backupDir.'/order-'.$order->id.'.json', json_encode([
                            'order' => $order->getAttributes(),
                            'deliveries' => $order->deliveries->map(fn ($d) => [
                                'attributes' => $d->getAttributes(), 'items' => $d->items->toArray(),
                                'pickup' => $d->pickupRequest?->getAttributes(), 'rider' => $d->rider?->getAttributes(),
                            ])->all(),
                        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                        if ($backfill || $missingPickup) {
                            $delivery = $shipments->ensure($order, false);
                        }
                        if ($close) {
                            $delivery = FulfillmentWorkflow::lock($delivery);
                            $riderId = $delivery->rider_id;
                            $delivery->update(['status' => 'cancelled', 'cancelled_at' => $delivery->cancelled_at ?? now(), 'cancellation_reason' => $order->cancellation_reason ?: 'Linked order was already cancelled.']);
                            DeliveryStatusLog::create([
                                'delivery_id' => $delivery->id, 'status' => 'cancelled',
                                'notes' => 'Repair: closed stale pre-pickup work for an already-cancelled order. Stock, voucher use and money were not changed.',
                            ]);
                            FulfillmentWorkflow::releaseRider($riderId);
                        }
                    }
                    if ($delivery && (($order->status === 'cancelled' && $delivery->picked_up_at && $delivery->status !== 'cancelled')
                        || ($order->status === 'delivered' && $delivery->status !== 'delivered')
                        || ($delivery->status === 'delivered' && $order->status !== 'delivered'))) {
                        $review[$order->id][] = 'Conflicting physical/delivered history; proof, cash and custody need staff review.';
                    }
                    if ($delivery && blank($delivery->center_id) && ! in_array($order->status, ['cancelled', 'delivered'], true)) {
                        $review[$order->id][] = 'Handling center must be selected by logistics; no coverage was invented.';
                    }
                }, 3);
            }
        });
        $this->line(json_encode(['mode' => $apply ? 'applied' : 'dry-run', 'counts' => $counts, 'review' => $review], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if ($apply && File::isDirectory($backupDir)) {
            $this->info('Private before-change backup: '.$backupDir);
        }

        return self::SUCCESS;
    }
}
