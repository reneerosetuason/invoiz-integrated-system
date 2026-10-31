<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authoritative inter-hub transport driver credit.
 *
 * One row per seller shipment per route: (delivery_id, recipient_role) is
 * unique, so an A -> B -> C route cannot pay the flat fee twice. Transfer
 * legs remain fully auditable through hub_transfer_events.
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
        'amount',
        'earned_on',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_on' => 'date',
        ];
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
