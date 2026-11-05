<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnWorkflow extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['refund_proof_path', 'recovery_proof_path'];
    protected $casts = ['events' => 'array', 'stock_restored' => 'boolean', 'recovery_amount' => 'decimal:2',
        'refund_sent_at' => 'datetime', 'buyer_confirmed_at' => 'datetime'];

    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
}
