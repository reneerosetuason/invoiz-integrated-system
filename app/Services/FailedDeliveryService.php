<?php

namespace App\Services;

use App\Models\{Delivery, DeliveryStatusLog, EscrowRemittance, FailedDeliveryCase, FailedDeliveryFee, FailedDeliveryPayment, HubTransfer, HubTransportDriver, LogisticsCenter, LogisticsTruck, Order, OrderStatusHistory, Rider, SellerNotification, User};
use App\Support\{EscrowEarnings, LogisticsAccess, Payments, PayoutAccounts};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;

/** Seller-funded failed-shipment services, separate from buyer return agreements. */
class FailedDeliveryService
{
    public static function available(): bool
    {
        return Schema::hasTable('failed_delivery_cases') && Schema::hasTable('failed_delivery_fees') && Schema::hasTable('failed_delivery_payments');
    }

    public static function caseFor(Delivery $delivery): ?FailedDeliveryCase
    {
        return self::available() ? FailedDeliveryCase::where('delivery_id', $delivery->id)->first() : null;
    }

    /** Called only after an authorized, physical rider-to-hub return. */
    public function open(Delivery $delivery): ?FailedDeliveryCase
    {
        if (! self::available() || ! $delivery->deliveryAttemptsExhausted() || ! $delivery->returned_at) return null;
        return DB::transaction(function () use ($delivery) {
            $delivery = FulfillmentWorkflow::lock($delivery);
            self::check($delivery->status === 'delivery_failed' && $delivery->seller_id && $delivery->center_id, 'This failed shipment needs its seller and destination hub recorded.');
            $existing = self::caseFor($delivery);
            if ($existing) return $existing;
            $case = FailedDeliveryCase::create(['delivery_id' => $delivery->id, 'seller_id' => $delivery->seller_id,
                'center_id' => $delivery->center_id, 'seller_center_id' => $delivery->origin_center_id ?: $delivery->center_id,
                'stage' => 'seller_review', 'events' => []]);
            $this->outboundFees($case, $delivery);
            $case->update(['quote' => ['version' => 1, 'outbound_once' => true], 'quoted_at' => now()]);
            if (Schema::hasTable('escrow_remittances')) {
                // Paid rows stay untouched and are credited against these fees.
                // Unpaid delivery-specific worker rows must not also be released.
                EscrowRemittance::where(fn ($q) => $q->where('delivery_id', $delivery->id)
                    ->when($delivery->order_id, fn ($q) => $q->orWhere(fn ($legacy) => $legacy->whereNull('delivery_id')->where('order_id', $delivery->order_id)->where('seller_id', $delivery->seller_id))))
                    ->whereIn('recipient_role', EscrowRemittance::WORKER_ROLES)
                    ->whereIn('status', ['held_in_escrow', 'pending_remittance'])->update(['status' => 'voided',
                        'notes' => 'Service settlement moved to failed-delivery case #'.$case->id.'. Buyer funds remain separate.']);
            }
            SellerNotification::create(['seller_id' => $case->seller_id, 'order_id' => $delivery->order_id,
                'type' => 'failed_delivery_review', 'title' => 'Three delivery attempts failed',
                'body' => 'Parcel '.$delivery->tracking_number.' is at the destination hub. Choose return to seller or unclaimed storage. Completed services remain payable.']);
            $this->event($case, null, 'opened');
            return $case->fresh();
        }, 3);
    }

    public function canView(FailedDeliveryCase $case, $actor): bool
    {
        if ($actor instanceof HubTransportDriver) return ! $actor->isBlocked() && (! $actor->user || $actor->user->isActive()) && (int) $case->return_driver_id === (int) $actor->id;
        if (! $actor instanceof User || ! $actor->isActive()) return false;
        if ($actor->isAdmin() || (int) $actor->id === (int) $case->seller_id) return true;
        if ($actor->isLogisticsStaff() && (LogisticsAccess::canAccessCenter($actor, (int) $case->center_id)
            || ($case->seller_center_id && LogisticsAccess::canAccessCenter($actor, (int) $case->seller_center_id)))) return true;
        if ((int) $case->rider?->user_id === (int) $actor->id) return true;
        return $case->fees->contains(fn ($fee) => $fee->canAccept($actor));
    }

