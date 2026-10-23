<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deliveries covering every lifecycle state, plus their items, status
 * history, proofs, failures, transactions, remittances, rider earnings,
 * locations, pickup requests and courier pickups.
 */
class DeliveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DeliveryDemoSeeder skipped outside local/testing.');

            return;
        }

        $orders = DB::table('orders')->get()->keyBy('id');
        $riders = DB::table('riders')->get()->keyBy('email');
        $riderUsers = DB::table('users')->where('role', 'rider')->pluck('id', 'email');
        $centers = DB::table('logistics_centers')->pluck('id', 'name');
        $areas = DB::table('service_areas')->pluck('id', 'name');
        $adminId = DB::table('users')->where('role', 'logistics_admin')->value('id');

        $specs = [
            ['tracking' => 'TRK-20260819-0001', 'order' => 2, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'delivered', 'parcel' => 'dispatched', 'days' => 2, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0002', 'order' => 3, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'out_for_delivery', 'parcel' => 'dispatched', 'days' => 1, 'payment' => 'Prepaid'],
            ['tracking' => 'TRK-20260819-0003', 'order' => 4, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'assigned', 'parcel' => 'received', 'days' => 1, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0004', 'order' => 5, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'accepted', 'parcel' => 'received', 'days' => 0, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0005', 'order' => 10, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'going_to_pickup', 'parcel' => 'received', 'days' => 0, 'payment' => 'Prepaid'],
            ['tracking' => 'TRK-20260819-0006', 'order' => 18, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Makati & Pasig', 'status' => 'arrived_at_shop', 'parcel' => 'received', 'days' => 0, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0007', 'order' => 19, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'picked_up', 'parcel' => 'sorted', 'days' => 0, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0008', 'order' => 27, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'arrived_at_customer', 'parcel' => 'dispatched', 'days' => 0, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0009', 'order' => 20, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'delivery_failed', 'parcel' => 'dispatched', 'days' => 3, 'payment' => 'COD', 'failure' => 'Recipient not available'],
            ['tracking' => 'TRK-20260819-0010', 'order' => 7, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'cancelled', 'parcel' => 'pending_arrival', 'days' => 4, 'payment' => 'Prepaid', 'cancellation' => 'Cancelled by buyer before pickup'],
            ['tracking' => 'TRK-20260819-0011', 'order' => 6, 'rider' => null, 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'waiting_for_rider', 'parcel' => 'pending_arrival', 'days' => 0, 'payment' => 'Prepaid'],
            ['tracking' => 'TRK-20260819-0012', 'order' => 14, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Makati & Pasig', 'status' => 'delivered', 'parcel' => 'dispatched', 'days' => 12, 'payment' => 'Prepaid', 'archived' => true],
            ['tracking' => 'TRK-20260819-0013', 'order' => 23, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'delivery_failed', 'parcel' => 'dispatched', 'days' => 2, 'payment' => 'COD', 'failure' => 'Nobody answered the door', 'reattempt' => true],
            ['tracking' => 'TRK-20260819-0014', 'order' => 24, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'delivery_failed', 'parcel' => 'dispatched', 'days' => 5, 'payment' => 'COD', 'failure' => 'Address could not be located', 'returned' => 'Returned to logistics center'],
            ['tracking' => 'TRK-20260819-0015', 'order' => 26, 'rider' => 'rider3@test.com', 'center' => 'Invoiz Cebu Logistics Center', 'area' => 'Cebu Metro', 'status' => 'out_for_delivery', 'parcel' => 'sorted', 'days' => 1, 'payment' => 'COD', 'destination_center' => 'Invoiz Manila Logistics Center', 'sorting' => true],
            ['tracking' => 'TRK-20260819-0016', 'order' => 28, 'rider' => 'rider3@test.com', 'center' => 'Invoiz Cebu Logistics Center', 'area' => 'Cebu Metro', 'status' => 'arrived_at_customer', 'parcel' => 'dispatched', 'days' => 0, 'payment' => 'Prepaid', 'sorting' => true],
            ['tracking' => 'TRK-20260819-0017', 'order' => 32, 'rider' => 'rider1@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Manila North', 'status' => 'delivered', 'parcel' => 'dispatched', 'days' => 20, 'payment' => 'COD'],
            ['tracking' => 'TRK-20260819-0018', 'order' => 33, 'rider' => 'rider2@test.com', 'center' => 'Invoiz Manila Logistics Center', 'area' => 'Quezon City', 'status' => 'delivered', 'parcel' => 'dispatched', 'days' => 8, 'payment' => 'COD'],
        ];

        // Earlier demo runs used TRK-DEMO-### identifiers, which do not match
        // the production tracking format (TRK-YYYYMMDD-XXXX) enforced by the
        // manual-entry/QR verification. Rename those rows in place so re-running
        // this seeder stays idempotent and existing delivery ids/relations hold.
        foreach ($specs as $spec) {
            $legacy = sprintf('TRK-DEMO-%03d', (int) substr($spec['tracking'], -4));
            if (DB::table('deliveries')->where('tracking_number', $legacy)->exists()
                && ! DB::table('deliveries')->where('tracking_number', $spec['tracking'])->exists()) {
                DB::table('deliveries')->where('tracking_number', $legacy)->update([
                    'tracking_number' => $spec['tracking'],
                    'updated_at' => now(),
                ]);
            }
        }

        $deliveryIds = [];

        foreach ($specs as $spec) {
            $order = $orders[$spec['order']] ?? null;

            if (! $order) {
                continue;
            }

            $rider = $spec['rider'] ? ($riders[$spec['rider']] ?? null) : null;
            $riderId = $rider->id ?? null;
            $riderUserId = $spec['rider'] ? ($riderUsers[$spec['rider']] ?? null) : null;
            $centerId = $centers[$spec['center']] ?? null;
            $areaId = $areas[$spec['area']] ?? null;
            $destinationCenterId = isset($spec['destination_center'])
                ? ($centers[$spec['destination_center']] ?? null)
                : null;

            $base = now()->subDays($spec['days'])->setTime(10, 0, 0);
            $status = $spec['status'];
            $shipping = json_decode($order->shipping_address ?? '', true) ?: [];

            $buyer = DB::table('users')->where('id', $order->buyer_id)->first();
            $seller = DB::table('sellers')->where('id', $order->seller_id)->first();
            $sellerUser = $seller ? DB::table('users')->where('id', $seller->user_id)->first() : null;

            $completed = in_array($status, ['accepted', 'going_to_pickup', 'arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered'], true);
            $moving = in_array($status, ['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered'], true);
            $dispatch = in_array($status, ['out_for_delivery', 'arrived_at_customer', 'delivered'], true);

            $values = [
                'order_id' => $order->id,
                'seller_id' => $order->seller_id,
                'rider_id' => $riderId,
                'center_id' => $centerId,
                'destination_center_id' => $destinationCenterId,
                'service_area_id' => $areaId,
                'status' => $status,
                'parcel_status' => $spec['parcel'],
                'delivery_notes' => 'Demo delivery generated for UI testing.',
                'payment_method' => $spec['payment'],
                'amount_to_collect' => $spec['payment'] === 'COD' ? $order->total : 0,
                'delivery_fee' => 49,
                'pickup_pin' => '1234',
                'estimated_delivery_at' => $base->copy()->addHours(6),
                'sender_lat' => 14.5995124,
                'sender_lng' => 120.9842224,
                'recipient_lat' => 14.6760413,
                'recipient_lng' => 121.0437003,
                'accepted_at' => $completed ? $base->copy()->addMinutes(15) : null,
                'assigned_at' => $riderId ? $base->copy()->addMinutes(5) : null,
                'picked_up_at' => $moving ? $base->copy()->addMinutes(40) : null,
                'delivered_at' => $status === 'delivered' ? $base->copy()->addHours(3) : null,
                'cancelled_at' => $status === 'cancelled' ? $base->copy()->addHours(1) : null,
                'failed_at' => $status === 'delivery_failed' ? $base->copy()->addHours(3) : null,
                'failure_reason' => $spec['failure'] ?? null,
                'cancellation_reason' => $spec['cancellation'] ?? null,
                'reattempt_requested_at' => ($spec['reattempt'] ?? false) ? now()->subHours(2) : null,
                'returned_at' => ($spec['returned'] ?? false) ? now()->subDays(4) : null,
                'return_reason' => $spec['returned'] ?? null,
                'sender_name' => $seller?->store_name ?? $seller?->business_name ?? 'Demo Seller',
                'sender_phone' => $sellerUser?->phone ?? '09170000000',
                'sender_address' => $seller?->business_name
                    ? $seller->business_name.' Warehouse, Manila'
                    : 'Warehouse, Manila',
                'recipient_name' => $buyer?->name ?? 'Demo Buyer',
                'recipient_phone' => $buyer?->phone ?? '09180000000',
                'recipient_address' => $shipping['street'] ?? '123 Buyer Street, Manila',
                'weight' => 1.5 + ($order->id % 5),
                'notes' => 'Handle with care.',
                'package_type' => 'Parcel',
                'package_description' => 'Assorted items',
                'priority' => $order->id % 3 === 0 ? 'high' : 'normal',
                'received_at' => in_array($spec['parcel'], ['received', 'scanned', 'sorted', 'dispatched'], true) ? $base->copy()->addMinutes(20) : null,
                'scanned_at' => in_array($spec['parcel'], ['scanned', 'sorted', 'dispatched'], true) ? $base->copy()->addMinutes(25) : null,
                'sorted_at' => in_array($spec['parcel'], ['sorted', 'dispatched'], true) ? $base->copy()->addMinutes(30) : null,
                'dispatched_at' => $spec['parcel'] === 'dispatched' ? $base->copy()->addMinutes(35) : null,
                'sorting_center_handoff_at' => ($spec['sorting'] ?? false) ? $base->copy()->addMinutes(20) : null,
                'sorting_center_handoff_rider_id' => ($spec['sorting'] ?? false) ? $riderId : null,
                'sorting_center_pickup_at' => ($spec['sorting'] ?? false) ? $base->copy()->addMinutes(35) : null,
                'sorting_center_pickup_rider_id' => ($spec['sorting'] ?? false) ? $riderId : null,
                'created_by' => $adminId,
                'archived_at' => ($spec['archived'] ?? false) ? now()->subDays(2) : null,
                'archived_by' => ($spec['archived'] ?? false) ? $adminId : null,
                'archive_note' => ($spec['archived'] ?? false) ? 'Completed and archived.' : null,
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ];

            DB::table('deliveries')->updateOrInsert(['tracking_number' => $spec['tracking']], $values);
            $deliveryId = DB::table('deliveries')->where('tracking_number', $spec['tracking'])->value('id');
            $deliveryIds[$spec['tracking']] = $deliveryId;

            $this->seedItems($deliveryId, $order->id);
            $this->seedStatusLogs($deliveryId, $status, $base, $riderUserId, $adminId);

            if ($status === 'delivery_failed') {
                DB::table('delivery_failures')->updateOrInsert(
                    ['delivery_id' => $deliveryId],
                    [
                        'rider_id' => $riderId,
                        'reason' => $spec['failure'],
                        'notes' => 'Customer could not be reached by phone.',
                        'reported_at' => $base->copy()->addHours(3),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }

            if ($status === 'delivered') {
                $this->seedProof($deliveryId, $riderId, $spec['tracking']);
                $this->seedTransaction($deliveryId, $spec['tracking'], $riderId, $centerId, $areaId, $order->total, $spec['days']);
                $this->seedRemittance($deliveryId, $order, $riderId, $spec['tracking']);
                $this->seedEarning($riderId, $deliveryId);
            }

            if ($riderId && $moving) {
                $this->seedLocations($riderId, $deliveryId, $base);
            }

            if (in_array($spec['tracking'], ['TRK-20260819-0002', 'TRK-20260819-0003', 'TRK-20260819-0005', 'TRK-20260819-0006', 'TRK-20260819-0009'], true)) {
                $pickupStatus = match ($spec['tracking']) {
                    // Only a pre-pickup delivery can still await review.
                    'TRK-20260819-0003' => 'pending',
                    'TRK-20260819-0005' => 'approved',
                    'TRK-20260819-0006' => 'rejected',
                    default => 'completed',
                };

                DB::table('pickup_requests')->updateOrInsert(
                    ['delivery_id' => $deliveryId],
                    [
                        'center_id' => $centerId,
                        'status' => $pickupStatus,
                        'requested_at' => $base->copy()->addMinutes(10),
                        'reviewed_at' => $pickupStatus === 'pending' ? null : $base->copy()->addMinutes(20),
                        'reviewed_by' => $pickupStatus === 'pending' ? null : $adminId,
                        'rejection_reason' => $pickupStatus === 'rejected' ? 'Pickup slot unavailable.' : null,
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $courierPickups = [
            ['order_id' => 2, 'status' => 'scheduled', 'courier' => 'Invoiz Express'],
            ['order_id' => 3, 'status' => 'picked_up', 'courier' => 'Invoiz Express'],
            ['order_id' => 4, 'status' => 'completed', 'courier' => 'Invoiz Express'],
            ['order_id' => 5, 'status' => 'cancelled', 'courier' => 'Invoiz Express'],
        ];

        foreach ($courierPickups as $pickup) {
            $order = $orders[$pickup['order_id']] ?? null;

            if (! $order) {
                continue;
            }

            DB::table('courier_pickups')->updateOrInsert(
                ['order_id' => $pickup['order_id'], 'courier' => $pickup['courier']],
                [
                    'seller_id' => $order->seller_id,
                    'pickup_at' => now()->addDay()->setTime(14, 0),
                    'tracking_number' => 'CP-'.$pickup['order_id'],
                    'status' => $pickup['status'],
                    'notes' => 'Courier pickup demo.',
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('rider_earnings')->updateOrInsert(
            ['rider_id' => $riders['rider1@test.com']->id ?? 0, 'delivery_id' => null, 'type' => 'bonus'],
            [
                'amount' => 500,
                'earned_on' => now()->startOfMonth(),
                'description' => 'Monthly performance bonus.',
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function seedItems(int $deliveryId, int $orderId): void
    {
        $items = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', $orderId)
            ->select('products.name', 'order_items.quantity', 'order_items.unit_price')
            ->limit(3)
            ->get();

        if ($items->isEmpty()) {
            $items = collect([(object) ['name' => 'Demo Item', 'quantity' => 1, 'unit_price' => 100]]);
        }

        foreach ($items as $item) {
            DB::table('delivery_items')->updateOrInsert(
                ['delivery_id' => $deliveryId, 'name' => $item->name],
                [
                    'variant_label' => 'Standard',
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedStatusLogs(int $deliveryId, string $finalStatus, $base, ?int $riderUserId, ?int $adminId): void
    {
        $lifecycle = [
            'waiting_for_rider',
            'assigned',
            'accepted',
            'going_to_pickup',
            'arrived_at_shop',
            'picked_up',
            'out_for_delivery',
            'arrived_at_customer',
            'delivered',
        ];

        $index = array_search($finalStatus, $lifecycle, true);
        $statuses = $index === false
            ? array_merge($lifecycle, [$finalStatus])
            : array_slice($lifecycle, 0, $index + 1);

        foreach ($statuses as $i => $status) {
            DB::table('delivery_status_logs')->updateOrInsert(
                ['delivery_id' => $deliveryId, 'status' => $status],
                [
                    'location' => 'Demo route point '.$i,
                    'notes' => 'Status changed to '.str_replace('_', ' ', $status).'.',
                    'changed_by' => $status === 'assigned' ? $adminId : ($riderUserId ?? $adminId),
                    'created_at' => $base->copy()->addMinutes($i * 15),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedProof(int $deliveryId, ?int $riderId, string $tracking): void
    {
        Storage::disk('public')->put("delivery-proofs/{$tracking}/photo.jpg", 'demo-proof-image');

        DB::table('delivery_proofs')->updateOrInsert(
            ['delivery_id' => $deliveryId, 'type' => 'photo'],
            [
                'rider_id' => $riderId,
                'file_path' => "delivery-proofs/{$tracking}/photo.jpg",
                'signature_name' => 'Received By Buyer',
                'otp' => '123456',
                'latitude' => '14.6760413',
                'longitude' => '121.0437003',
                'verified_at' => now()->subDays(2),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );

        DB::table('delivery_proofs')->updateOrInsert(
            ['delivery_id' => $deliveryId, 'type' => 'signature'],
            [
                'rider_id' => $riderId,
                'file_path' => null,
                'signature_name' => 'Buyer Signature',
                'otp' => null,
                'latitude' => '14.6760413',
                'longitude' => '121.0437003',
                'verified_at' => now()->subDays(2),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function seedTransaction(int $deliveryId, string $tracking, ?int $riderId, ?int $centerId, ?int $areaId, $total, int $days): void
    {
        DB::table('transactions')->updateOrInsert(
            ['delivery_id' => $deliveryId],
            [
                'tracking_number' => $tracking,
                'rider_id' => $riderId,
                'logistics_center_id' => $centerId,
                'service_area_id' => $areaId,
                'amount' => $total,
                'rider_fee' => 15,
                'admin_commission' => round(((float) $total) * 0.10, 2),
                'status' => $days === 2 ? 'pending' : 'completed',
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRemittance(int $deliveryId, $order, ?int $riderId, string $tracking): void
    {
        $status = match ($tracking) {
            'TRK-20260819-0001' => 'pending',
            'TRK-20260819-0012' => 'sent',
            default => 'confirmed',
        };

        DB::table('remittances')->updateOrInsert(
            ['delivery_id' => $deliveryId],
            [
                'order_id' => $order->id,
                'rider_id' => $riderId,
                'seller_id' => $order->seller_id,
                'amount' => $order->total,
                'status' => $status,
                'reference' => 'REM-'.$order->id,
                'note' => 'COD cash handoff demo.',
                'sent_at' => $status === 'pending' ? null : now()->subDays(1),
                'confirmed_at' => $status === 'confirmed' ? now()->subHours(12) : null,
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function seedEarning(?int $riderId, int $deliveryId): void
    {
        if (! $riderId) {
            return;
        }

        DB::table('rider_earnings')->updateOrInsert(
            ['rider_id' => $riderId, 'delivery_id' => $deliveryId, 'type' => 'delivery'],
            [
                'amount' => 50,
                'earned_on' => now()->subDays(2)->toDateString(),
                'description' => 'Delivery fee share.',
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }

    private function seedLocations(int $riderId, int $deliveryId, $base): void
    {
        $points = [
            ['latitude' => 14.6042000, 'longitude' => 120.9822000, 'minutes' => 45],
            ['latitude' => 14.6200000, 'longitude' => 121.0000000, 'minutes' => 55],
            ['latitude' => 14.6500000, 'longitude' => 121.0200000, 'minutes' => 65],
        ];

        foreach ($points as $point) {
            $recordedAt = $base->copy()->addMinutes($point['minutes']);

            DB::table('rider_locations')->updateOrInsert(
                ['rider_id' => $riderId, 'delivery_id' => $deliveryId, 'recorded_at' => $recordedAt],
                [
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                ]
            );
        }
    }
}
