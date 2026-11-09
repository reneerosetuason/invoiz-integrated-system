<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class HubTransportDriver extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    /** Statuses that prevent login and new assignments. */
    public const BLOCKED_STATUSES = [self::STATUS_INACTIVE, self::STATUS_SUSPENDED];

    protected $fillable = [
        'user_id',
        'logistics_company_id',
        'logistics_truck_id',
        'name',
        'email',
        'password',
        'phone',
        'license_number',
        'avatar',
        'status',
        'is_online',
        'remarks',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_online' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompany::class, 'logistics_company_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(LogisticsTruck::class, 'logistics_truck_id');
    }

    public function ownedTruck(): HasOne
    {
        return $this->hasOne(LogisticsTruck::class, 'hub_transport_driver_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'driver_id');
    }

    public function latestTransfer(): HasOne
    {
        return $this->hasOne(HubTransfer::class, 'driver_id')->latestOfMany('id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(HubTransferEarning::class, 'hub_transport_driver_id')->acceptedRemittances();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(VehicleIncident::class, 'hub_transport_driver_id');
    }

    public function application(): HasOne
    {
        return $this->hasOne(RiderApplication::class, 'hub_transport_driver_id');
    }

    public function effectiveApplication(): ?RiderApplication
    {
        if ($this->relationLoaded('application') && $this->getRelation('application')) {
            return $this->getRelation('application');
        }

        if ($this->application) {
            return $this->application;
        }

        if ($this->email) {
            return RiderApplication::query()
                ->where(function ($query) {
                    $query->where('hub_transport_driver_id', $this->id)
                        ->orWhere(function ($q) {
                            $q->whereNull('hub_transport_driver_id')
                                ->where('email', strtolower(trim((string) $this->email)))
                                ->where('application_type', 'hub_transport_driver');
                        });
                })
                ->latest('id')
                ->first();
        }

        return null;
    }

    public function isLicenseValid(): bool
    {
        $app = $this->effectiveApplication();
        if (! $app) {
            // Manual driver records created directly by an administrator carry
            // no application; Fleet Management treats them as trusted and they
            // remain assignable (see FleetController::updateDriver).
            return true;
        }
        if ($app->status !== 'approved' || ! $app->license_expiry) {
            return false;
        }

        $expiry = $app->license_expiry instanceof \Carbon\CarbonInterface
            ? $app->license_expiry
            : \Carbon\Carbon::parse($app->license_expiry);

        return ! $expiry->lt(now()->startOfDay());
    }

    public function effectiveTruck(): ?LogisticsTruck
    {
        return $this->truck ?: $this->ownedTruck;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, self::BLOCKED_STATUSES, true);
    }

    public function hasActiveTransfer(): bool
    {
        return $this->transfers()
            ->whereIn('status', HubTransfer::ACTIVE_STATUSES)
            ->exists();
    }

    /**
     * The driver's assigned base hub. The account's logistics center is set
     * when the application is approved; fall back to the approved application
     * for legacy records whose user account predates the center linkage.
     */
    public function homeHub(): ?LogisticsCenter
    {
        return $this->user?->logisticsCenter
            ?? $this->effectiveApplication()?->logisticsCenter;
    }
}
