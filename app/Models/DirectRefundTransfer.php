<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectRefundTransfer extends Model
{
    protected $fillable = ['return_request_id', 'reference', 'amount_cents', 'revision', 'status', 'receipt_versions', 'buyer_note', 'confirmed_at'];
    protected $hidden = ['receipt_versions'];
    protected function casts(): array
    {
        return ['receipt_versions'=>'encrypted:array', 'amount_cents'=>'integer', 'revision'=>'integer', 'confirmed_at'=>'datetime'];
    }
}
