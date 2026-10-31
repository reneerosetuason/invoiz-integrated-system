<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubTransfer extends Model
{
    public const ACTIVE_STATUSES = ['pending', 'assigned', 'loading', 'ready_for_dispatch', 'in_transit', 'arrived', 'receiving', 'exception', 'on_hold'];

    protected $fillable = ['reference', 'logistics_company_id', 'origin_center_id', 'destination_center_id', 'truck_id', 'driver_id', 'status', 'planned_dispatch_at', 'notes', 'created_by', 'dispatched_by', 'arrived_by', 'received_by', 'dispatched_at', 'arrived_at', 'received_at', 'origin_arrived_at', 'destination_arrived_at'];

    protected function casts(): array
    {
        return ['planned_dispatch_at' => 'datetime', 'dispatched_at' => 'datetime', 'arrived_at' => 'datetime', 'received_at' => 'datetime', 'origin_arrived_at' => 'datetime', 'destination_arrived_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'origin_center_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class, 'destination_center_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'truck_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(HubTransportDriver::class, 'driver_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(HubTransferParcel::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HubTransferEvent::class);
    }
}
