<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnWorkflow extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['refund_proof_path', 'recovery_proof_path'];
    protected $casts = ['events' => 'array', 'stock_restored' => 'boolean', 'recovery_amount' => 'decimal:2',
        'refund_sent_at' => 'datetime', 'buyer_confirmed_at' => 'datetime','driver_accepted_at'=>'datetime','driver_arrived_at'=>'datetime'];

    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
    public function center() { return $this->belongsTo(LogisticsCenter::class,'center_id'); }
    public function destinationCenter() { return $this->belongsTo(LogisticsCenter::class,'destination_center_id'); }
    public function rider() { return $this->belongsTo(Rider::class); }
}
