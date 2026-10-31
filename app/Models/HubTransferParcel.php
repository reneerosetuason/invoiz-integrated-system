<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubTransferParcel extends Model
{
    protected $fillable = ['hub_transfer_id', 'delivery_id', 'loading_status', 'loaded_by', 'loaded_by_hub_driver_id', 'loaded_at', 'receiving_status', 'condition', 'exception_reason', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return ['loaded_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
