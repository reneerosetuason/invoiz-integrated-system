<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnSettlement extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['refund_destination', 'replacement_proof_path'];

    protected function casts(): array
    {
        return array_merge(['refund_destination' => 'encrypted:array', 'events' => 'array', 'replacement_stock_reserved_at' => 'datetime', 'replacement_stock_restored_at' => 'datetime'],
            array_fill_keys(array_map(fn ($key) => $key.'_at', ['buyer_accepted', 'seller_accepted', 'admin_decided', 'quoted', 'funded', 'destination_verified', 'collection', 'response_due', 'closed']), 'datetime'));
    }

    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
    public function payments() { return $this->hasMany(ReturnFundPayment::class); }
    public function allocations() { return $this->hasMany(ReturnAllocation::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function physicalReturn(): bool { return in_array($this->resolution, ['return_refund', 'exchange'], true); }
    public function budgetCents(): int { return (int) $this->transport_cents + (int) $this->compensation_cents; }
    public function agreed(): bool { return $this->buyer_accepted_at && $this->seller_accepted_at && $this->admin_decided_at && ! $this->dispute_note; }
}
