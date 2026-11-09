<?php

namespace App\Services;

use App\Models\{ReturnRequest, ReturnSettlement, ReturnFundPayment, ReturnAllocation, Order, User, Rider, HubTransportDriver, LogisticsCenter};
use App\Support\{LogisticsAccess, EscrowEarnings, Payments};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;

class ReturnSettlementService
{
    public static function cents($amount): int { return (int) round((float) $amount * 100); }
    private static function require(bool $valid, string $message): void
    {
        if (! $valid) throw ValidationException::withMessages(['return' => $message]);
    }

    public static function ensure(ReturnRequest $case): ?ReturnSettlement
    {
        if (! Schema::hasTable('return_settlements') || $case->quantity <= 0) return null;
        if ($existing = $case->settlement()->first()) return $existing;
        if (! $case->isOpen()) return null;
        // Preserve already-moving legacy returns. Their completed custody must
        // not be retroactively re-quoted or billed as new work.
        if ($case->workflow && $case->workflow->stage !== 'awaiting_collection') return null;
        $delivery = EscrowEarnings::deliveryForSeller($case->order, (int) $case->seller_id);
        $direct=Schema::hasColumn('return_settlements','workflow_policy');
        $defaults=$direct?ReturnFeeService::defaultRoute($case):[];
        $settlement = ReturnSettlement::firstOrCreate(['return_request_id' => $case->id], [
            'order_id' => $case->order_id,
            'maximum_refund_cents' => self::cents($case->refund_amount),
            'merchandise_cents' => self::cents($case->refund_amount),
            'net_refund_cents' => self::cents($case->refund_amount),
            'response_due_at' => now()->addHours(48),
            'collection_center_id' => $defaults['collection_center_id']??($delivery?->destination_center_id ?: $delivery?->center_id),
            'seller_center_id' => $defaults['seller_center_id']??($delivery?->origin_center_id ?: $delivery?->center_id),
            ...($direct?['workflow_policy'=>'direct','funding_preference'=>'deposit']:[]),
            ...(Schema::hasColumn('return_settlements','policy_version')?['policy_version'=>2]:[]),
        ]);
        \App\Models\ReturnWorkflow::firstOrCreate(['return_request_id' => $case->id], [
            'center_id' => $settlement->collection_center_id, 'stage' => 'awaiting_collection',
        ]);
        return $settlement;
    }

    public static function canView($actor, ReturnRequest $case): bool
    {
        if ($actor instanceof HubTransportDriver) {
            return $actor->status !== 'inactive' && ((int)$case->workflow?->driver_id===(int)$actor->id || $case->settlement?->allocations()->where('driver_id', $actor->id)->exists());
        }
        if (! $actor instanceof User || ! $actor->isActive()) return false;
        if ($actor->isAdmin() || in_array((int) $actor->id, [(int) $case->seller_id, (int) $case->buyer_id], true)) return true;
        if ($actor->isLogisticsAdmin()) return true;
        $flow = $case->workflow;
        $agreement = $case->settlement;
        if ($actor->rider && $agreement?->allocations()->where('recipient_user_id', $actor->id)->exists()) return true;
        return $actor->isLogisticsStaff() && collect([$flow?->center_id, $flow?->destination_center_id, $agreement?->collection_center_id, $agreement?->seller_center_id])
            ->filter()->contains(fn ($id) => LogisticsAccess::canAccessCenter($actor, (int) $id));
    }