    public function outstanding(FailedDeliveryCase $case): int
    {
        return max(0, $case->fees->sum(fn ($fee) => $fee->payableCents())
            - (int) $case->payments()->where('status', 'verified')->sum('amount_cents'));
    }

    public function canViewHandover(FailedDeliveryCase $case, $actor): bool
    {
        if (! $this->canView($case, $actor)) return false;
        if ($actor instanceof HubTransportDriver) return (int) $case->return_driver_id === (int) $actor->id;
        return $actor->isAdmin() || ($actor->isLogisticsStaff() && (LogisticsAccess::canAccessCenter($actor, (int) $case->center_id)
                || LogisticsAccess::canAccessCenter($actor, (int) $case->seller_center_id))) || (int) $actor->id === (int) $case->seller_id
            || (int) $case->rider?->user_id === (int) $actor->id
            || (Schema::hasTable('hub_transport_drivers') && (int) $case->return_driver_id === (int) $actor->hubTransportDriver?->id && $case->return_driver_id !== null);
    }

    public function act(Delivery $delivery, $actor, string $action, array $data = []): FailedDeliveryCase
    {
        return DB::transaction(function () use ($delivery, $actor, $action, $data) {
            $delivery = FulfillmentWorkflow::lock($delivery);
            $case = FailedDeliveryCase::where('delivery_id', $delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canView($case, $actor), 403);
            if ($action === 'decide') {
                $this->seller($case, $actor);
                self::check($delivery->deliveryAttemptsExhausted() && $delivery->returned_at, 'Three attempts and physical return to the hub are required.');
                self::check(in_array($data['decision'] ?? '', ['return', 'unclaimed'], true), 'Choose return or unclaimed.');
                if (($data['decision'] ?? '') === 'unclaimed') self::check(filter_var($data['confirm_unclaimed'] ?? false, FILTER_VALIDATE_BOOLEAN), 'Confirm unclaimed storage and the outstanding service fees.');
                if ($case->decision === $data['decision']) return $case;
                self::check(! $case->accepted_at && ! $case->funded_at && ! $case->closed_at
                    && ! $case->payments()->whereIn('status', ['submitted', 'verified'])->exists(), 'This decision is already financially committed.');
                $case->fees()->where('leg', 'return')->delete();
                $case->fill(['decision' => $data['decision'], 'stage' => $data['decision'] === 'return' ? 'awaiting_quote' : 'unclaimed']);
                if ($data['decision'] === 'unclaimed') {
                    $case->accepted_at = now(); $case->closed_at = now();
                    $this->closeOrder($delivery, 'Seller marked the parcel unclaimed after three failed delivery attempts. Stock remains at the hub.');
                }
            } elseif ($action === 'quote') {
                $this->staff($actor, (int) $case->center_id);
                self::check($case->decision === 'return' && in_array($case->stage, ['awaiting_quote', 'awaiting_acceptance'], true)
                    && ! $case->accepted_at, 'Only an unaccepted return quote can be changed.');
                $this->quoteReturn($case, $delivery, $data);
            } elseif ($action === 'accept_quote') {
                $this->seller($case, $actor);
                self::check($case->stage === 'awaiting_acceptance' && (int) ($data['version'] ?? 0) === (int) ($case->quote['version'] ?? 0), 'Refresh and accept the current return quote.');
                self::check(filter_var($data['confirm_quote'] ?? false, FILTER_VALIDATE_BOOLEAN), 'Confirm acceptance of the current service quote.');
                $case->accepted_at = now(); $case->stage = 'awaiting_payment';
            } elseif ($action === 'deposit') {
                $this->seller($case, $actor);
                self::check($case->accepted_at && ! $case->funded_at, 'Accept the current quote before submitting payment.');
                self::check(! $case->payments()->where('status', 'submitted')->exists(), 'The current receipt is awaiting review.');
                $due = $this->outstanding($case);
                self::check($due > 0 && $this->proof($data['proof_path'] ?? null), 'An outstanding balance and an actual transfer receipt are required.');
                $reference = $this->reference($data['reference'] ?? '');
                $old = $case->payments()->where('reference', $reference)->lockForUpdate()->first();
                if ($old && $old->status === 'rejected' && (int) $old->payer_user_id === (int) $actor->id) {
                    $history = $old->revisions ?? [];
                    $history[] = ['proof_path' => $old->proof_path, 'review_note' => $old->review_note, 'at' => now()->toIso8601String()];
                    $old->update(['amount_cents' => $due, 'proof_path' => $data['proof_path'], 'status' => 'submitted',
                        'receipt_viewed_by' => null, 'receipt_viewed_at' => null, 'reviewed_by' => null,
                        'review_note' => null, 'verified_at' => null, 'revisions' => $history]);
                } else {
                    $this->unusedReference($reference);
                    $case->payments()->create(['payer_user_id' => $actor->id, 'amount_cents' => $due,
                        'reference' => $reference, 'proof_path' => $data['proof_path'], 'status' => 'submitted']);
                }
            } elseif ($action === 'review_payment') {
                $this->admin($actor);
                $payment = $case->payments()->whereKey($data['payment_id'] ?? 0)->lockForUpdate()->firstOrFail();
                if ($payment->status === 'verified') return $case;
                self::check($payment->status === 'submitted', 'Only submitted receipts can be reviewed.');
                $approved = filter_var($data['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($approved) {
                    self::check(filter_var($data['confirm_verified'] ?? false, FILTER_VALIDATE_BOOLEAN), 'Confirm that you checked the actual incoming transfer.');
                    self::check($payment->receipt_viewed_at && (int) $payment->receipt_viewed_by === (int) $actor->id
                        && $this->proof($payment->proof_path), 'Open the receipt and verify the actual incoming transfer first.');
                    self::check((int) $payment->amount_cents === $this->outstanding($case), 'The receipt does not match the outstanding quote.');
                }
                $payment->update(['status' => $approved ? 'verified' : 'rejected', 'reviewed_by' => $actor->id,
                    'verified_at' => $approved ? now() : null, 'review_note' => $data['note'] ?? null]);
                if ($approved && $this->outstanding($case->fresh()) === 0) {
                    $case->funded_at = now();
                    if ($case->decision === 'return') $case->stage = 'at_hub';
                }
            } else {
                if ($action === 'seller_receive' && $case->stage === 'completed') {
                    $this->seller($case, $actor);
                    return $case;
                }
                self::check($case->decision === 'return' && $case->funded_at && ! $case->closed_at, 'The seller must accept and fully fund the return before transport.');
                $this->transport($case, $delivery, $actor, $action, $data);
            }
            $case->save();
            if ($case->accepted_at && ! $case->funded_at && $this->outstanding($case->fresh()) === 0) {
                $case->funded_at = now();
                if ($case->decision === 'return') $case->stage = 'at_hub';
                $case->save();
            }
            $this->event($case, $actor, $action);
            return $case->fresh();
        }, 3);
    }

    private function transport(FailedDeliveryCase $case, Delivery $delivery, $actor, string $action, array $data): void
    {
        if ($action === 'driver_accept') {
            $this->driver($case, $actor);
            self::check($case->stage === 'at_hub' && $case->center_id !== $case->seller_center_id, 'This case is not awaiting inter-hub transport.');
            $case->driver_accepted_at = now();
        } elseif ($action === 'depart') {
            $this->driver($case, $actor);
            self::check($case->stage === 'at_hub' && $case->driver_accepted_at, 'Accept the assigned return trip first.');
            $truck = LogisticsTruck::whereKey($case->truck_id)->lockForUpdate()->firstOrFail();
            $driver = HubTransportDriver::whereKey($case->return_driver_id)->lockForUpdate()->firstOrFail();
            self::check($truck->isAssignable() && ! $driver->isBlocked() && $driver->is_online && (int) $truck->driver?->id === (int) $driver->id,
                'The quoted truck and driver are no longer eligible.');
            self::check(! $truck->transfers()->whereIn('status', HubTransfer::ACTIVE_STATUSES)->exists()
                && ! FailedDeliveryCase::where('id', '!=', $case->id)->whereIn('stage', ['in_transfer', 'hub_arrival'])
                    ->where(fn ($q) => $q->where('truck_id', $truck->id)->orWhere('return_driver_id', $driver->id))->exists(), 'The return truck or driver is already in transit.');
            if (Schema::hasTable('return_workflows')) self::check(! \App\Models\ReturnWorkflow::where('driver_id', $driver->id)->where('stage', 'in_transfer')->exists(), 'This driver already has a buyer-return trip in transit.');
            self::check($truck->only(['capacity_kg', 'parcel_capacity']) == ($case->quote['trip']['truck'] ?? []), 'The quoted truck capacity changed.');
            $case->stage = 'in_transfer';
            $this->fulfill($case, 'return:origin_hub');
        } elseif ($action === 'arrive') {
            $this->driver($case, $actor);
            self::check($case->stage === 'in_transfer' && $this->proof($data['proof_path'] ?? null), 'Record the actual hub handover photo for this return trip.');
            $case->driver_arrived_at = now(); $case->handover_proof_path = $data['proof_path'];
            $case->driver_proof_path = $data['proof_path']; $case->stage = 'hub_arrival';
        } elseif ($action === 'receive') {
            $this->staff($actor, (int) $case->seller_center_id);
            self::check($case->stage === 'hub_arrival' && $case->driver_arrived_at && $this->proof($case->driver_proof_path), 'The assigned driver must record arrival and handover first.');
            $case->center_id = $case->seller_center_id; $case->stage = 'at_seller_hub';
            $this->fulfill($case, 'return:inter_hub_driver'); $this->fulfill($case, 'return:destination_hub');
        } elseif ($action === 'assign_rider') {
            $this->staff($actor, (int) $case->center_id);
            self::check(in_array($case->stage, ['at_hub', 'at_seller_hub'], true) && (int) $case->center_id === (int) $case->seller_center_id, 'Receive the parcel at the seller-side hub first.');
            $rider = Rider::whereKey($data['rider_id'] ?? 0)->lockForUpdate()->firstOrFail();
            self::check((int) $rider->center_id === (int) $case->center_id && $rider->assignmentBlockReason() === null
                && $rider->is_online && $rider->activeDeliveryCount() < Rider::MAX_ACTIVE_DELIVERIES
                && $rider->capacityLimit() >= (float) $delivery->weight, 'Choose an eligible, available rider at this hub with sufficient capacity.');
            self::check(! FailedDeliveryCase::where('id', '!=', $case->id)->where('return_rider_id', $rider->id)
                ->whereIn('stage', ['seller_assigned', 'seller_delivery', 'seller_arrival'])->exists(), 'This rider already has a return delivery.');
            $case->return_rider_id = $rider->id; $case->stage = 'seller_assigned';
            $case->fees()->where('service_key', 'return:last_mile_rider')->update(['rider_id' => $rider->id, 'recipient_user_id' => $rider->user_id]);
            if (Schema::hasTable('rider_notifications')) \App\Models\RiderNotification::notifyOnce($rider->id,
                'failed_delivery_return_assigned', ['delivery_id' => $delivery->id, 'failed_delivery_case_id' => $case->id],
                'Seller return assigned', 'Collect this funded return parcel from the hub and deliver it back to the seller.');
        } elseif ($action === 'rider_collect') {
            $this->rider($case, $actor);
            self::check($case->stage === 'seller_assigned', 'Staff must assign this return delivery first.');
            $case->stage = 'seller_delivery'; $case->save();
            app(ReturnToSellerService::class)->dispatch($delivery, $actor);
            $this->fulfill($case, 'return:origin_hub');
        } elseif ($action === 'seller_arrival') {
            $this->rider($case, $actor);
            self::check($case->stage === 'seller_delivery' && $this->proof($data['proof_path'] ?? null), 'Upload the actual seller handover photo.');
            $case->handover_proof_path = $data['proof_path']; $case->seller_proof_path = $data['proof_path']; $case->stage = 'seller_arrival';
        } elseif ($action === 'seller_receive') {
            $this->seller($case, $actor);
            self::check($case->stage === 'seller_arrival' && $this->proof($case->seller_proof_path), 'The rider must record physical seller handover first.');
            app(ReturnToSellerService::class)->confirm($delivery, $actor, $data['note'] ?? null);
            $this->fulfill($case, 'return:last_mile_rider'); $case->stage = 'completed'; $case->closed_at = now();
        } else self::check(false, 'Unknown failed-delivery action.');
    }

    private function outboundFees(FailedDeliveryCase $case, Delivery $delivery): void
    {
        $policy = $delivery->order?->logistics_policy_snapshot
            ? SellerProtectionService::policy($delivery->order, (int) $case->seller_id) : null;
        $rates = $policy['rates'] ?? EscrowEarnings::rates();
        $origin = $delivery->origin_center_id ?: $delivery->center_id;
        if ($delivery->received_at) $this->fee($case, 'outbound:origin_hub', 'origin_hub', 'outbound', self::cents($rates['origin_hub_fee']), ['center_id' => $origin], true);
        if ($delivery->sorting_center_handoff_rider_id) {
            $rider = Rider::find($delivery->sorting_center_handoff_rider_id);
            $quantity = $delivery->order_id && Schema::hasTable('order_items')
                ? $delivery->order->items()->where('seller_id', $case->seller_id)->sum('quantity') : $delivery->items()->sum('quantity');
            $quantity = max(1, (int) $quantity);
            $this->fee($case, 'outbound:pickup_rider', 'pickup_rider', 'outbound', self::cents($rates['pickup_rider_per_item'] * $quantity),
                ['rider_id' => $rider?->id, 'recipient_user_id' => $rider?->user_id], true);
        }
        foreach (EscrowEarnings::transferLegs($delivery)->where('status', 'received') as $leg) {
            // A truck trip has its own funded settlement. Do not bill a
            // parcel again for a driver obligation already on that ledger.
            if (Schema::hasTable('escrow_remittances') && EscrowRemittance::where('hub_transfer_id', $leg->id)
                ->where('recipient_role', 'inter_hub_driver')->whereNotNull('settlement_reference')
                ->where('status', '!=', 'voided')->exists()) continue;
            $amount = $leg->payment_snapshot['allocations'][$delivery->id] ?? self::cents($rates['inter_hub_driver_fee']);
            $this->fee($case, 'outbound:trip:'.$leg->id, 'inter_hub_driver', 'outbound', (int) $amount, ['driver_id' => $leg->driver_id], true, $leg->id);
        }
        if ((int) $origin !== (int) $delivery->center_id) $this->fee($case, 'outbound:destination_hub', 'destination_hub', 'outbound', self::cents($rates['destination_hub_fee']), ['center_id' => $delivery->center_id], true);
        $failures = DB::table('delivery_failures')->where('delivery_id', $delivery->id)->whereNotNull('rider_id')->get()->groupBy('rider_id');
        if ($failures->isEmpty() && $delivery->rider_id) $failures = collect([$delivery->rider_id => collect([1])]);
        $total = self::cents($delivery->delivery_fee ?? $rates['last_mile_rider_fee']); $left = $total; $count = max(1, $failures->sum->count());
        foreach ($failures as $id => $attempts) {
            $share = $id === $failures->keys()->last() ? $left : intdiv($total * $attempts->count(), $count);
            $left -= $share; $rider = Rider::find($id);
            $this->fee($case, 'outbound:attempt_rider:'.$id, 'last_mile_rider', 'outbound', $share,
                ['rider_id' => $id, 'recipient_user_id' => $rider?->user_id], true);
        }
    }

    private function quoteReturn(FailedDeliveryCase $case, Delivery $delivery, array $data): void
    {
        $from = $case->center; $to = LogisticsCenter::findOrFail($data['seller_center_id'] ?? $case->seller_center_id);
        self::check(ReturnRouting::operational($from) && ReturnRouting::operational($to), 'Choose operational return hubs.');
        $rates = EscrowEarnings::rates(); $trip = null; $driver = null; $truck = null;
        if ($from->id !== $to->id) {
            $truck = LogisticsTruck::whereKey($data['truck_id'] ?? 0)->lockForUpdate()->firstOrFail(); $driver = $truck->driver;
            self::check($truck->isAssignable() && $truck->status === 'available' && $driver && ! $driver->isBlocked()
                && $driver->is_online && ! $truck->hasActiveTransfer() && ! $driver->hasActiveTransfer()
                && (int) $truck->logistics_company_id === (int) $from->logistics_company_id, 'Choose an available approved truck and driver from the collection hub company.');
            $route = app(HubRoadDistanceService::class)->between($from, $to);
            $trip = app(ParcelInterHubPricing::class)->calculate($truck->only(['capacity_kg', 'parcel_capacity']),
                [['id' => $delivery->id, 'weight' => $delivery->weight]], $route['distance'], $rates);
        }
        $case->fees()->where('leg', 'return')->delete();
        $this->fee($case, 'return:origin_hub', 'origin_hub', 'return', self::cents($rates['origin_hub_fee']), ['center_id' => $from->id]);
        if ($trip) {
            $this->fee($case, 'return:inter_hub_driver', 'inter_hub_driver', 'return', $trip['total'], ['driver_id' => $driver->id]);
            $this->fee($case, 'return:destination_hub', 'destination_hub', 'return', self::cents($rates['destination_hub_fee']), ['center_id' => $to->id]);
        }
        $this->fee($case, 'return:last_mile_rider', 'last_mile_rider', 'return', self::cents($rates['last_mile_rider_fee']), []);
        $case->fill(['seller_center_id' => $to->id, 'return_driver_id' => $driver?->id, 'truck_id' => $truck?->id,
            'stage' => 'awaiting_acceptance', 'quoted_at' => now(), 'quote' => ['version' => (int) ($case->quote['version'] ?? 0) + 1,
                'rates' => $rates, 'trip' => $trip, 'outbound_once' => true]]);
    }

    private function fee(FailedDeliveryCase $case, string $key, string $role, string $leg, int $amount, array $recipient, bool $fulfilled = false, ?int $tripId = null): void
    {
        $credited = 0;
        if ($leg === 'outbound' && Schema::hasTable('escrow_remittances')) {
            $paid = EscrowRemittance::where('recipient_role', $role)->whereIn('status', ['remitted', 'accepted']);
            if ($tripId) $paid->where('hub_transfer_id', $tripId)->where(fn ($q) => $q->where('delivery_id', $case->delivery_id)
                ->when($case->delivery->order_id, fn ($q) => $q->orWhere(fn ($legacy) => $legacy->whereNull('delivery_id')->where('order_id', $case->delivery->order_id)->where('seller_id', $case->seller_id))));
            else $paid->where(fn ($q) => $q->where('delivery_id', $case->delivery_id)
                ->when($case->delivery->order_id, fn ($q) => $q->orWhere(fn ($legacy) => $legacy->whereNull('delivery_id')->where('order_id', $case->delivery->order_id)->where('seller_id', $case->seller_id))));
            if (! empty($recipient['rider_id'])) $paid->where('rider_id', $recipient['rider_id']);
            if (! empty($recipient['center_id'])) $paid->where('logistics_center_id', $recipient['center_id']);
            $credited = min($amount, self::cents($paid->sum('amount')));
        }
        $case->fees()->create($recipient + ['service_key' => $key, 'role' => $role, 'leg' => $leg,
            'amount_cents' => $amount, 'credited_cents' => $credited,
            'status' => $credited >= $amount ? 'credited' : ($fulfilled ? 'eligible' : 'reserved'), 'fulfilled_at' => $fulfilled ? now() : null]);
    }

    public function payout(FailedDeliveryFee $fee, $actor, bool $accept, array $data = []): void
    {
        DB::transaction(function () use ($fee, $actor, $accept, $data) {
            $delivery = FulfillmentWorkflow::lock($fee->resolution->delivery);
            $case = FailedDeliveryCase::whereKey($fee->failed_delivery_case_id)->lockForUpdate()->firstOrFail();
            $fee = FailedDeliveryFee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            if ($accept) {
                abort_unless($fee->canAccept($actor), 403);
                if ($fee->status === 'accepted') return;
                self::check($fee->status === 'remitted', 'Admin must remit the actual payment before it can be accepted.');
                $fee->update(['status' => 'accepted', 'accepted_at' => now()]);
            } else {
                $this->admin($actor);
                if (in_array($fee->status, ['remitted', 'accepted'], true)) return;
                self::check($case->funded_at && $fee->fulfilled_at && $fee->status === 'eligible'
                    && $fee->payableCents() > 0, 'Only funded, completed service allocations can be released.');
                $spent = $case->fees()->whereIn('status', ['remitted', 'accepted'])->get()->sum(fn ($row) => $row->payableCents());
                self::check($spent + $fee->payableCents() <= (int) $case->payments()->where('status', 'verified')->sum('amount_cents'), 'Insufficient verified funds.');
                PayoutAccounts::assertReady($fee);
                self::check($this->proof($data['proof_path'] ?? null), 'Upload proof of the actual worker transfer.');
                $reference = $this->reference($data['reference'] ?? ''); $this->unusedReference($reference);
                $fee->update(['status' => 'remitted', 'reference' => $reference, 'proof_path' => $data['proof_path'],
                    'remitted_by' => $actor->id, 'remitted_at' => now()]);
            }
            $this->event($case, $actor, $accept ? 'fee_accepted' : 'fee_remitted', ['fee_id' => $fee->id]);
        }, 3);
    }

    private function closeOrder(Delivery $delivery, string $reason): void
    {
        if (! $delivery->order_id) return;
        $order = Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
        $otherActive = $order->deliveries()->where('id', '!=', $delivery->id)->whereNotIn('status', ['returned_to_seller', 'cancelled'])
            ->whereNotIn('id', FailedDeliveryCase::where('stage', 'unclaimed')->select('delivery_id'))->exists();
        if (! $otherActive && $order->status !== 'cancelled') {
            $from = $order->status; $order->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => 'cancelled', 'note' => $reason]);
            // Reuse the existing cancellation/refund review; never restore
            // stock for goods still physically held as unclaimed at the hub.
            Payments::voidForCancel($order, strict: true);
        }
    }

    private function fulfill(FailedDeliveryCase $case, string $key): void
    {
        $case->fees()->where('service_key', $key)->where('status', 'reserved')->update(['status' => 'eligible', 'fulfilled_at' => now()]);
    }

    private function seller(FailedDeliveryCase $case, $actor): void { abort_unless($actor instanceof User && $actor->isActive() && $actor->isSeller() && (int) $actor->id === (int) $case->seller_id, 403); }
    private function admin($actor): void { abort_unless($actor instanceof User && $actor->isActive() && $actor->isAdmin(), 403); }
    private function staff($actor, int $hub): void { abort_unless($actor instanceof User && $actor->isActive() && LogisticsAccess::canProcessParcels($actor) && LogisticsAccess::canAccessCenter($actor, $hub), 403); }
    private function rider(FailedDeliveryCase $case, $actor): void { abort_unless($actor instanceof User && $actor->isActive() && (int) $case->rider?->user_id === (int) $actor->id, 403); }
    private function driver(FailedDeliveryCase $case, $actor): void
    {
        $driver = $actor instanceof HubTransportDriver ? $actor : ($actor instanceof User && $actor->isActive() ? $actor->hubTransportDriver : null);
        abort_unless($driver && ! $driver->isBlocked() && (! $driver->user || $driver->user->isActive()) && (int) $driver->id === (int) $case->return_driver_id, 403);
    }
    private function proof(?string $path): bool { return $path && Storage::disk('private')->exists($path); }
    public static function cents($amount): int
    {
        self::check(is_numeric($amount) && is_finite((float) $amount) && (float) $amount >= 0 && (float) $amount < 1000000000, 'Invalid service fee.');
        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }
    private static function check(bool $ok, string $message): void { if (! $ok) throw ValidationException::withMessages(['failed_delivery' => $message]); }
    private function reference(string $value): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim($value)));
        self::check(strlen($value) >= 4 && strlen($value) <= 150, 'Enter a complete bank/e-wallet transfer reference.'); return $value;
    }
    private function unusedReference(string $reference): void
    {
        foreach (['failed_delivery_payments' => 'reference', 'failed_delivery_fees' => 'reference',
            'return_fund_payments' => 'reference', 'return_allocations' => 'reference', 'escrow_remittances' => 'bank_reference',
            'return_workflows' => 'refund_reference'] as $table => $column) {
            if (Schema::hasTable($table)) self::check(! DB::table($table)->whereRaw('UPPER(REPLACE('.$column.", ' ', '')) = ?", [$reference])->exists(), 'This transfer reference was already used.');
        }
    }
    private function event(FailedDeliveryCase $case, $actor, string $action, array $extra = []): void
    {
        $events = $case->events ?? []; $events[] = $extra + ['action' => $action, 'actor_id' => $actor?->id,
            'actor_type' => $actor ? class_basename($actor) : null, 'stage' => $case->stage, 'at' => now()->toIso8601String()];
        $case->update(['events' => $events]);
    }
}
