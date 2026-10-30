<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowRemittance extends Model
{
    public const ROLE_ORIGIN_HUB       = 'origin_hub';
    public const ROLE_PICKUP_RIDER     = 'pickup_rider';
    public const ROLE_INTER_HUB_DRIVER = 'inter_hub_driver';
    public const ROLE_DESTINATION_HUB  = 'destination_hub';
    public const ROLE_LAST_MILE_RIDER  = 'last_mile_rider';
    public const ROLE_ADMIN            = 'admin';
    public const ROLE_SELLER           = 'seller';

    protected $fillable = [
        'order_id',
        'delivery_id',
        'payment_transaction_id',
        'recipient_role',
        'recipient_user_id',
        'rider_id',
        'logistics_center_id',
        'seller_id',
        'recipient_name',
        'rule_applied',
        'amount',
        'settlement_mode',
        'status',
        'bank_reference',
        'remitted_at',
        'remitted_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'remitted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function logisticsCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'logistics_center_id');
    }

    public function remitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitted_by');
    }

    public function roleLabel(): string
    {
        return match ($this->recipient_role) {
            self::ROLE_ORIGIN_HUB       => 'Origin Logistics Hub',
            self::ROLE_PICKUP_RIDER     => 'Pickup Rider',
            self::ROLE_INTER_HUB_DRIVER => 'Inter-Hub Transport Driver',
            self::ROLE_DESTINATION_HUB  => 'Destination Logistics Hub',
            self::ROLE_LAST_MILE_RIDER  => 'Last-Mile Delivery Rider',
            self::ROLE_ADMIN            => 'Admin (Platform Commission)',
            self::ROLE_SELLER           => 'Seller (Net Product Earnings)',
            default                     => ucwords(str_replace('_', ' ', (string) $this->recipient_role)),
        };
    }

    public function settlementModeLabel(): string
    {
        return match ($this->settlement_mode) {
            'cod_retained'           => 'Deducted from COD Cash',
            'escrow_bank_remittance' => 'Escrow Bank Remittance',
            'platform_retained'      => 'Retained in Platform Escrow',
            'seller_wallet_credit'   => 'Seller Payout / Bank Remittance',
            default                  => ucwords(str_replace('_', ' ', (string) $this->settlement_mode)),
        };
    }
}