    public static function change(ReturnRequest $record, User $actor, string $action, array $data): void
    {
        abort_unless($actor->isActive(), 403);
        DB::transaction(function () use ($record, $actor, $action, $data) {
            $order = Order::lockForUpdate()->findOrFail($record->order_id);
            $case = ReturnRequest::lockForUpdate()->findOrFail($record->id);
            $agreement = self::ensure($case);
            self::require($agreement !== null && (! $agreement->closed_at || $action === 'close_unused'), 'This case is closed or uses the earlier payment-adjustment process.');
            $agreement = ReturnSettlement::lockForUpdate()->findOrFail($agreement->id);
            $seller = (int) $case->seller_id === (int) $actor->id;
            $buyer = (int) $case->buyer_id === (int) $actor->id;
            $admin = $actor->isAdmin();
            abort_unless(self::canView($actor, $case), 403);
            if (! in_array($action, ['destination', 'dispute'], true)) {
                self::require((int) ($data['version'] ?? 0) === (int) $agreement->version, 'This agreement changed. Refresh and review the current version before continuing.');
            }
            if ($action === 'propose') {
                abort_unless($seller || ($admin && !$agreement->directAgreement()), 403);
                self::require(! $agreement->funded_at && !$agreement->collection_authorized_at && ! $agreement->payments()->whereIn('status', ['submitted', 'verified'])->exists(), 'A submitted or funded payment must be resolved before changing the agreement.');
                self::require(! $case->workflow || in_array($case->workflow->stage, ['awaiting_collection', 'refund_pending'], true), 'The parcel is already moving. Escalate changes to admin.');
                $resolution = $data['resolution'];
                $fault = $data['fault'];
                $buyerDirect=$agreement->directAgreement() && (int)$agreement->policy_version>=2 && $fault==='buyer' && $resolution==='return_refund';
                if((int)$agreement->policy_version>=2 && $agreement->directAgreement())$agreement->workflow_policy=$buyerDirect?'buyer_fault_direct':'direct';
                $payer = $buyerDirect ? 'seller' : ($agreement->directAgreement() ? ($fault === 'buyer' ? 'buyer' : 'seller') : ($data['payer']??'seller'));
                $preference = $agreement->directAgreement() ? 'deposit' : ($data['funding_preference'] ?? $data['funding_method'] ?? 'auto');
                self::require(in_array($resolution, ['return_refund', 'refund_only', 'partial_refund', 'exchange'], true), 'Choose a supported resolution.');
                self::require(in_array($fault, ['seller', 'buyer', 'logistics'], true), 'Identify the proposed responsibility.');
                self::require(!$agreement->directAgreement() || in_array($fault,['seller','buyer'],true),'Agree whether the buyer or seller is responsible. Report courier damage separately.');
                self::require(in_array($payer, ['seller', 'buyer', 'platform'], true), 'Choose who funds the return.');
                self::require($payer !== 'buyer' || $fault === 'buyer', 'A buyer cannot be charged for a seller or logistics fault.');
                self::require($payer !== 'platform' || $admin, 'Only admin can authorize a platform-funded return.');
                self::require(in_array($preference, ['auto', 'deposit', 'deduct_refund'], true), 'Choose a supported funding preference.');
                self::require($preference !== 'deduct_refund' || $payer === 'buyer', 'Only agreed buyer-funded fees can be deducted from the buyer refund.');
                $merch = $resolution === 'exchange' ? 0 : self::cents($data['merchandise_amount']);
                self::require($merch >= 0 && $merch <= $agreement->maximum_refund_cents, 'The item refund exceeds the amount paid for this quantity.');
                self::require($resolution === 'partial_refund' || $resolution === 'exchange' || $merch === (int) $agreement->maximum_refund_cents, 'Use Partial refund to propose less than the eligible item value.');
                $shipping = self::cents($data['shipping_amount'] ?? 0);
                $shippingUsed = (int) ReturnSettlement::where('order_id', $order->id)->whereKeyNot($agreement->id)->whereNotNull('buyer_accepted_at')->sum('shipping_cents');
                $shippingCap = self::cents(max(0, (float) $order->shipping_fee - $order->shippingWaivedForSeller((int) $case->seller_id)));
                self::require($shipping >= 0 && $shipping + $shippingUsed <= $shippingCap && ($shipping === 0 || ($fault !== 'buyer' && $resolution !== 'exchange')), 'Original shipping reimbursement exceeds the available paid shipping or is not applicable to this resolution.');
                $compensation = self::cents($data['compensation_amount'] ?? 0);
                self::require(!$agreement->directAgreement() || $compensation===0,'The simplified return charges only the configured transport fees; seller compensation is not an extra charge.');
                self::require($compensation >= 0, 'Compensation cannot be negative.');
                self::require($compensation === 0 || ($fault === 'buyer' && $payer === 'buyer' && $resolution === 'return_refund' && $compensation <= $merch), 'Seller compensation is only an agreed, justified buyer-fault physical-return charge.');
                self::require(strlen(trim($data['note'] ?? '')) >= 10, 'Explain the responsibility and proposed amounts (at least 10 characters).');
                $replacement = null;
                if ($resolution === 'exchange') {
                    $product = \App\Models\Product::whereKey($case->product_id)->where('seller_id', $case->seller_id)->first();
                    self::require($product !== null && $product->status === 'active', 'The original product must still be active for an exchange.');
                    $variantId = $data['replacement_variant_id'] ?? null;
                    if ($product->variants()->exists()) {
                        $replacement = $product->variants()->whereKey($variantId ?? 0)->where('status', 'active')->first();
                        self::require($replacement !== null && $replacement->stock >= $case->quantity, 'Choose the agreed replacement variation with sufficient stock.');
                    }
                    self::require($product->stock >= $case->quantity && strlen(trim($data['replacement_description'] ?? '')) >= 5, 'Describe the expected replacement and check its stock.');
                }
                self::releaseUnfundedReservations($order, $agreement);
                $agreement->allocations()->delete();
                if(Schema::hasColumn('return_settlements','cost_snapshot'))$agreement->fill(['cost_snapshot'=>null,'deduction_note'=>null]);
                $agreement->fill([
                    'fault' => $fault, 'resolution' => $resolution, 'payer' => $payer,
                    'funding_preference' => $preference,
                    'funding_method' => $payer === 'buyer' && $preference === 'deduct_refund' ? 'deduct_refund' : 'deposit',
                    'merchandise_cents' => $merch, 'shipping_cents' => $shipping, 'compensation_cents' => $compensation,
                    'net_refund_cents' => $merch + $shipping, 'transport_cents' => 0,
                    'reason_note' => $data['note'], 'status' => 'proposed', 'version' => $agreement->version + 1,
                    'buyer_accepted_at' => null, 'seller_accepted_at' => null, 'admin_decided_at' => null,
                    'quoted_at' => in_array($resolution, ['refund_only', 'partial_refund']) ? now() : null,
                    'decision_note' => null, 'dispute_note' => null, 'response_due_at' => now()->addHours(48),
                    'replacement_product_id' => $resolution === 'exchange' ? $case->product_id : null,
                    'replacement_variant_id' => $replacement?->id,
                    'replacement_description' => $resolution === 'exchange' ? $data['replacement_description'] : null,
                ]);
                if($agreement->directAgreement()){
                    ReturnFeeService::apply($case,$agreement,$data);
                    if($buyerDirect)BuyerFaultReturnService::applyDeductions($case,$agreement);
                    $agreement->seller_accepted_at=now();
                }
            } elseif ($action === 'quote') {
                self::quote($case, $agreement, $actor, $data);
            } elseif ($action === 'accept') {
                abort_unless($seller || $buyer, 403);
                self::require($agreement->quoted_at !== null && $agreement->status !== 'draft' && ! $agreement->dispute_note, 'Wait for a complete quote or resolution of the dispute.');
                self::require(!$agreement->directAgreement() || $agreement->status==='proposed','The seller must send a revised proposal before it can be accepted.');
                self::require(! $agreement->funded_at && !$agreement->collection_authorized_at, 'The activated agreement is already locked.');
                if($agreement->buyerFaultDirect()){
                    $snapshot=BuyerFaultReturnService::originalCosts($case,$agreement);
                    // MySQL JSON normalizes object-key order; compare values,
                    // not insertion order, when checking the saved quote.
                    self::require($snapshot==$agreement->cost_snapshot,'Original costs changed because another return was accepted. Ask the seller for a refreshed proposal.');
                }
                $otherShipping = (int) ReturnSettlement::where('order_id', $order->id)->whereKeyNot($agreement->id)
                    ->where('status', '!=', 'cancelled')->whereNotNull('buyer_accepted_at')->sum('shipping_cents');
                $shippingLimit = self::cents(max(0, (float) $order->shipping_fee - $order->shippingWaivedForSeller((int) $case->seller_id)));
                self::require($agreement->shipping_cents + $otherShipping <= $shippingLimit, 'Another accepted return already reserves this shipping reimbursement.');
                $agreement->{$buyer ? 'buyer_accepted_at' : 'seller_accepted_at'} = now();
            } elseif ($action === 'decide') {
                abort_unless($admin, 403);
                self::require(!$agreement->directAgreement(),'This proposal is agreed by buyer and seller; no admin responsibility approval is required.');
                self::require($agreement->quoted_at !== null && strlen(trim($data['note'] ?? '')) >= 10, 'Review the complete quote and record the decision reason.');
                $agreement->admin_decided_at = now();
                $agreement->decision_note = $data['note'];
                $agreement->dispute_note = null;
                if ($agreement->status === 'disputed') $agreement->status = $agreement->funded_at ? 'funded' : 'proposed';
                // Admin can uphold a seller-fault remedy after review, without
                // allowing the seller to indefinitely veto the buyer's remedy.
                if ($agreement->fault === 'seller') $agreement->seller_accepted_at ??= now();
            } elseif ($action==='resolve_issue') {
                $lastIssue=collect($agreement->events??[])->where('action','dispute')->last();
                abort_unless($agreement->buyerFaultDirect() ? (($buyer||$seller) && (int)($lastIssue['actor_id']??0)===(int)$actor->id) : $admin,403);
                self::require($agreement->directAgreement() && $agreement->dispute_note && strlen(trim($data['note']??''))>=10,
                    'Explain the payment or custody issue resolution without changing the agreed terms.');
                $agreement->dispute_note=null;
                $agreement->status=$agreement->collection_authorized_at?'collection_ready':($agreement->funded_at?'funded':'proposed');
            } elseif (in_array($action,['comment','decline'],true)) {
                abort_unless($buyer || $seller,403);
                self::require($agreement->directAgreement() && !$agreement->funded_at && !$agreement->collection_authorized_at
                    && !$agreement->payments()->whereIn('status',['submitted','verified'])->exists(),'Paid or submitted terms are locked. Request help before changing them.');
                self::require(strlen(trim($data['note']??''))>=10,'Explain your suggestion or reason for declining (at least 10 characters).');
                $agreement->buyer_accepted_at=null;
                $agreement->status=$action==='decline'?'declined':'revision_requested';
                $agreement->response_due_at=now()->addHours(48);
            } elseif ($action === 'dispute') {
                abort_unless($buyer || $seller || ($admin && !$agreement->buyerFaultDirect()), 403);
                self::require(strlen(trim($data['note'] ?? '')) >= 10, 'Explain the dispute or missing refund payment.');
                $agreement->dispute_note = $data['note'];
                $agreement->admin_decided_at = null;
                $agreement->status = 'disputed';
                $agreement->response_due_at = now()->addHours(48);
            } elseif ($action === 'destination') {
                abort_unless($buyer, 403);
                self::require(! $case->workflow?->refund_sent_at, 'The refund was already sent.');
                self::require($agreement->buyerFaultDirect() || ! $agreement->funded_at || ! $agreement->destination_verified_at, 'The funded refund account is locked. Ask admin to request an account correction before changing it.');
                $agreement->refund_destination = [
                    'provider' => $data['provider'], 'holder' => $data['holder'], 'account' => $data['account'],
                ];
                $agreement->destination_verified_at = now();
            } elseif ($action === 'unlock_destination') {
                abort_unless($admin && !$agreement->buyerFaultDirect(), 403);
                self::require(! $case->workflow?->refund_sent_at && strlen(trim($data['note'] ?? '')) >= 10, 'An account correction needs an explanation before the refund is sent.');
                $agreement->destination_verified_at = null;
                $agreement->admin_decided_at = null;
                $agreement->dispute_note = 'Refund account correction requested: '.$data['note'];
                $agreement->status = 'disputed';
            } elseif ($action === 'reserve') {
                abort_unless($admin, 403);
                self::require($agreement->agreed() && $agreement->funding_method === 'deduct_refund' && $agreement->payer === 'buyer', 'Only an agreed buyer-funded deduction can be reserved.');
                ReturnFundingService::reserve($order, $case, $agreement);
                self::require((int) $agreement->reserved_cents === $agreement->budgetCents(), 'Reconcile the original payment and recover the refund funding shortfall before reserving transport money.');
            } elseif ($action === 'schedule') {
                abort_unless($seller || ($admin && !$agreement->buyerFaultDirect()), 403);
                self::assertFunded($case);
                self::require($agreement->physicalReturn() && $case->workflow?->stage === 'awaiting_collection', 'Only an uncollected physical return can be scheduled.');
                $agreement->collection_at = $data['collection_at'];
                $agreement->status = 'collection_scheduled';
            } elseif ($action === 'ready') {
                abort_unless($admin, 403);
                self::require(!$agreement->buyerFaultDirect(),'Buyer and seller authorize this return; admin handles only delivery escrow payouts.');
                self::require(! $agreement->funded_at, 'This agreement was already funded and activated.');
                $committedQty = (int) ReturnRequest::where('order_item_id', $case->order_item_id)->whereKeyNot($case->id)
                    ->whereIn('status', ['approved', 'item_received', 'refunded'])->sum('quantity');
                self::require($case->item && $case->item->seller_id == $case->seller_id
                    && $committedQty + $case->quantity <= $case->item->quantity, 'Another return already commits this purchased quantity. Resolve duplicate cases before funding is unlocked.');
                self::require($agreement->agreed(), 'Buyer and seller must agree to the complete proposal.');
                ReturnFundingService::reserve($order, $case, $agreement);
                self::require($agreement->net_refund_cents == 0 || $agreement->destination_verified_at !== null, 'The buyer must verify the refund receiving account before funding is unlocked.');
                self::require(self::budgetFunded($agreement), 'Return transport and compensation are not fully funded.');
                $originalFunding = ReturnWorkflowService::fundingSummary($order, $agreement->net_refund_cents / 100, $agreement->id);
                self::require($originalFunding['funded'] > 0 && $originalFunding['shortfall'] <= 0, 'Reconcile the original collected payment and secure the buyer refund before unlocking this resolution.');
                if ($agreement->resolution === 'exchange' && ! $agreement->replacement_stock_reserved_at) {
                    self::require(\App\Support\Stock::deduct((int) $agreement->replacement_product_id, $agreement->replacement_variant_id, (int) $case->quantity), 'Replacement stock is no longer available. Resolve the stock shortage before collecting the buyer item.');
                    $agreement->replacement_stock_reserved_at = now();
                }
                $agreement->funded_at ??= now();
                $agreement->refund_reserved_cents = $agreement->net_refund_cents;
                $agreement->status = 'funded';
                self::activate($case, $agreement);
            } elseif ($action === 'replacement_dispatch') {
                abort_unless($actor->isLogisticsStaff() && LogisticsAccess::isCenterStaff($actor, (int) $agreement->seller_center_id), 403);
                self::require($agreement->resolution === 'exchange' && $case->status === 'item_received' && ! $agreement->replacement_reference, 'Receive and inspect the returned item before dispatching a replacement.');
                self::require(! empty($data['reference']) && ! empty($data['proof_path']), 'Record the replacement tracking reference and dispatch photo.');
                self::require(! ReturnSettlement::where('replacement_reference', $data['reference'])->whereKeyNot($agreement->id)->exists(), 'This replacement tracking reference belongs to another case.');
                $allocation = $agreement->allocations()->where('role', 'replacement_delivery_rider')->first();
                self::require($allocation !== null, 'The agreed quote must include the replacement delivery leg.');
                self::bindRider($allocation, (int) ($data['rider_id'] ?? 0), (int) $agreement->seller_center_id);
                $agreement->replacement_reference = $data['reference'];
                $agreement->replacement_proof_path = $data['proof_path'];
                $agreement->status = 'replacement_dispatched';
            } elseif ($action === 'replacement_received') {
                abort_unless($buyer, 403);
                self::require($agreement->resolution === 'exchange' && $agreement->replacement_reference && ! $agreement->dispute_note, 'A replacement must be dispatched before receipt is confirmed.');
                self::makeEligible($agreement, 'replacement_delivery_rider');
                $case->update(['status' => 'refunded', 'resolved_at' => now()]);
                $case->workflow()->update(['stage' => 'exchange_completed']);
                $agreement->status = 'exchange_completed';
                $agreement->closed_at = now();
                self::makeEligible($agreement, 'admin_service', $actor->id, true);
            } elseif ($action === 'complete_no_cash') {
                abort_unless($admin, 403);
                self::assertFunded($case);
                self::require($agreement->net_refund_cents == 0 && $agreement->resolution !== 'exchange'
                    && ($case->status === 'item_received' || (! $agreement->physicalReturn() && $case->status === 'approved')), 'Only a completed zero-cash resolution can close without a payment transfer.');
                $case->update(['status' => 'refunded', 'resolved_at' => now()]);
                $case->workflow()->update(['stage' => 'completed_no_refund']);
                $agreement->status = 'completed_no_cash';
                $agreement->closed_at = now();
                self::makeEligible($agreement, 'admin_service');
            } elseif ($action === 'close_unused') {
                abort_unless($admin, 403);
                self::require(($agreement->funded_at || $agreement->reserved_cents > 0 || $agreement->seller_reserved_cents > 0 || $agreement->payments()->where('status', 'verified')->exists()) && ! $agreement->dispute_note, 'Only an undisputed budget with verified funds can be reconciled.');
                $cancel = ! $agreement->closed_at;
                self::require(! $cancel || ((! $case->workflow || $case->workflow->stage === 'awaiting_collection') && ! $case->workflow?->refund_sent_at), 'An in-transit return cannot be cancelled through budget reconciliation.');
                if ($cancel) {
                    self::require(strlen(trim($data['note'] ?? '')) >= 10, 'Explain why the funded return is cancelled.');
                    $agreement->allocations()->where('status', 'reserved')->update(['status' => 'voided', 'note' => $data['note']]);
                    $case->update(['status' => 'rejected', 'decision_note' => $data['note'], 'resolved_at' => now()]);
                    $case->workflow()->update(['stage' => 'cancelled']);
                    $agreement->closed_at = now();
                    $agreement->status = 'cancelled';
                    if ($agreement->replacement_stock_reserved_at && ! $agreement->replacement_stock_restored_at) {
                        $stockItem = new \App\Models\OrderItem(['product_id' => $agreement->replacement_product_id, 'product_variant_id' => $agreement->replacement_variant_id, 'quantity' => $case->quantity]);
                        \App\Support\Stock::restore($stockItem, (int) $case->quantity);
                        $agreement->replacement_stock_restored_at = now();
                    }
                    // A cancelled deduction is released back to the original
                    // escrow, not paid to a buyer who kept the purchased item.
                    if ($agreement->funding_method === 'deduct_refund') $agreement->reserved_cents = 0;
                    $agreement->seller_reserved_cents = 0;
                    $agreement->refund_reserved_cents = 0;
                }
                self::require(strlen(trim($data['note'] ?? '')) >= 10, 'Explain the unused-budget reconciliation.');
                if (! $cancel) $agreement->allocations()->where('status', 'reserved')->update(['status' => 'voided', 'note' => $data['note']]);
                $agreement->save();
                $used = (int) $agreement->allocations()->whereNotIn('status', ['voided'])->sum('amount_cents');
                $unused = max(0, self::poolCents($agreement) - $used);
                // Release unused original seller funds to their original
                // allocation. Only unused external deposits need repayment.
                $release = min($unused, (int) $agreement->seller_reserved_cents);
                if ($release > 0) {
                    $agreement->seller_reserved_cents -= $release;
                    $unused -= $release;
                    $agreement->save();
                }
                EscrowEarnings::syncRemittancesForOrder($order);
                if ($unused > 0) {
                    $owner = $agreement->payer === 'buyer' ? $case->buyer_id : ($agreement->payer === 'seller' ? $case->seller_id : $actor->id);
                    ReturnAllocation::firstOrCreate(['return_settlement_id' => $agreement->id, 'role' => 'unused_budget_refund'], [
                        'amount_cents' => $unused, 'recipient_user_id' => $owner, 'status' => 'eligible', 'eligible_at' => now(),
                        'note' => 'Unused return funding; pay back to the funding party, not the seller by default.',
                    ]);
                }
                $pendingSale = \App\Models\EscrowRemittance::where('order_id', $order->id)
                    ->whereIn('recipient_role', ['seller', 'admin'])->whereIn('status', ['held_in_escrow', 'pending_remittance'])->sum('amount');
                $available = max(0, self::cents(ReturnWorkflowService::fundingSummary($order, 0)['available']) - self::cents($pendingSale));
                foreach ($agreement->payments()->where('purpose', 'refund_recovery')->where('status', 'verified')->get() as $payment) {
                    $committed = (int) ReturnAllocation::where('fund_payment_id', $payment->id)->where('status', '!=', 'voided')->sum('amount_cents');
                    $amount = min($available, max(0, (int) $payment->amount_cents - $committed));
                    if ($amount <= 0) continue;
                    $agreement->allocations()->firstOrCreate(['role' => 'unused_recovery_'.$payment->id], [
                        'fund_payment_id' => $payment->id, 'amount_cents' => $amount, 'recipient_user_id' => $payment->payer_user_id,
                        'status' => 'eligible', 'eligible_at' => now(), 'note' => 'Unused verified refund recovery returned to its actual funding party.',
                    ]);
                    $available -= $amount;
                }
            } else {
                self::require(false, 'Unsupported return settlement action.');
            }
            self::event($agreement, $actor->id, $action, $data['note'] ?? null);
            $agreement->save();
            if (in_array($action, ['accept', 'decide', 'destination'], true)) ReturnFundingService::reserve($order, $case, $agreement);
            if($agreement->directAgreement() && in_array($action,['accept','destination','resolve_issue'],true))self::tryActivateDirect($order,$case,$agreement);
        }, 3);
    }

