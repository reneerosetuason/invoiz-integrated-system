<?php

namespace App\Services;

use App\Models\{EscrowRemittance, Order, ReturnRequest, ReturnSettlement};
use App\Support\EscrowEarnings;

/** All writes are called inside the settlement's order-first transaction. */
class ReturnFundingService
{
    public static function chooseMethod(Order $order, ReturnSettlement $agreement): string
    {
        if($agreement->directAgreement())return 'deposit';
        if ($agreement->payer !== 'buyer') return 'deposit';
        $preference = $agreement->funding_preference ?? $agreement->funding_method;
        if ($preference !== 'auto') return $preference;
        $gross = (int) $agreement->merchandise_cents + (int) $agreement->shipping_cents;
        $funding = ReturnWorkflowService::fundingSummary($order, $gross / 100, $agreement->id);
        return $agreement->budgetCents() > 0 && $gross > $agreement->budgetCents()
            && $funding['funded'] > 0 && $funding['shortfall'] <= 0 ? 'deduct_refund' : 'deposit';
    }

    public static function preview(ReturnRequest $case, ReturnSettlement $agreement): array
    {
        $prospective = $agreement->payer === 'buyer' && $agreement->funding_method === 'deduct_refund'
            ? max(0, $agreement->budgetCents() - (int) $agreement->reserved_cents) : 0;
        $funding = ReturnWorkflowService::fundingSummary($case->order,
            ($agreement->net_refund_cents + $prospective) / 100, $agreement->id);
        $outstanding = max(0, $agreement->budgetCents() - ReturnSettlementService::poolCents($agreement));
        $reservable = self::reservableCents($case->order, $case, $agreement);
        return [
            'seller_direct_refund'=>$agreement->buyerFaultDirect(),
            'funding' => $funding,
            'budget_outstanding_cents' => $outstanding,
            'buyer_reserved_cents' => (int) $agreement->reserved_cents,
            'seller_reserved_cents' => (int) $agreement->seller_reserved_cents,
            'refund_reserved_cents' => (int) $agreement->refund_reserved_cents,
            'automatic' => $agreement->funding_preference === 'auto',
            'prospective_reservation_cents' => $reservable,
            'payment_due_cents' => max(0, $outstanding - $reservable),
        ];
    }

    /** Read-only estimate; reserve() repeats it under the order lock. */
    private static function reservableCents(Order $order, ReturnRequest $case, ReturnSettlement $agreement): int
    {
        if($agreement->directAgreement())return 0;
        if (! $agreement->agreed() || ! $agreement->quoted_at || $agreement->funded_at || $agreement->closed_at) return 0;
        if ($agreement->payments()->where('purpose', 'return_budget')->where('status', 'submitted')->exists()) return 0;
        if ($agreement->payer === 'buyer' && $agreement->funding_method === 'deduct_refund') {
            $remaining = max(0, $agreement->budgetCents() - (int) $agreement->reserved_cents);
            $funding = ReturnWorkflowService::fundingSummary($order,
                ($agreement->net_refund_cents + $remaining) / 100, $agreement->id);
            if ($remaining > 0 && $funding['funded'] > 0 && $funding['shortfall'] <= 0) {
                return $remaining;
            }
        } elseif ($agreement->payer === 'seller' && $agreement->funding_preference === 'auto') {
            $funding = ReturnWorkflowService::fundingSummary($order, $agreement->net_refund_cents / 100, $agreement->id);
            // Only this seller's unreleased allocation can fund their costs.
            // Protect the entire buyer refund and all other escrow reservations.
            $held = EscrowRemittance::where('order_id', $order->id)->where('seller_id', $case->seller_id)
                ->where('recipient_role', EscrowRemittance::ROLE_SELLER)
                ->whereIn('status', ['held_in_escrow', 'pending_remittance'])->sum('amount');
            $pendingMerchandise = ReturnSettlement::where('order_id', $order->id)->whereNull('closed_at')
                ->where('resolution', '!=', 'exchange')
                ->whereHas('returnRequest', fn ($q) => $q->where('seller_id', $case->seller_id)
                    ->whereDoesntHave('workflow', fn ($w) => $w->whereNotNull('refund_sent_at')))
                ->where(fn ($q) => $q->whereKey($agreement->id)->orWhereNotNull('funded_at')
                    ->orWhere(fn ($p) => $p->whereNotNull('buyer_accepted_at')->whereNotNull('seller_accepted_at')->whereNotNull('admin_decided_at')))
                ->sum('merchandise_cents');
            $entitlement = EscrowEarnings::calculateForSeller($order, (int) $case->seller_id,
                null, null, $pendingMerchandise / 100)['seller_earning'];
            $available = $funding['funded'] > 0 ? min(
                ReturnSettlementService::cents($held), ReturnSettlementService::cents($entitlement),
                max(0, ReturnSettlementService::cents($funding['available']) - (int) $agreement->net_refund_cents)
            ) : 0;
            $amount = min($available, max(0, $agreement->budgetCents() - ReturnSettlementService::poolCents($agreement)));
            return $amount;
        }
        return 0;
    }

    /** Reserve once, only after all parties approved the exact financial terms. */
    public static function reserve(Order $order, ReturnRequest $case, ReturnSettlement $agreement): void
    {
        $amount = self::reservableCents($order, $case, $agreement);
        if ($amount > 0) {
            $field = $agreement->payer === 'buyer' ? 'reserved_cents' : 'seller_reserved_cents';
            $agreement->$field = (int) $agreement->$field + $amount;
            $events = $agreement->events ?? [];
            $events[] = ['action'=>'escrow_reserved', 'actor_id'=>null, 'version'=>$agreement->version,
                'at'=>now()->toIso8601String(), 'buyer_reserved_cents'=>(int)$agreement->reserved_cents,
                'seller_reserved_cents'=>(int)$agreement->seller_reserved_cents,
                'note'=>'Agreed return fees reserved from verified '.$agreement->payer.' funds.'];
            $agreement->events = $events;
            $agreement->save();
            if ($agreement->seller_reserved_cents > 0) EscrowEarnings::syncRemittancesForOrder($order);
        }
    }
}
