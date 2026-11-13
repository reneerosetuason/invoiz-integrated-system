<?php

namespace App\Services;

use App\Models\{Order, ReturnRequest, ReturnWorkflow, EscrowRemittance};
use App\Support\{LogisticsAccess, Payments, Stock, EscrowEarnings};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reverse custody is separate from forward deliveries and has its own funded service ledger. */
class ReturnWorkflowService
{
    /** No physical return is required for a cancelled, unfulfilled prepaid order. */
    public static function queueRefund(Order $order, float $amount, string $reason): void
    {
        if ($amount <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('return_workflows')) { return; }
        self::require(! $order->returnRequests()->whereIn('status', ['requested', 'approved', 'item_received'])->exists(), 'Resolve the existing return before creating another refund for this order.');
        $items = $order->items()->get();
        $gross = (float) $items->sum(fn ($i) => $i->lineTotal);
        self::require($items->isNotEmpty() && $gross > 0, 'The original purchased items are required for a refund.');
        $remaining = round($amount, 2);
        foreach ($items as $index => $item) {
            $share = $index === $items->count() - 1 ? $remaining : min($remaining, round($amount * $item->lineTotal / $gross, 2));
            $remaining = round($remaining - $share, 2);
            if ($share <= 0) { continue; }
            $subtotal = $order->sellerSubtotal((int) $item->seller_id);
            $discount = $subtotal > 0 ? $order->voucherDiscountForSeller((int) $item->seller_id) * $item->lineTotal / $subtotal : 0;
            $prior = ReturnRequest::where('order_item_id', $item->id)->where('status', 'refunded')->get()->sum(fn ($r) => $r->merchandiseRefundAmount());
            $merchandise = round(min($share, max(0, $item->lineTotal - $discount - $prior)), 2);
            $case = ReturnRequest::create(['order_id' => $order->id, 'order_item_id' => $item->id,
                'buyer_id' => $order->buyer_id, 'seller_id' => $item->seller_id, 'product_id' => $item->product_id,
                'quantity' => 0, 'reason' => 'other', 'reason_details' => $reason,
                'refund_amount' => $share, 'status' => 'item_received', 'decision_note' => 'Admin refund payment required. '.$reason]);
            ReturnWorkflow::create(['return_request_id' => $case->id, 'stage' => 'refund_pending', 'events' => [[
                'action' => 'refund_approved', 'actor_id' => auth()->id(), 'at' => now()->toIso8601String(),
                'reference' => null, 'merchandise_amount' => $merchandise,
            ]]]);
        }
    }

    public static function start(ReturnRequest $return): ReturnWorkflow
    {
        ReturnSettlementService::ensure($return);
        $delivery = EscrowEarnings::deliveryForSeller($return->order, (int) $return->seller_id);
        return ReturnWorkflow::firstOrCreate(['return_request_id' => $return->id], [
            'center_id' => $delivery?->destination_center_id ?: $delivery?->center_id,
            'stage' => 'awaiting_collection',
        ]);
    }

