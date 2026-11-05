<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class RiderEarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_id',
        'delivery_id',
        'type',
        'amount',
        'earned_on',
        'description',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('gcash_received', function ($query) {
            foreach (['deliveries', 'payments'] as $table) {
                if (! Schema::hasTable($table)) {
                    return;
                }
            }
            $query->where(function ($q) {
                // Financial visibility must also hold for archived orders.
                $q->whereNotExists(function ($payment) {
                    $payment->selectRaw('1')->from('payments')->join('deliveries', 'deliveries.order_id', '=', 'payments.order_id')
                        ->whereColumn('deliveries.id', 'rider_earnings.delivery_id')->where('payments.method', 'gcash');
                });
                if (Schema::hasTable('escrow_remittances')) {
                    $q->orWhereExists(function ($payout) {
                        $payout->selectRaw('1')->from('escrow_remittances')
                            ->whereColumn('escrow_remittances.delivery_id', 'rider_earnings.delivery_id')
                            ->whereColumn('escrow_remittances.rider_id', 'rider_earnings.rider_id')
                            ->where('escrow_remittances.status', 'accepted')
                            ->where(function ($role) {
                                $role->where(fn ($r) => $r->where('rider_earnings.type', 'delivery')->where('recipient_role', 'last_mile_rider'))
                                    ->orWhere(fn ($r) => $r->where('rider_earnings.type', 'pickup')->where('recipient_role', 'pickup_rider'))
                                    ->orWhere(fn ($r) => $r->where('rider_earnings.type', 'inter_hub_transfer')->where('recipient_role', 'inter_hub_driver'));
                            });
                    });
                }
            });
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_on' => 'date',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