    private static function quote(ReturnRequest $case, ReturnSettlement $s, User $actor, array $data): void
    {
        self::require(!$s->directAgreement(),'Return fees are calculated automatically from Commission settings; they cannot be entered manually.');
        $admin = $actor->isAdmin();
        abort_unless($admin || $actor->isLogisticsStaff(), 403);
        self::require($s->status !== 'draft' && ! $s->funded_at && ! $s->payments()->whereIn('status', ['submitted', 'verified'])->exists(), 'Propose the resolution before quoting. A submitted or funded quote cannot be changed.');
        self::require($s->physicalReturn(), 'Refund-only and partial-refund cases have no return transport quote.');
        $from = LogisticsCenter::findOrFail($data['collection_center_id']);
        $to = LogisticsCenter::findOrFail($data['seller_center_id']);
        self::require($from->is_active && $to->is_active && (int) $from->logistics_company_id === (int) $to->logistics_company_id, 'Choose active return hubs in the same logistics company.');
        abort_unless($admin || LogisticsAccess::isCenterStaff($actor, $from->id), 403);
        $lines = collect($data['allocations'] ?? [])->filter(fn ($line) => self::cents($line['amount'] ?? 0) > 0);
        self::require($lines->isNotEmpty() && $lines->pluck('role')->unique()->count() === $lines->count(), 'Quote each actual service once.');
        self::require(! $lines->contains(fn ($line) => ! in_array($line['role'], ReturnAllocation::ROLES, true)), 'Unsupported return service.');
        self::require($from->id !== $to->id || ! $lines->contains(fn ($line) => in_array($line['role'], ['inter_hub_driver', 'seller_hub'])), 'A same-hub return cannot charge inter-hub transport or a second handling hub.');
        self::require($s->resolution === 'exchange' || ! $lines->contains('role', 'replacement_delivery_rider'), 'Replacement delivery is only applicable to an exchange.');
        self::require($s->resolution !== 'exchange' || $lines->contains('role', 'replacement_delivery_rider'), 'Include the replacement delivery fee for an exchange.');
        self::releaseUnfundedReservations($case->order, $s);
        $s->allocations()->delete();
        $total = 0;
        foreach ($lines as $line) {
            $amount = self::cents($line['amount']);
            self::require($amount <= 100000000, 'A service fee is too large.');
            $total += $amount;
            $center = in_array($line['role'], ['collection_rider', 'collection_hub', 'inter_hub_driver']) ? $from->id : $to->id;
            $s->allocations()->create(['role' => $line['role'], 'amount_cents' => $amount, 'center_id' => $center]);
        }
        $budget = $total + $s->compensation_cents;
        $s->transport_cents = $total;
        $s->funding_method = ReturnFundingService::chooseMethod($case->order, $s);
        $net = $s->merchandise_cents + $s->shipping_cents - ($s->funding_method === 'deduct_refund' ? $budget : 0);
        self::require($net > 0 || ($s->resolution === 'exchange' && $s->funding_method === 'deposit'), 'Fees consume the entire refund. Use a separately prepaid return instead.');
        $s->fill(['collection_center_id' => $from->id, 'seller_center_id' => $to->id, 'transport_cents' => $total,
            'net_refund_cents' => $net, 'quoted_at' => now(), 'version' => $s->version + 1,
            'buyer_accepted_at' => null, 'seller_accepted_at' => null, 'admin_decided_at' => null,
            'status' => 'proposed', 'response_due_at' => now()->addHours(48), 'dispute_note' => null]);
        if ($s->compensation_cents > 0) $s->allocations()->create(['role' => 'seller_compensation', 'amount_cents' => $s->compensation_cents, 'recipient_user_id' => $case->seller_id]);
    }

