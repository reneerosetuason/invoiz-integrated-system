<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnFundPayment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['proof_path'];
    protected function casts(): array { return ['verified_at' => 'datetime']; }
    public function settlement() { return $this->belongsTo(ReturnSettlement::class, 'return_settlement_id'); }
}
