<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnSettlement extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['refund_destination', 'replacement_proof_path'];

    protected function casts(): array
    {
        return array_merge(['refund_destination' => 'encrypted:array', 'events' => 'array', 'fee_snapshot'=>'array','cost_snapshot'=>'array',
            'collection_authorized_at'=>'datetime','policy_version'=>'integer',
            'reserved_cents'=>'integer', 'seller_reserved_cents'=>'integer', 'refund_reserved_cents'=>'integer',
            'replacement_stock_reserved_at' => 'datetime', 'replacement_stock_restored_at' => 'datetime'],
            array_fill_keys(array_map(fn ($key) => $key.'_at', ['buyer_accepted', 'seller_accepted', 'admin_decided', 'quoted', 'funded', 'destination_verified', 'collection', 'response_due', 'closed']), 'datetime'));
    }

    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
    public function payments() { return $this->hasMany(ReturnFundPayment::class); }
    public function allocations() { return $this->hasMany(ReturnAllocation::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function physicalReturn(): bool { return in_array($this->resolution, ['return_refund', 'exchange'], true); }
    public function budgetCents(): int { return (int) $this->transport_cents + ($this->buyerFaultDirect()?0:(int) $this->compensation_cents); }
    public function buyerFaultDirect(): bool { return $this->workflow_policy === 'buyer_fault_direct'; }
    public function sellerFaultDirect(): bool { return $this->workflow_policy === 'seller_fault_direct'; }
    public function physicalDirectRefund(): bool { return $this->buyerFaultDirect() || $this->sellerFaultDirect(); }
    public function keepItemDirect(): bool { return $this->workflow_policy==='seller_direct_keep_item' && !$this->physicalReturn(); }
    public function sellerDirectRefund(): bool { return $this->physicalDirectRefund() || $this->keepItemDirect(); }
    public function directAgreement(): bool { return in_array($this->workflow_policy,['direct','buyer_fault_direct','seller_fault_direct','seller_direct_keep_item'],true); }
    public function collectionReady(): bool
    {
        if(!$this->physicalDirectRefund())return (bool)$this->funded_at && $this->agreed();
        return (bool)$this->collection_authorized_at && $this->agreed()
            && (int)$this->payments()->where('purpose','return_budget')->whereIn('status',['submitted','verified'])->sum('amount_cents') >= $this->budgetCents();
    }
    public function agreed(): bool { return $this->buyer_accepted_at && $this->seller_accepted_at && ($this->directAgreement() || $this->admin_decided_at) && ! $this->dispute_note; }
}
