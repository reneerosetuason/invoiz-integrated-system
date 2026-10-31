<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubTransferEvent extends Model
{
    protected $fillable = ['hub_transfer_id', 'delivery_id', 'event_type', 'previous_status', 'new_status', 'notes', 'actor_id', 'hub_transport_driver_id', 'actor_name'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