    public static function change(ReturnRequest $record, $actor, string $action, array $data = []): void
    {
        DB::transaction(function () use ($record, $actor, $action, $data) {
            $order = Order::lockForUpdate()->findOrFail($record->order_id);
            $return = ReturnRequest::lockForUpdate()->findOrFail($record->id);
            $flow = ReturnWorkflow::where('return_request_id', $return->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isActive(), 403);
            $managed=\Illuminate\Support\Facades\Schema::hasTable('return_settlements')?$return->settlement:null;
            if($managed?->keepItemDirect() && in_array($action,['refund','confirm_refund'],true)){
                KeepItemRefundService::change($order,$return,$flow,$actor,$action,$data);return;
            }
            $seller = (int) $return->seller_id === (int) $actor->id;
            $buyer = (int) $return->buyer_id === (int) $actor->id;
            $staff = LogisticsAccess::isCenterStaff($actor, (int) $flow->center_id);
            $assigned = $actor->role === 'rider' && $actor->rider && (int) $actor->rider->id === (int) $flow->rider_id;
            $stage = $flow->stage;
            $updates = [];
            if (in_array($action, ['route_hub', 'assign', 'pickup', 'intake', 'transfer', 'receive_transfer', 'dispatch_seller', 'deliver_seller'], true)) {
                self::require($return->status === 'approved', 'Only an approved open return can move through logistics.');
            }

            if (in_array($action, ['route_hub', 'assign', 'pickup', 'intake', 'transfer', 'receive_transfer', 'dispatch_seller', 'deliver_seller', 'receive'], true)) {
                ReturnSettlementService::beforeCustody($return, $action, $data);
            }

            if ($action === 'route_hub') {
                abort_unless($actor->isLogisticsAdmin(), 403);
                self::require($stage === 'awaiting_collection' && ! $flow->center_id, 'This return already has a handling hub.');
                $center = \App\Models\LogisticsCenter::findOrFail($data['destination_center_id'] ?? 0);
                $updates = ['center_id' => $center->id];
            } elseif ($action === 'assign') {
                abort_unless($staff, 403);
                self::require(in_array($stage, ['awaiting_collection', 'pickup_assigned'], true), 'This return is already in transit.');
                $rider = ReturnSettlementService::riderForAssignment((int)($data['rider_id']??0),(int)$flow->center_id);
                $updates = ['rider_id' => $rider->id, 'stage' => 'pickup_assigned'];
            } elseif ($action === 'pickup') {
                abort_unless($assigned, 403);
                self::require($stage === 'pickup_assigned', 'The return must be assigned before collection.');
                $updates = ['stage' => 'collected'];
            } elseif ($action === 'intake') {
                abort_unless($staff, 403);
                self::require($stage === 'collected', 'The assigned rider must confirm collection first.');
                $updates = ['stage' => 'at_hub', 'rider_id' => null];
            } elseif ($action === 'transfer') {
                abort_unless($staff, 403);
                self::require($stage === 'at_hub', 'Receive the parcel at this hub first.');
                $target = (int) ($data['destination_center_id'] ?? 0);
                $agreement=\Illuminate\Support\Facades\Schema::hasTable('return_settlements')?$return->settlement:null;
                if($agreement && (int)$agreement->seller_center_id!==$target){
                    ReturnRouting::retarget($return,$actor,$target);
                    $agreement=$return->settlement;
                }
                $agreedDestination=$agreement && $agreement->collectionReady() && (int)$agreement->seller_center_id===$target
                    && ReturnRouting::operational(\App\Models\LogisticsCenter::find($target));
                $originalReverse=$agreement?->directAgreement() && $agreement->collectionReady() && $agreement->agreed()
                    && (int)$agreement->collection_center_id===(int)$flow->center_id && (int)$agreement->seller_center_id===$target
                    && ReturnFeeService::originalReverseRoute($return,(int)$flow->center_id,$target);
                self::require($target > 0 && $target !== (int) $flow->center_id && LogisticsAccess::canAccessCenter($actor, $target)
                    || ($target > 0 && $target !== (int) $flow->center_id && self::sameCompany((int) $flow->center_id, $target))
                    || $originalReverse || $agreedDestination, 'Choose an active receiving hub for this return.');
                $ref = ! empty($data['reference']) ? trim($data['reference']) : ('RET-TRF-'.$return->id.'-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(4)));
                $updates = ['stage' => 'in_transfer', 'destination_center_id' => $target, 'transport_reference' => $ref];
                if($agreement && !empty($data['driver_id']))$updates+=['driver_id'=>(int)$data['driver_id'],'driver_accepted_at'=>null,'driver_arrived_at'=>null];
            } elseif ($action === 'receive_transfer') {
                abort_unless(LogisticsAccess::isCenterStaff($actor, (int) $flow->destination_center_id), 403);
                self::require($stage === 'in_transfer', 'This parcel is not on a return transfer.');
                $updates = ['stage' => 'at_hub', 'center_id' => $flow->destination_center_id, 'destination_center_id' => null];
            } elseif ($action === 'dispatch_seller') {
                abort_unless($staff, 403);
                self::require($stage === 'at_hub', 'Receive the returned parcel at the hub first.');
                $ref = ! empty($data['reference']) ? trim($data['reference']) : ('RET-DSP-'.$return->id.'-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(4)));
                $updates = ['stage' => 'sent_to_seller', 'transport_reference' => $ref];
                if (ReturnSettlementService::ensure($return)) {
                    $rider=ReturnSettlementService::riderForAssignment((int)($data['rider_id']??0),(int)$flow->center_id);
                    $updates['rider_id']=$rider->id;
                }
            } elseif ($action === 'deliver_seller') {
                abort_unless($assigned, 403);
                self::require($stage === 'sent_to_seller' && ! empty($data['proof_path']), 'Record the seller-delivery handover photo.');
                $updates = ['stage' => 'seller_arrival'];
            } elseif ($action === 'receive') {
                abort_unless($seller, 403);
                self::require($return->status === 'approved' && in_array($stage, ['sent_to_seller', 'seller_arrival'], true), 'Logistics must dispatch this return before you confirm physical receipt.');
                self::require($return->item && (int) $return->item->seller_id === (int) $return->seller_id, 'The return item does not belong to this seller.');
                $otherQuantity = (int) ReturnRequest::where('order_item_id', $return->order_item_id)->whereKeyNot($return->id)
                    ->whereIn('status', ['approved', 'item_received', 'refunded'])->sum('quantity');
                self::require($return->quantity > 0 && $otherQuantity + $return->quantity <= $return->item->quantity, 'This return exceeds the purchased quantity.');
                if (! empty($data['restock']) && ! $flow->stock_restored) {
                    Stock::restore($return->item, (int) $return->quantity);
                    $updates['stock_restored'] = true;
                }
                $return->update(['status' => 'item_received']);
                $updates['stage'] = 'refund_pending';
                if ((float) $return->refund_amount <= 0 && ! ReturnSettlementService::ensure($return)) {
                    $return->update(['status' => 'refunded', 'resolved_at' => now()]);
                    $updates['stage'] = 'completed_no_refund';
                }
            } elseif ($action === 'refund') {
                $agreement=ReturnSettlementService::ensure($return);
                $sellerDirect=$agreement?->physicalDirectRefund();
                abort_unless($sellerDirect ? $seller : $actor->isAdmin(), 403);
                if ($agreement = ReturnSettlementService::ensure($return)) {
                    ReturnSettlementService::assertFunded($return);
                    self::require($agreement->resolution !== 'exchange', 'An exchange closes after the buyer receives the replacement, not through a cash refund.');
                    self::require($agreement->destination_verified_at !== null && ! empty($agreement->refund_destination), 'The buyer must verify their private refund receiving details first.');
                    self::require(ReturnSettlementService::cents($return->refund_amount) === (int) $agreement->net_refund_cents, 'The refund must match the agreed net amount.');
                    self::require((float) ($data['recovery_amount'] ?? 0) === 0.0, 'Submit and verify refund recovery through the agreement ledger before funding is unlocked.');
                    if (empty($data['reference'])) {
                        $data['reference'] = 'REF-RET-'.$return->id.'-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(4));
                    } else {
                        $data['reference'] = strtoupper(preg_replace('/\s+/', '', trim($data['reference'])));
                    }
                    self::require(strlen($data['reference']) >= 4 && ! \App\Models\ReturnAllocation::where('reference', $data['reference'])->exists()
                        && ! ReturnWorkflow::where('refund_reference',$data['reference'])->exists()
                        && !(\Illuminate\Support\Facades\Schema::hasTable('direct_refund_transfers') && \App\Models\DirectRefundTransfer::where('reference',$data['reference'])->exists())
                        && ! EscrowRemittance::where('bank_reference', $data['reference'])->exists(), 'Use a unique actual outgoing refund reference.');
                }
                if (empty($data['reference'])) {
                    $data['reference'] = 'REF-RET-'.$return->id.'-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(4));
                }
                $waived = isset($agreement) && $agreement && ! $agreement->physicalReturn() && $return->status === 'approved' && $flow->stage === 'refund_pending';
                self::require(($return->status === 'item_received' || $waived) && ! $flow->refund_sent_at, 'This refund is not awaiting payment.');
                self::require(! empty($data['proof_path']), 'Record the actual transfer proof before sending the refund.');
                if($sellerDirect)self::require(\Illuminate\Support\Facades\Storage::disk('private')->exists($data['proof_path']),'Upload the actual direct-refund receipt before recording payment.');
                $amount = (float) $return->refund_amount;
                $tx = Payments::transactionFor($order);
                $funding = $sellerDirect ? ['funded'=>$amount,'shortfall'=>0] : self::fundingSummary($order, $amount, isset($agreement) ? $agreement?->id : null);
                $funded = $funding['funded'];
                $shortfall = $funding['shortfall'];
                self::require($funded > 0 || (GcashEscrowService::isGcash($order) && $order->payment?->proof_path
                    && (float) ($data['recovery_amount'] ?? 0) >= $amount), 'Reconcile the collected COD or recover the seller-held GCash into escrow before refunding.');
                if ($shortfall > 0) {
                    self::require(round((float) ($data['recovery_amount'] ?? 0), 2) >= $shortfall
                        && ! empty($data['recovery_reference']) && ! empty($data['recovery_proof_path']), 'Previously released funds must be recovered into escrow first. Required recovery: PHP '.number_format($shortfall, 2));
                    $updates += ['recovery_amount' => $data['recovery_amount'], 'recovery_reference' => $data['recovery_reference'], 'recovery_proof_path' => $data['recovery_proof_path']];
                }
                if ($amount > 0) {
                    self::require(round($amount + (float) ($tx?->refunded_amount ?? 0), 2) <= round((float) $order->total_amount, 2), 'This refund would exceed the buyer payment.');
                    if(!$sellerDirect)Payments::refund($order, $amount, 'Return #'.$return->id.' transfer '.$data['reference']);
                }
                if (isset($agreement) && $agreement) $agreement->update(['refund_reserved_cents' => 0]);
                $updates += ['stage' => 'refund_sent', 'refund_reference' => $data['reference'], 'refund_proof_path' => $data['proof_path'], 'refund_sent_at' => now()];
                if($sellerDirect)$updates+=['refund_source'=>'seller_direct','refund_sender_user_id'=>$actor->id];
            } elseif ($action === 'confirm_refund') {
                abort_unless($buyer, 403);
                if ($flow->buyer_confirmed_at) { return; }
                self::require($flow->refund_sent_at !== null, 'The refund sender has not recorded a payment yet.');
                if($flow->refund_source==='seller_direct'){
                    $tx=Payments::transactionFor($order);
                    self::require(round((float)$return->refund_amount+(float)($tx?->refunded_amount??0),2)<=round((float)$order->total_amount,2),'This refund would exceed the buyer payment.');
                    Payments::refund($order,(float)$return->refund_amount,'Buyer confirmed seller-direct refund; return #'.$return->id);
                }
                $updates = ['stage' => 'completed', 'buyer_confirmed_at' => now()];
                $return->update(['status' => 'refunded', 'resolved_at' => now()]);
                if (! $order->returnRequests()->whereIn('status', ['requested', 'approved', 'item_received'])->exists()) {
                    $order->paymentDisputes()->where('status', 'awaiting_refund')->update(['status' => 'resolved', 'resolved_at' => now()]);
                }
            } else {
                throw ValidationException::withMessages(['return' => 'Unknown return action.']);
            }
            ReturnSettlementService::custody($return, $action, $data, $actor);
            $events = $flow->events ?? [];
            $events[] = ['action' => $action, 'actor_id' => $actor->id, 'at' => now()->toIso8601String(), 'reference' => $data['reference'] ?? null,
                'from' => $stage, 'to' => $updates['stage'] ?? $stage, 'restocked' => $updates['stock_restored'] ?? false,
                'proof_path' => $data['proof_path'] ?? null];
            $flow->update($updates + ['events' => $events]);
            if ($action === 'refund') {
                $fresh = $order->fresh();
                $split = Payments::splitForSellers($fresh);
                $sum = fn ($key) => round(array_sum(array_column($split, $key)), 2);
                Payments::transactionFor($fresh)?->update(['seller_earning' => $sum('earning'), 'platform_commission' => $sum('commission')]);
                $fresh->update(['commission_amount' => $sum('commission')]);
                EscrowEarnings::syncRemittancesForOrder($fresh);
            }
        }, 3);
    }

    /** Read-only preview; the same calculation is repeated under the order lock at submission. */
    public static function fundingSummary(Order $order, float $amount, ?int $excludeRefundSettlementId = null): array
    {
        $tx = Payments::transactionFor($order);
        $externalRefunds=\Illuminate\Support\Facades\Schema::hasTable('return_settlements') && \Illuminate\Support\Facades\Schema::hasColumn('return_workflows','refund_source')?BuyerFaultReturnService::externalRefundTotal($order):0;
        // COD must first be reconciled; a delivered parcel alone is not proof of cash in escrow.
        $funded = GcashEscrowService::isGcash($order)
            ? (float) (\App\Models\GcashEscrowDeposit::where('order_id', $order->id)->where('status', 'funded')->whereNotNull('confirmed_at')->value('amount') ?? 0)
            : ((in_array($tx?->status, ['successful', 'partially_refunded'], true) || ($tx?->status==='refunded' && $externalRefunds>0)) ? (float) $tx->amount : 0);
        $released = (float) EscrowRemittance::where('order_id', $order->id)->whereIn('status', ['remitted', 'accepted'])
            ->where('settlement_mode', '!=', 'cod_retained')->sum('amount');
        // COD delivery fees were retained in cash and are unavailable for refunds too.
        if (! GcashEscrowService::isGcash($order)) {
            $released += (float) EscrowRemittance::where('order_id', $order->id)->where('settlement_mode', 'cod_retained')->whereIn('status', ['remitted', 'accepted'])->sum('amount');
        }
        $previousRecovery = \Illuminate\Support\Facades\Schema::hasTable('return_workflows') ? (float) ReturnWorkflow::whereHas('returnRequest', fn ($q) => $q->where('order_id', $order->id))->sum('recovery_amount') : 0;
        $returnReserves = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('return_settlements')) {
            $recoveryQuery = \App\Models\ReturnFundPayment::whereHas('settlement', fn ($q) => $q->where('order_id', $order->id))
                ->where('purpose', 'refund_recovery')->where('status', 'verified');
            $previousRecovery += ((clone $recoveryQuery)->sum('amount_cents') - (clone $recoveryQuery)->sum('returned_cents')) / 100;
            $returnReserves = \App\Models\ReturnSettlement::where('order_id', $order->id)->sum('reserved_cents') / 100;
            if (\Illuminate\Support\Facades\Schema::hasColumn('return_settlements', 'seller_reserved_cents')) {
                $returnReserves += \App\Models\ReturnSettlement::where('order_id', $order->id)->sum('seller_reserved_cents') / 100;
                $returnReserves += \App\Models\ReturnSettlement::where('order_id', $order->id)
                    ->when($excludeRefundSettlementId, fn ($q) => $q->whereKeyNot($excludeRefundSettlementId))
                    ->sum('refund_reserved_cents') / 100;
            }
            $returnReserves += \App\Models\ReturnAllocation::whereHas('settlement', fn ($q) => $q->where('order_id', $order->id))
                ->whereNotNull('fund_payment_id')->where('status', 'eligible')->sum('amount_cents') / 100;
        }
        $reservedLogistics = (float) EscrowRemittance::where('order_id', $order->id)
            ->whereNotIn('recipient_role', [EscrowRemittance::ROLE_SELLER, EscrowRemittance::ROLE_ADMIN])
            ->whereIn('status', ['held_in_escrow', 'pending_remittance'])->sum('amount');
        $tripBudget = TripPaymentService::orderBudget($order);
        $released += $tripBudget['released'];
        $reservedLogistics += $tripBudget['reserved'];
        $platformRefunds=max(0,(float)($tx?->refunded_amount??0)-$externalRefunds);
        $shortfall = round(max(0, $amount + $released + $reservedLogistics + $returnReserves + $platformRefunds - $funded - $previousRecovery), 2);
        $available = round(max(0, $funded + $previousRecovery - $released - $reservedLogistics - $returnReserves - $platformRefunds), 2);
        // A multi-seller order is not a shared wallet: another store's held
        // proceeds cannot fund this seller's refund or return service costs.
        if ($excludeRefundSettlementId && ($case = ReturnRequest::whereHas('settlement', fn ($q) => $q->whereKey($excludeRefundSettlementId))->first())) {
            $sellerId = (int) $case->seller_id;
            $calc = EscrowEarnings::calculateForSeller($order, $sellerId);
            $originalShare = $calc['gross_merch'] - $calc['merch_discount'] + $calc['buyer_shipping_paid'];
            $sellerRows = EscrowRemittance::where('order_id', $order->id)->where('seller_id', $sellerId);
            $ownLogistics = (clone $sellerRows)->whereNotIn('recipient_role', ['seller','admin'])->where('status','!=','voided')->sum('amount');
            $ownTripBudget = TripPaymentService::orderBudget($order, $sellerId);
            $ownLogistics += $ownTripBudget['reserved'] + $ownTripBudget['released'];
            $ownReleased = (clone $sellerRows)->whereIn('recipient_role', ['seller','admin'])->whereIn('status',['remitted','accepted'])->sum('amount');
            $ownRefunds = ReturnRequest::where('order_id',$order->id)->where('seller_id',$sellerId)
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('return_workflows','refund_source'),fn($q)=>$q->whereDoesntHave('workflow',fn($w)=>$w->where('refund_source','seller_direct')))
                ->where(fn ($q) => $q->where('status','refunded')->orWhereHas('workflow',fn ($w) => $w->whereNotNull('refund_sent_at')))->sum('refund_amount');
            $ownSettlements = \App\Models\ReturnSettlement::where('order_id',$order->id)->whereHas('returnRequest',fn ($q) => $q->where('seller_id',$sellerId));
            $ownReserved = (clone $ownSettlements)->sum('reserved_cents') / 100;
            if (\Illuminate\Support\Facades\Schema::hasColumn('return_settlements','seller_reserved_cents')) {
                $ownReserved += (clone $ownSettlements)->sum('seller_reserved_cents') / 100;
                $ownReserved += (clone $ownSettlements)->whereKeyNot($excludeRefundSettlementId)->sum('refund_reserved_cents') / 100;
            }
            $ownRecoveryQuery = \App\Models\ReturnFundPayment::whereHas('settlement.returnRequest',fn ($q) => $q->where('order_id',$order->id)->where('seller_id',$sellerId))
                ->where('purpose','refund_recovery')->where('status','verified');
            $ownRecovery = ((clone $ownRecoveryQuery)->sum('amount_cents') - (clone $ownRecoveryQuery)->sum('returned_cents')) / 100;
            $ownRecovery += (float) ReturnWorkflow::whereHas('returnRequest',fn ($q) => $q->where('order_id',$order->id)->where('seller_id',$sellerId))->sum('recovery_amount');
            $ownReserved += \App\Models\ReturnAllocation::whereHas('settlement.returnRequest',fn ($q) => $q->where('order_id',$order->id)->where('seller_id',$sellerId))
                ->whereNotNull('fund_payment_id')->where('status','eligible')->sum('amount_cents') / 100;
            $unusedRefunds = \App\Models\ReturnAllocation::whereHas('settlement',fn ($q) => $q->where('order_id',$order->id)->where('funding_method','deduct_refund')
                ->whereHas('returnRequest',fn ($r) => $r->where('seller_id',$sellerId)))
                ->where('role','unused_budget_refund')->whereIn('status',['remitted','accepted'])->sum('amount_cents') / 100;
            $ownAvailable = round(max(0, $originalShare + $ownRecovery - $ownLogistics - $ownReleased - $ownRefunds - $ownReserved - $unusedRefunds), 2);
            $available = min($available, $ownAvailable);
            $shortfall = max($shortfall, round(max(0, $amount - $ownAvailable), 2));
        }
        return ['funded' => $funded, 'shortfall' => $shortfall, 'released' => $released, 'reserved_logistics' => $reservedLogistics, 'return_reserves' => $returnReserves, 'available' => $available,'seller_direct_refunds'=>$externalRefunds];
    }

    private static function sameCompany(int $origin, int $destination): bool
    {
        $company = \App\Models\LogisticsCenter::whereKey($origin)->value('logistics_company_id');
        return $company && \App\Models\LogisticsCenter::whereKey($destination)->where('logistics_company_id', $company)->exists();
    }

    private static function require(bool $condition, string $message): void
    {
        if (! $condition) { throw ValidationException::withMessages(['return' => $message]); }
    }
}
