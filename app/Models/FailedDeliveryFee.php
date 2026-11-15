<?php

namespace App\Models;

use App\Support\LogisticsAccess;
use Illuminate\Database\Eloquent\Model;

class FailedDeliveryFee extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['proof_path'];
    protected $casts = ['fulfilled_at' => 'datetime', 'remitted_at' => 'datetime', 'accepted_at' => 'datetime'];
    public function resolution() { return $this->belongsTo(FailedDeliveryCase::class, 'failed_delivery_case_id'); }
    public function rider() { return $this->belongsTo(Rider::class); }
    public function driver() { return $this->belongsTo(HubTransportDriver::class, 'driver_id'); }
    public function center() { return $this->belongsTo(LogisticsCenter::class); }
    public function logisticsCenter() { return $this->belongsTo(LogisticsCenter::class, 'center_id'); }
    public function transportDriver() { return $this->belongsTo(HubTransportDriver::class, 'driver_id'); }
    public function getRecipientRoleAttribute(): string { return $this->role; }
    public function recipientUser() { return $this->belongsTo(User::class, 'recipient_user_id'); }
    public function payableCents(): int { return max(0, (int) $this->amount_cents - (int) $this->credited_cents); }

    public function canAccept($actor): bool
    {
        if ($actor instanceof HubTransportDriver) return ! $actor->isBlocked() && (! $actor->user || $actor->user->isActive()) && (int) $this->driver_id === (int) $actor->id;
        if (! $actor instanceof User || ! $actor->isActive()) return false;
        if ($this->driver_id) return $actor->hubTransportDriver && ! $actor->hubTransportDriver->isBlocked() && (int) $actor->hubTransportDriver->id === (int) $this->driver_id;
        if ($this->recipient_user_id) return (int) $actor->id === (int) $this->recipient_user_id;
        return $this->center_id && ! LogisticsAccess::isGlobal($actor)
            && (LogisticsAccess::isCenterStaff($actor, (int) $this->center_id)
                || ($actor->isCompanyManager() && LogisticsAccess::canAccessCenter($actor, (int) $this->center_id)));
    }
}