    private static function activate(ReturnRequest $case, ReturnSettlement $s): void
    {
        $flow = ReturnWorkflowService::start($case);
        $case->update(['status' => 'approved', 'refund_amount' => $s->net_refund_cents / 100]);
        if (! $s->physicalReturn()) {
            $flow->update(['stage' => 'refund_pending']);
        } elseif ($flow->stage === 'awaiting_collection') {
            $flow->update(['center_id' => $s->collection_center_id]);
        }
    }

    /** Called under the order lock; mutual agreement never substitutes for verified cash. */
    public static function tryActivateDirect(Order $order,ReturnRequest $case,ReturnSettlement $agreement): void
    {
        if($agreement->buyerFaultDirect()){
            if(self::budgetFunded($agreement) && !$agreement->funded_at){
                $agreement->funded_at=now();$agreement->save();
            }
            BuyerFaultReturnService::activateIfReady($order,$case,$agreement);
            return;
        }
        if(!$agreement->directAgreement() || !$agreement->agreed() || $agreement->funded_at || $agreement->closed_at
            || !self::budgetFunded($agreement) || ($agreement->net_refund_cents>0 && !$agreement->destination_verified_at))return;
        $funding=ReturnWorkflowService::fundingSummary($order,$agreement->net_refund_cents/100,$agreement->id);
        if($funding['funded']<=0 || $funding['shortfall']>0)return;
        $committed=(int)ReturnRequest::where('order_item_id',$case->order_item_id)->whereKeyNot($case->id)
            ->whereIn('status',['approved','item_received','refunded'])->sum('quantity');
        self::require($case->item && $case->item->seller_id==$case->seller_id && $committed+$case->quantity<=$case->item->quantity,
            'Another return already commits this purchased quantity.');
        if($agreement->resolution==='exchange' && !$agreement->replacement_stock_reserved_at){
            self::require(\App\Support\Stock::deduct((int)$agreement->replacement_product_id,$agreement->replacement_variant_id,(int)$case->quantity),
                'Replacement stock is unavailable; resolve this before collecting the item.');
            $agreement->replacement_stock_reserved_at=now();
        }
        $agreement->fill(['funded_at'=>now(),'refund_reserved_cents'=>$agreement->net_refund_cents,'status'=>'funded'])->save();
        self::activate($case,$agreement);
    }

