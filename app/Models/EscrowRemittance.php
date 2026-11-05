<?php

namespace App\Models;

use App\Services\GcashEscrowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class EscrowRemittance extends Model
{
    public const ROLE_ORIGIN_HUB = 'origin_hub';

    public const ROLE_PICKUP_RIDER = 'pickup_rider';

    public const ROLE_INTER_HUB_DRIVER = 'inter_hub_driver';

    public const ROLE_DESTINATION_HUB = 'destination_hub';

    public const ROLE_LAST_MILE_RIDER = 'last_mile_rider';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SELLER = 'seller';

    public const STATUS_HELD_IN_ESCROW = 'held_in_escrow';

    public const STATUS_PENDING_REMITTANCE = 'pending_remittance';

    public const STATUS_REMITTED = 'remitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'order_id',
        'delivery_id',
        'payment_transaction_id',
        'hub_transfer_id',
        'hub_transport_driver_id',
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
            'amount' => 'decimal:2',
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

    public function hubTransfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function transportDriver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'hub_transport_driver_id');
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
            self::ROLE_ORIGIN_HUB => 'Origin Logistics Hub',
            self::ROLE_PICKUP_RIDER => 'Pickup Rider',
            self::ROLE_INTER_HUB_DRIVER => 'Inter-Hub Transport Driver',
            self::ROLE_DESTINATION_HUB => 'Destination Logistics Hub',
            self::ROLE_LAST_MILE_RIDER => 'Last-Mile Delivery Rider',
            self::ROLE_ADMIN => 'Admin (Platform Commission)',
            self::ROLE_SELLER => 'Seller (Net Product Earnings)',
            default => ucwords(str_replace('_', ' ', (string) $this->recipient_role)),
        };
    }

    public function settlementModeLabel(): string
    {
        return match ($this->settlement_mode) {
            'cod_retained' => 'Deducted from COD Cash',
            'escrow_bank_remittance' => 'Escrow Bank Remittance',
            'platform_retained' => 'Retained in Platform Escrow',
            'seller_wallet_credit' => 'Seller Payout / Bank Remittance',
            default => ucwords(str_replace('_', ' ', (string) $this->settlement_mode)),
        };
    }

    /**
     * Worker (e.g. pickup rider) accepts the remittance sent by admin.
     */
    public function accept(User $user, ?string $note = null): bool
    {
        return DB::transaction(function () use ($user, $note) {
            $order = $this->order_id ? Order::lockForUpdate()->findOrFail($this->order_id) : null;
            $locked = self::lockForUpdate()->findOrFail($this->id);
            $isRecipient = (int) $locked->recipient_user_id === (int) $user->id
                || ($locked->rider_id && (int) $user->rider?->id === (int) $locked->rider_id)
                || ($locked->logistics_center_id && (int) $user->center_id === (int) $locked->logistics_center_id
                    && in_array($user->role, ['logistics_admin', 'logistics_staff', 'staff'], true));
            abort_unless($isRecipient, 403);
            $this->setRawAttributes($locked->getAttributes(), true);
            if ($this->status !== self::STATUS_REMITTED) {
                return false;
            }

            $noteText = "Confirmed received by {$user->name} (#{$user->id}) on ".now()->format('Y-m-d H:i:s');
            if ($note) {
                $noteText .= ' — Note: '.trim($note);
            }

            $this->update([
                'status' => self::STATUS_ACCEPTED,
                'notes' => $this->notes ? ($this->notes."\n".$noteText) : $noteText,
            ]);

            if ($order && GcashEscrowService::isGcash($order) && $this->rider_id && $this->delivery_id) {
                GcashEscrowService::assertFunded($order);
                $type = match ($this->recipient_role) {
                    self::ROLE_PICKUP_RIDER => 'pickup',
                    self::ROLE_LAST_MILE_RIDER => 'delivery',
                    self::ROLE_INTER_HUB_DRIVER => 'inter_hub_transfer',
                    default => null,
                };
                if ($type) {
                    RiderEarning::firstOrCreate(['delivery_id' => $this->delivery_id, 'type' => $type], [
                        'rider_id' => $this->rider_id, 'amount' => $this->amount,
                        'earned_on' => now()->toDateString(), 'description' => 'Accepted GCash escrow remittance #'.$this->id,
                    ]);
                }
            }

            return true;
        });
    }
}
