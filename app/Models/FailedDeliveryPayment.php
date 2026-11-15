<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedDeliveryPayment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['proof_path', 'revisions'];
    protected $casts = ['verified_at' => 'datetime', 'receipt_viewed_at' => 'datetime', 'revisions' => 'array'];
    public function resolution() { return $this->belongsTo(FailedDeliveryCase::class, 'failed_delivery_case_id'); }
}
