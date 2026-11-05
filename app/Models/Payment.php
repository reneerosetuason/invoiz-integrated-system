<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'reference_number',
        'paid_at',
        'proof_path',
    ];

    protected $hidden = ['proof_path'];
    protected $appends = ['has_payment_proof'];

    public function getHasPaymentProofAttribute(): bool
    {
        return $this->method === 'gcash' && filled($this->proof_path);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => 'string',
            'status' => 'string',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
