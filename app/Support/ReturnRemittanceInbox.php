<?php

namespace App\Support;

use App\Models\ReturnAllocation;
use Illuminate\Database\Eloquent\Builder;

final class ReturnRemittanceInbox
{
    public const ROLES = [...ReturnAllocation::DELIVERY_ROLES, 'replacement_delivery_rider', 'seller_protection_reimbursement'];

    /** Read the existing return ledger; never create a second delivery payout. */
    public static function query($recipient, bool $includePending=false): Builder
    {
        $query = ReturnAllocation::whereIn('role', self::ROLES);
        if(!$includePending)$query->whereIn('status', ['remitted', 'accepted']);
        $ids = (clone $query)->get()->filter(fn ($line) => $line->canAccept($recipient))->modelKeys();

        return $query->whereKey($ids);
    }

    public static function page($recipient): array
    {
        $base = self::query($recipient);

        return [
            'rows' => (clone $base)->with('settlement.returnRequest.item')->latest('id')
                ->paginate(8, ['*'], 'return_page')->withQueryString(),
            'awaiting_cents' => (int) (clone $base)->where('status', 'remitted')->sum('amount_cents'),
            'accepted_cents' => (int) (clone $base)->where('status', 'accepted')->sum('amount_cents'),
        ];
    }
}
