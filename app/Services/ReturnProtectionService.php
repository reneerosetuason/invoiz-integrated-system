<?php

namespace App\Services;

use App\Models\ReturnSettlement;

class ReturnProtectionService
{
    public static function quote(ReturnSettlement $agreement): void
    {
        // Newly quoted returns use the existing payer flow, without platform subsidies.
        foreach (['platform_subsidy_cents', 'seller_reimbursement_cents'] as $field) {
            if ($agreement->getAttribute($field)) {
                $agreement->setAttribute($field, 0);
            }
        }
    }

    public static function reserve(ReturnSettlement $agreement): void
    {
        if (! $agreement->platform_subsidy_cents || ! $agreement->agreed() || $agreement->closed_at) {
            return;
        }
        LogisticsFundingService::reserve('RETURN:'.$agreement->id, (int) $agreement->platform_subsidy_cents, $agreement->order_id,
            ['return_id' => $agreement->return_request_id, 'version' => $agreement->version]);
    }

    /** Return unused platform money to its own pool, never to the buyer or seller. */
    public static function reconcileUnused(ReturnSettlement $agreement, int $used): void
    {
        if (! $agreement->platform_subsidy_cents) {
            return;
        }
        $needed = min((int) $agreement->platform_subsidy_cents, max(0, $used - $agreement->payerBudgetCents()));
        LogisticsFundingService::reserve('RETURN:'.$agreement->id, $needed, $agreement->order_id, ['reconciled_unused' => true]);
    }

    public static function finishIfPaid(ReturnSettlement $agreement): void
    {
        if (! $agreement->platform_subsidy_cents) {
            return;
        }
        if (! $agreement->allocations()->whereNotIn('status', ['remitted', 'accepted', 'voided'])->exists()) {
            LogisticsFundingService::finish('RETURN:'.$agreement->id, true);
        }
    }
}
