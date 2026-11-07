<?php

namespace App\Models;

use App\Support\LogisticsAccess;
use Illuminate\Database\Eloquent\Model;

class ReturnAllocation extends Model
{
    public const ROLES = ['collection_rider', 'collection_hub', 'inter_hub_driver', 'seller_hub', 'seller_delivery_rider', 'replacement_delivery_rider', 'admin_service'];
    protected $guarded = ['id'];
    protected $hidden = ['proof_path', 'receiving_destination'];
    protected function casts(): array { return ['receiving_destination' => 'encrypted:array', 'eligible_at' => 'datetime', 'remitted_at' => 'datetime', 'accepted_at' => 'datetime']; }
    public function settlement() { return $this->belongsTo(ReturnSettlement::class, 'return_settlement_id'); }

    public function canAccept($actor): bool
    {
        if ($actor instanceof HubTransportDriver) return $actor->status !== 'inactive' && (! $actor->user || $actor->user->isActive()) && (int) $this->driver_id === (int) $actor->id;
        if (! $actor instanceof User || ! $actor->isActive()) return false;
        if ($this->driver_id) return (int) $actor->hubTransportDriver?->id === (int) $this->driver_id;
        if ($this->recipient_user_id) return (int) $actor->id === (int) $this->recipient_user_id;
        if ($this->role === 'admin_service') return $actor->isAdmin();
        if (in_array($this->role, ['collection_hub', 'seller_hub'], true)) {
            return ! LogisticsAccess::isGlobal($actor) && (LogisticsAccess::isCenterStaff($actor, (int) $this->center_id)
                || ($actor->isCompanyManager() && LogisticsAccess::canAccessCenter($actor, (int) $this->center_id)));
        }
        return false;
    }
}