    public static function poolCents(ReturnSettlement $s): int
    {
        return (int) $s->reserved_cents + (int) $s->seller_reserved_cents
            + (int) $s->payments()->where('purpose', 'return_budget')->where('status', 'verified')->sum('amount_cents');
    }

    private static function releaseUnfundedReservations(Order $order, ReturnSettlement $agreement): void
    {
        $sellerHeld = (int) $agreement->seller_reserved_cents;
        $agreement->fill(['reserved_cents'=>0, 'seller_reserved_cents'=>0, 'refund_reserved_cents'=>0])->save();
        if ($sellerHeld > 0) EscrowEarnings::syncRemittancesForOrder($order);
    }

    public static function budgetFunded(ReturnSettlement $s): bool { return self::poolCents($s) >= $s->budgetCents(); }

    public static function assertFunded(ReturnRequest $case): void
    {
        $s = self::ensure($case);
        if (! $s) return;
        self::require($s->buyerFaultDirect() ? $s->collectionReady() : ($s->funded_at !== null && $s->agreed() && self::budgetFunded($s)), 'Complete the agreement, refund account and required return-delivery deposit first.');
    }

    public static function beforeCustody(ReturnRequest $case, string $action, array $data): void
    {
        $s = self::ensure($case);
        if (! $s) return;
        self::assertFunded($case);
        self::require($s->physicalReturn(), 'Physical custody is waived for this refund-only resolution.');
        if (in_array($action, ['assign', 'pickup'], true)) self::require($s->collection_at !== null, 'The seller must schedule collection first.');
        if (in_array($action, ['pickup', 'deliver_seller'], true)) self::require(! empty($data['proof_path']) && \Illuminate\Support\Facades\Storage::disk('private')->exists($data['proof_path']), 'Attach the actual parcel handover photo.');
        if ($action === 'transfer') self::require(ReturnRouting::operational(LogisticsCenter::find((int)($data['destination_center_id']??0))), 'Choose an active return destination hub.');
        if ($action === 'dispatch_seller') self::require((int) $case->workflow->center_id === (int) $s->seller_center_id, 'The parcel must reach the agreed seller-side hub.');
    }

