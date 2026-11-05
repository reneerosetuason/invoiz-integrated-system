<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Authoritative inter-hub transport driver credit.
 *
 * Supports both:
 * 1. Trip-level flat compensation per completed Hub Transfer:
 *    (hub_transfer_id, recipient_role) is unique, delivery_id is null.
 * 2. Historical per-shipment records: (delivery_id, recipient_role) is unique.
 */
class HubTransferEarning extends Model
{
    public const ROLE_INTER_HUB_DRIVER = 'inter_hub_driver';

    protected $fillable = [
        'logistics_company_id',
        'hub_transfer_id',
        'delivery_id',
        'hub_transport_driver_id',
        'recipient_role',
        'base_amount',
        'vehicle_allowance',
        'vehicle_ownership',
        'amount',
        'earned_on',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'vehicle_allowance' => 'decimal:2',
            'amount' => 'decimal:2',
            'earned_on' => 'date',
        ];
    }

    /** Keep pending trip accounting out of confirmed earnings and totals. */
    public function scopeAcceptedRemittances(Builder $query): void
    {
        $matchingPayouts = static function (QueryBuilder $payout): void {
            $payout->selectRaw('1')->from('escrow_remittances as driver_payout')
                ->where('driver_payout.recipient_role', EscrowRemittance::ROLE_INTER_HUB_DRIVER)
                ->whereColumn('driver_payout.hub_transport_driver_id', 'hub_transfer_earnings.hub_transport_driver_id')
                ->where('driver_payout.amount', '>', 0)
                ->where(function ($match): void {
                    $match->where(fn ($delivery) => $delivery->whereNotNull('hub_transfer_earnings.delivery_id')
                        ->whereColumn('driver_payout.delivery_id', 'hub_transfer_earnings.delivery_id'))
                        ->orWhere(fn ($trip) => $trip->whereNull('hub_transfer_earnings.delivery_id')
                            ->whereColumn('driver_payout.hub_transfer_id', 'hub_transfer_earnings.hub_transfer_id'));
                });
        };
        $query->whereExists(function ($payout) use ($matchingPayouts): void {
            $matchingPayouts($payout);
            $payout->where('driver_payout.status', EscrowRemittance::STATUS_ACCEPTED);
        })->whereNotExists(function ($payout) use ($matchingPayouts): void {
            $matchingPayouts($payout);
            $payout->whereNotIn('driver_payout.status', [EscrowRemittance::STATUS_ACCEPTED, EscrowRemittance::STATUS_VOIDED]);
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'hub_transport_driver_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }
}
