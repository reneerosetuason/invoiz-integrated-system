<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
