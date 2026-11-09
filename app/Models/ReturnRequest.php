<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'buyer_id',
        'seller_id',
        'product_id',
        'quantity',
        'reason',
        'reason_details',
        'evidence_path',
        'evidence_type',
        'refund_amount',
        'status',
        'decision_note',
        'resolved_at',
    ];

    protected $appends = [
        'evidence_url',
        'progress',
        'has_return_agreement',
    ];

    public function workflow() { return $this->hasOne(ReturnWorkflow::class); }
    public function settlement() { return $this->hasOne(ReturnSettlement::class); }

    public function getHasReturnAgreementAttribute(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('return_settlements') || $this->quantity <= 0) return false;
        return $this->settlement()->exists() || ($this->isOpen() && (! $this->workflow || $this->workflow->stage === 'awaiting_collection'));
    }

    /** Financial adjustments can include unused shipping, which must not reduce merchandise twice. */
    public function merchandiseRefundAmount(): float
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('return_settlements')) {
            $agreement = $this->settlement;
            // Seller-paid refunds are an external settlement. Original paid
            // commission/logistics remain earned and are not reversed again.
            if ($agreement?->buyerFaultDirect()) return 0.0;
            if ($agreement && $agreement->resolution !== 'exchange' && $agreement->funded_at) {
                return $agreement->merchandise_cents / 100;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('return_workflows')) {
            $events = $this->workflow?->events ?? [];
            if (isset($events[0]['merchandise_amount'])) {
                return min((float) $this->refund_amount, max(0, (float) $events[0]['merchandise_amount']));
            }
        }
        return (float) $this->refund_amount;
    }

    public function getProgressAttribute(): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('return_workflows')) {
            return null;
        }
        return $this->workflow?->toArray();
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_path ? asset('storage/' . $this->evidence_path) : null;
    }

    protected $casts = [
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'approved', 'item_received'], true);
    }
}
