<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransferParcel;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\PaymentDispute;
use App\Models\User;
use App\Support\EscrowEarnings;
use App\Support\Payments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TripPaymentService
{
    public static function preview(HubTransfer $trip, ?array $rates = null): array
    {
        $truck = LogisticsTruck::findOrFail($trip->truck_id);
        $parcels = $trip->parcels()->where('loading_status', '!=', 'removed')->orderBy('delivery_id')->with('delivery')->get();
        $snapshot = app(TripPaymentCalculator::class)->calculate($truck->only(['capacity_kg', 'parcel_capacity', 'cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm']), $parcels->map(fn ($p) => $p->delivery?->only(['id', 'weight', 'length_cm', 'width_cm', 'height_cm']) ?? ['id' => $p->delivery_id])->all(), (float) $trip->route_distance_km, $rates ?? EscrowEarnings::rates());

        return $snapshot + ['driver_id' => $trip->driver_id, 'truck_id' => $trip->truck_id, 'origin_center_id' => $trip->origin_center_id, 'destination_center_id' => $trip->destination_center_id, 'route_distance_source' => $trip->route_distance_source];
    }

    public static function confirm(HubTransfer $trip, User $actor, string $previewHash): void
    {
        DB::transaction(function () use ($trip, $actor, $previewHash) {
            $trip = HubTransferService::lockTransfer($trip->id);
            abort_unless((int) $trip->payment_version === 1 && in_array($trip->status, ['loading', 'ready_for_dispatch'], true), 409);
            if ($trip->payment_confirmed_at) {
                return;
            }
            abort_unless($trip->driver_id && $trip->route_recorded_by && trim((string) $trip->route_distance_source) !== '', 422, 'Record an authorized road distance and source first.');
            LogisticsTruck::whereKey($trip->truck_id)->lockForUpdate()->firstOrFail();
            $entries = $trip->parcels()->where('loading_status', '!=', 'removed')->lockForUpdate()->get();
            Delivery::whereIn('id', $entries->pluck('delivery_id'))->orderBy('id')->lockForUpdate()->get();
            $snapshot = self::preview($trip);
            abort_unless(hash_equals(hash('sha256', json_encode($snapshot)), $previewHash), 409, 'The manifest, measurements, or rates changed. Review the updated calculation before confirming.');
            // Snapshot the existing seller budget; platform absorbs/reconciles the difference.
            $snapshot['seller_budget_cents'] = (int) round(EscrowEarnings::interHubDriverFee() * 100);
            $snapshot['seller_budgets'] = [];
            foreach ($entries as $entry) {
                $prior = HubTransfer::whereKeyNot($trip->id)->where('status', 'received')
                    ->whereHas('parcels', fn ($q) => $q->where('delivery_id', $entry->delivery_id)->where('loading_status', '!=', 'removed'))->exists();
                $snapshot['seller_budgets'][$entry->delivery_id] = $prior ? 0 : $snapshot['seller_budget_cents'];
            }
            $snapshot['platform_adjustment_cents'] = $snapshot['total'] - array_sum($snapshot['seller_budgets']);
            foreach ($snapshot['allocations'] as $deliveryId => $cents) {
                $trip->parcels()->where('delivery_id', $deliveryId)->update(['driver_allocation_cents' => $cents]);
            }
            $trip->update(['payment_snapshot' => $snapshot, 'payment_confirmed_at' => now(), 'payment_confirmed_by' => $actor->id]);
            $payout = EscrowRemittance::firstOrNew(['settlement_reference' => 'TRIP-'.$trip->id]);
            abort_if($payout->exists && in_array($payout->status, ['remitted', 'accepted'], true), 409, 'This trip payment has already been released.');
            $payout->fill(['hub_transfer_id' => $trip->id, 'hub_transport_driver_id' => $trip->driver_id, 'recipient_role' => 'inter_hub_driver', 'recipient_user_id' => $trip->driver?->user_id,
                'recipient_name' => $trip->driver?->name, 'amount' => number_format($snapshot['total'] / 100, 2, '.', ''), 'settlement_mode' => 'escrow_bank_remittance',
                'status' => 'held_in_escrow', 'rule_applied' => 'One dynamic payment per truck trip', 'notes' => 'Reserved; platform reconciliation: '.$snapshot['platform_adjustment_cents'].' centavos.'])->save();
            HubTransferService::event($trip, 'payment_reserved', null, null, json_encode($snapshot), $actor);
        });
    }

    /** Caller holds the transfer lock. Preserve the old snapshot in its event trail. */
    public static function reverse(HubTransfer $trip, User $actor, string $reason): void
    {
        if (! $trip->payment_confirmed_at) {
            return;
        }
        abort_unless(in_array($trip->status, ['loading', 'ready_for_dispatch'], true), 409, 'A dispatched trip payment cannot be changed.');
        $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->lockForUpdate()->firstOrFail();
        abort_if(in_array($payout->status, ['remitted', 'accepted'], true), 409, 'Released trip payments require financial adjustment.');
        $payout->update(['status' => 'voided', 'notes' => $reason]);
        HubTransferService::event($trip, 'payment_reversed', null, null, $reason.' Snapshot: '.json_encode($trip->payment_snapshot), $actor);
        $trip->parcels()->update(['driver_allocation_cents' => null]);
        $trip->update(['payment_snapshot' => null, 'payment_confirmed_at' => null, 'payment_confirmed_by' => null]);
    }

    public static function assertUnchanged(HubTransfer $trip): void
    {
        abort_unless($trip->payment_confirmed_at && $trip->payment_snapshot, 422, 'Review and confirm the trip payment before dispatch.');
        $old = $trip->payment_snapshot;
        $current = self::preview($trip, $old['rates']);
        // Rates may change after confirmation; physical facts may not.
        foreach (['parcels', 'truck', 'driver_id', 'truck_id', 'origin_center_id', 'destination_center_id', 'distance', 'route_distance_source'] as $key) {
            if ($key === 'parcels') {
                $measurements = fn ($items) => array_map(fn ($p) => array_intersect_key($p, array_flip(['id', 'weight', 'length_cm', 'width_cm', 'height_cm'])), $items);
                $same = $measurements($old[$key]) == $measurements($current[$key]);
            } elseif ($key === 'truck') {
                $fields = array_flip(['capacity_kg', 'parcel_capacity', 'cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm']);
                $same = array_intersect_key($old[$key], $fields) == array_intersect_key($current[$key], $fields);
            } else {
                $same = $old[$key] == $current[$key];
            }
            abort_unless($same, 409, 'Trip measurements or assignment changed. Reverse and confirm the payment again.');
        }
    }

    public static function complete(HubTransfer $trip): ?HubTransferEarning
    {
        abort_unless($trip->status === 'received' && $trip->payment_snapshot, 409, 'Trip payment was not reserved.');
        $s = $trip->payment_snapshot;
        self::refreshEligibility($trip);

        return HubTransferEarning::firstOrCreate(['hub_transfer_id' => $trip->id, 'recipient_role' => 'inter_hub_driver'], [
            'logistics_company_id' => $trip->logistics_company_id, 'hub_transport_driver_id' => $s['driver_id'], 'delivery_id' => null,
            'base_amount' => $s['base'] / 100, 'vehicle_allowance' => 0, 'amount' => $s['total'] / 100,
            'vehicle_ownership' => $trip->truck?->ownership_type ?? 'company', 'earned_on' => now()->toDateString(),
            'description' => 'Dynamic trip '.$trip->reference.'; distance '.($s['distancePayment'] / 100).'; load bonus '.($s['bonus'] / 100),
        ]);
    }

    public static function refreshEligibility(HubTransfer $trip): void
    {
        DB::transaction(function () use ($trip) {
            $trip = HubTransferService::lockTransfer($trip->id);
            $ready = false;
            try {
                self::assertReleasable($trip);
                $ready = true;
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 422) {
                    throw $e;
                }
            }
            EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)
                ->whereIn('status', ['held_in_escrow', 'pending_remittance'])
                ->update(['status' => $ready ? 'pending_remittance' : 'held_in_escrow']);
        });
    }

    /** Recheck all constituent orders at release; never infer funding from a trip scan. */
    public static function assertReleasable(HubTransfer $trip): void
    {
        abort_unless($trip->status === 'received' && $trip->payment_confirmed_at, 422, 'Destination receipt is required.');
        abort_unless(($trip->payment_snapshot['total'] ?? 0) > 0, 422, 'There is no positive trip payment to release.');
        $entries = $trip->parcels()->where('loading_status', '!=', 'removed')->with('delivery')->get();
        abort_if($entries->isEmpty() || $entries->contains(fn ($e) => $e->receiving_status !== 'received' || $e->condition !== 'good' || ! $e->delivery?->order_id), 422, 'Resolve missing, damaged, or unlinked parcels before releasing the trip payment.');
        foreach (Order::whereIn('id', $entries->pluck('delivery.order_id'))->orderBy('id')->lockForUpdate()->get() as $order) {
            abort_if($order->returnRequests()->whereIn('status', ['requested', 'approved', 'item_received'])->exists()
                || PaymentDispute::where('order_id', $order->id)->whereNotIn('status', ['resolved', 'rejected'])->exists(), 422, 'Resolve every trip order return or dispute before releasing payment.');
            if (GcashEscrowService::isGcash($order)) {
                abort_unless(GcashEscrowService::funded($order) && ! $order->deliveries()->where('status', '!=', 'delivered')->exists(), 422, 'All GCash trip orders must be funded and delivered.');
            } else {
                $tx = Payments::transactionFor($order);
                abort_unless(in_array($order->payment_status, ['paid', 'partially_refunded'], true)
                    && in_array($tx?->status, ['successful', 'partially_refunded'], true) && (float) $tx->amount > 0, 422, 'Every trip order must have confirmed escrow settlement before release.');
            }
            abort_if(in_array($order->status, ['cancelled', 'refunded', 'returned'], true), 422, 'Cancelled or refunded trip orders require a financial adjustment.');
        }
    }

    /** Existing seller budgets remain unavailable for refunds while reserved or paid. */
    public static function orderBudget(Order $order, ?int $sellerId = null): array
    {
        $result = ['reserved' => 0, 'released' => 0];
        if (! Schema::hasColumn('hub_transfers', 'payment_version')) {
            return $result;
        }
        $entries = HubTransferParcel::whereHas('delivery', fn ($q) => $q->where('order_id', $order->id)->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId)))
            ->where('loading_status', '!=', 'removed')->whereHas('transfer', fn ($q) => $q->where('payment_version', 1)->whereNotNull('payment_confirmed_at')->where('status', '!=', 'cancelled'))
            ->with('transfer')->get();
        foreach ($entries as $entry) {
            $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$entry->hub_transfer_id)->first();
            if (! $payout || $payout->status === 'voided') {
                continue;
            }
            $key = in_array($payout->status, ['remitted', 'accepted'], true) ? 'released' : 'reserved';
            $result[$key] += (int) ($entry->transfer->payment_snapshot['seller_budgets'][$entry->delivery_id] ?? 0) / 100;
        }

        return $result;
    }
}