    public static function bindRider(ReturnAllocation $line, int $riderId, int $center): void
    {
        $rider = self::riderForAssignment($riderId,$center);
        $line->update(['rider_id' => $rider->id, 'recipient_user_id' => $rider->user_id]);
    }

    /** Recheck availability under the assignment transaction, not just in the dropdown. */
    public static function riderForAssignment(int $riderId,int $center): Rider
    {
        $rider=Rider::lockForUpdate()->find($riderId);
        self::require($rider && $rider->center_id==$center && $rider->approved_at && $rider->is_verified
            && $rider->status!=='inactive' && $rider->user?->isActive(),'Choose an approved active rider from the service hub.');
        self::require((bool)$rider->is_online,'This rider is offline. Ask the rider to go online before assigning return or replacement work.');
        return $rider;
    }

    public static function onlineRiderOptions(int $center): \Illuminate\Support\Collection
    {
        return Rider::with('user')->where('center_id',$center)->whereNotNull('approved_at')->where('is_verified',true)
            ->where('status','!=','inactive')->where('is_online',true)->orderBy('name')->get()
            ->filter(fn($rider)=>$rider->user?->isActive());
    }

    public static function custody(ReturnRequest $case, string $action, array $data, $actor): void
    {
        $s = self::ensure($case);
        if (! $s) return;
        $lines = $s->allocations();
        if ($action === 'assign' && ($line = $lines->where('role', 'collection_rider')->first())) self::bindRider($line, (int) $data['rider_id'], (int) $s->collection_center_id);
        if ($action === 'intake') {
            self::makeEligible($s, 'collection_rider');
            self::makeEligible($s, 'collection_hub');
        }
        if ($action === 'transfer') {
            $line=$s->allocations()->where('role', 'inter_hub_driver')->first();
            $driver = HubTransportDriver::lockForUpdate()->find($data['driver_id'] ?? 0);
            self::require((bool)$driver,'Choose an online inter-hub driver for this return transfer.');
            $company = LogisticsCenter::findOrFail($s->collection_center_id)->logistics_company_id;
            self::require($driver->status !== 'inactive' && $driver->logistics_company_id == $company && $driver->company?->status === 'active'
                && (!$driver->user || $driver->user->isActive()), 'Choose an active inter-hub driver from the return company.');
            self::require((bool)$driver->is_online,'This inter-hub driver is offline. Ask the driver to go online before assigning the return transfer.');
            if($line)$line->update(['driver_id' => $driver->id]);
        }
        if ($action === 'receive_transfer') {
            self::makeEligible($s, 'inter_hub_driver');
            self::makeEligible($s, 'seller_hub');
        }
        if ($action === 'dispatch_seller' && ($line = $s->allocations()->where('role', 'seller_delivery_rider')->first())) self::bindRider($line, (int) ($data['rider_id'] ?? 0), (int) $s->seller_center_id);
        if ($action === 'receive') {
            self::makeEligible($s, 'seller_delivery_rider');
            self::makeEligible($s, 'seller_compensation');
        }
        if ($action === 'confirm_refund') {
            $s->update(['status' => $s->physicalReturn() ? 'completed' : 'refunded_return_waived', 'closed_at' => now()]);
            self::makeEligible($s, 'admin_service', 0, true);
        }
    }

    private static function makeEligible(ReturnSettlement $s, string $role, int $userId = 0, bool $admin = false): void
    {
        $line = $s->allocations()->where('role', $role)->first();
        if (! $line || $line->status !== 'reserved') return;
        $line->update(['status' => 'eligible', 'eligible_at' => now()]);
    }

    private static function event(ReturnSettlement $s, int $actor, string $action, ?string $note): void
    {
        $events = $s->events ?? [];
        $case=$s->returnRequest;
        $label=$actor===(int)$case->buyer_id?'Buyer':($actor===(int)$case->seller_id?'Seller':'Payment staff');
        $events[] = ['action' => $action, 'actor_id' => $actor, 'actor_label'=>$label, 'version' => $s->version, 'at' => now()->toIso8601String(), 'note' => $note];
        $s->events = $events;
    }

    public static function submitPayment(ReturnRequest $record, User $actor, array $data): ReturnFundPayment
    {
        abort_unless($actor->isActive(), 403);
        return DB::transaction(function () use ($record, $actor, $data) {
            $order = Order::lockForUpdate()->findOrFail($record->order_id);
            $case = ReturnRequest::lockForUpdate()->findOrFail($record->id);
            $s = ReturnSettlement::lockForUpdate()->where('return_request_id', $case->id)->firstOrFail();
            self::require($s->agreed() && ! $s->closed_at && ! $s->funded_at, 'Agree to the current quote before funding; this budget may already be locked.');
            self::require(Schema::hasTable('gcash_escrow_settings') && DB::table('gcash_escrow_settings')->exists(), 'Admin must configure the platform escrow receiving account before deposits are submitted.');
            self::require((int) $data['version'] === (int) $s->version, 'The quote changed. Refresh first.');
            $purpose = $data['purpose'];
            self::require(!$s->buyerFaultDirect() || $purpose==='return_budget','The seller refunds the buyer directly. Only the new return-delivery fee belongs in escrow.');
            self::require(in_array($purpose, ['return_budget', 'refund_recovery'], true), 'Unknown funding purpose.');
            if ($purpose === 'return_budget') {
                $owner = $s->payer === 'buyer' ? $case->buyer_id : ($s->payer === 'seller' ? $case->seller_id : null);
                abort_unless($owner ? (int) $owner === (int) $actor->id : $actor->isAdmin(), 403);
                self::require($s->funding_method === 'deposit', 'This quote uses a refund deduction, not another transport payment.');
                // Original funds may have been reconciled since acceptance.
                // Use them first, before requiring an external shortfall.
                ReturnFundingService::reserve($order, $case, $s);
                $required = max(0, $s->budgetCents() - self::poolCents($s));
            } else {
                abort_unless((int) $actor->id === (int) $case->seller_id || $actor->isAdmin(), 403);
                $prospective = $s->funding_method === 'deduct_refund' && ! $s->reserved_cents ? $s->budgetCents() : 0;
                $originalFunding = ReturnWorkflowService::fundingSummary($order, ($s->net_refund_cents + $prospective) / 100, $s->id);
                self::require($originalFunding['funded'] > 0, 'Reconcile original collected COD or the full GCash escrow deposit before recovering payout shortfalls.');
                $required = self::cents($originalFunding['shortfall']);
            }
            self::require(! $s->payments()->where('purpose', $purpose)->where('status', 'submitted')->exists(), 'A payment for this purpose is already awaiting review.');
            $amount = self::cents($data['amount']);
            self::require($amount > 0 && $amount === $required, 'Pay exactly the current outstanding amount: PHP '.number_format($required / 100, 2).'.');
            self::require(! empty($data['proof_path']) && \Illuminate\Support\Facades\Storage::disk('private')->exists($data['proof_path']), 'Upload the actual transfer receipt.');
            $reference = self::reference($data['reference']);
            $correction = ReturnFundPayment::where('reference', $reference)->first();
            self::require((! $correction || ($correction->status === 'rejected' && $correction->return_settlement_id == $s->id
                    && $correction->payer_user_id == $actor->id && $correction->purpose === $purpose))
                && ! \App\Models\GcashEscrowDeposit::whereRaw('UPPER(REPLACE(reference, \' \', \'\')) = ?', [$reference])->exists()
                && ! \App\Models\ReturnWorkflow::whereRaw('UPPER(REPLACE(recovery_reference, \' \', \'\')) = ?', [$reference])->exists(), 'This incoming transfer reference was already recorded.');
            $payload = ['payer_user_id' => $actor->id, 'purpose' => $purpose, 'amount_cents' => $amount, 'reference' => $reference,
                'proof_path' => $data['proof_path'], 'status' => 'submitted', 'reviewed_by' => null, 'review_note' => null, 'verified_at' => null,
                'receipt_viewed_by'=>null,'receipt_viewed_at'=>null];
            if ($correction) { $correction->update($payload); $payment = $correction; }
            else $payment = $s->payments()->create($payload);
            self::event($s, $actor->id, 'payment_submitted', $purpose.'; screenshot awaiting actual-bank verification');
            $s->save();
            if($s->buyerFaultDirect())self::tryActivateDirect($order,$case,$s);
            return $payment;
        }, 3);
    }

    public static function viewPaymentReceipt(ReturnFundPayment $record,User $admin): void
    {
        abort_unless($admin->isActive() && $admin->isAdmin(),403);
        self::require(Storage::disk('private')->exists($record->proof_path),'The uploaded receipt is missing. Request a replacement before confirming payment.');
        // Do not acknowledge a replacement receipt if this response contained
        // the previous image, or alter an already completed bank review.
        ReturnFundPayment::whereKey($record->id)->where('status','submitted')->where('proof_path',$record->proof_path)
            ->update(['receipt_viewed_by'=>$admin->id,'receipt_viewed_at'=>now()]);
    }

    public static function reviewPayment(ReturnFundPayment $record, User $admin, bool $approved, string $note): void
    {
        abort_unless($admin->isActive() && $admin->isAdmin(), 403);
        DB::transaction(function () use ($record, $admin, $approved, $note) {
            $orderId = $record->settlement->order_id;
            $order = Order::lockForUpdate()->findOrFail($orderId);
            $s = ReturnSettlement::lockForUpdate()->findOrFail($record->return_settlement_id);
            $p = ReturnFundPayment::lockForUpdate()->findOrFail($record->id);
            self::require($p->status === 'submitted' && strlen(trim($note)) >= 10, 'This payment was already reviewed, or the verification note is missing.');
            if ($approved) {
                self::require((int)$p->receipt_viewed_by===(int)$admin->id && $p->receipt_viewed_at,
                    'Open the uploaded payment receipt first, then check the actual incoming transfer before confirming success.');
                self::require($s->buyerFaultDirect() ? ($s->buyer_accepted_at && $s->seller_accepted_at && $s->status!=='cancelled') : ($s->agreed() && ! $s->closed_at && ! $s->funded_at), 'Resolve the disputed or locked agreement before confirming a deposit.');
                if ($p->purpose === 'return_budget') self::require(self::poolCents($s) + $p->amount_cents <= $s->budgetCents(), 'This payment would overfund the return budget.');
            }
            $p->update(['status' => $approved ? 'verified' : 'rejected', 'reviewed_by' => $admin->id,
                'review_note' => $note, 'verified_at' => $approved ? now() : null]);
            self::event($s, $admin->id, $approved ? 'deposit_verified' : 'deposit_rejected', $note);
            $s->save();
            if ($approved) ReturnFundingService::reserve($order, $s->returnRequest, $s);
            if ($approved && $s->directAgreement())self::tryActivateDirect($order,$s->returnRequest,$s);
        }, 3);
    }

    public static function payout(ReturnAllocation $record, $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($record, $actor, $action, $data) {
            $order = Order::lockForUpdate()->findOrFail($record->settlement->order_id);
            $s = ReturnSettlement::lockForUpdate()->findOrFail($record->return_settlement_id);
            $line = ReturnAllocation::lockForUpdate()->findOrFail($record->id);
            $active = $actor instanceof User ? $actor->isActive() : $actor->status !== 'inactive';
            abort_unless($active, 403);
            if ($action === 'destination') {
                abort_unless($line->canAccept($actor), 403);
                self::require(in_array($line->status, ['reserved', 'eligible'], true), 'Receiving details are locked after transfer.');
                $line->update(['receiving_destination' => ['provider' => $data['provider'], 'holder' => $data['holder'], 'account' => $data['account']]]);
            } elseif (in_array($action,['remit','release'],true)) {
                abort_unless($actor instanceof User && $actor->isAdmin(), 403);
                $orderStyle=$action==='release';
                self::require(!$orderStyle || $line->isDeliveryService(),'Only return-service earnings use this release action.');
                if($orderStyle && in_array($line->status,['remitted','accepted'],true))return;
                self::require(!$s->buyerFaultDirect() || in_array($line->role,[...ReturnAllocation::DELIVERY_ROLES,'unused_budget_refund'],true),'Only completed return-delivery services can be paid from this escrow.');
                $unusedRefund = $s->status === 'cancelled' && ($line->fund_payment_id || $line->role === 'unused_budget_refund');
                self::require(($s->funded_at !== null || $unusedRefund) && $line->status === 'eligible', 'Only verified completed work or a verified unused-funds refund can be paid, once.');
                if($orderStyle)self::require($line->hasAssignedRecipient(),'Assign the worker or hub before releasing this service payout.');
                else self::require(! empty($line->receiving_destination), 'The recipient must verify receiving details before admin transfers money.');
                if ($line->fund_payment_id) {
                    $incoming = ReturnFundPayment::lockForUpdate()->findOrFail($line->fund_payment_id);
                    self::require($incoming->status === 'verified' && $incoming->amount_cents - $incoming->returned_cents >= $line->amount_cents
                        && ReturnWorkflowService::fundingSummary($order, 0)['shortfall'] <= 0, 'The original escrow no longer has this unused recovery available.');
                } else {
                    $sent = (int) $s->allocations()->whereNull('fund_payment_id')->whereIn('status', ['remitted', 'accepted'])->sum('amount_cents');
                    self::require($sent + $line->amount_cents <= self::poolCents($s), 'Insufficient verified funds remain in this return budget.');
                }
                if(!$orderStyle)self::require(! empty($data['proof_path']) && \Illuminate\Support\Facades\Storage::disk('private')->exists($data['proof_path']), 'Record proof of the actual completed transfer.');
                $ref = self::reference(($data['reference']??null) ?: ('RETURN-RELEASE-'.$line->id.'-'.strtoupper(\Illuminate\Support\Str::random(8))));
                self::require(! ReturnAllocation::where('reference', $ref)->exists() && ! \App\Models\EscrowRemittance::where('bank_reference', $ref)->exists(), 'This outgoing transfer reference was already used.');
                self::require(! \App\Models\ReturnWorkflow::whereRaw('UPPER(REPLACE(refund_reference, \' \', \'\')) = ?', [$ref])->exists(), 'This reference was already used for a buyer refund.');
                $line->update(['status' => 'remitted', 'reference' => $ref, 'proof_path' => $data['proof_path']??null,
                    'remitted_by' => $actor->id, 'remitted_at' => now(),
                    'recipient_user_id' => $line->role === 'admin_service' ? $actor->id : $line->recipient_user_id]);
                if ($line->fund_payment_id) $incoming->increment('returned_cents', $line->amount_cents);
                if ($line->role === 'unused_budget_refund' && $s->reserved_cents > 0) {
                    Payments::refund($order, $line->amount_cents / 100, 'Unused deducted return fees; return #'.$s->return_request_id);
                    $s->reserved_cents = max(0, (int) $s->reserved_cents - (int) $line->amount_cents);
                }
            } elseif ($action === 'accept') {
                abort_unless($line->canAccept($actor), 403);
                if ($line->status === 'accepted') return;
                self::require($line->status === 'remitted', 'Admin has not recorded this payment yet.');
                $line->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $actor->id]);
            } else {
                self::require(false, 'Unknown remittance action.');
            }
            self::event($s, $actor->id, 'remittance_'.$action, 'Allocation #'.$line->id.' '.$line->role);
            $s->save();
        }, 3);
    }

    private static function reference(string $reference): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim($reference)));
        self::require(strlen($value) >= 4 && strlen($value) <= 150, 'Use the complete bank/e-wallet transfer reference.');
        return $value;
    }
}
