<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for logistics company/center authorization.
 *
 * Global access belongs to the Platform Administrator and the designated
 * global Logistics Admin only. Company managers are company-wide but never
 * global; logistics staff are company-scoped and, when assigned, scoped to
 * one center. Missing assignments deny by default.
 */
final class LogisticsAccess
{
    public static function isGlobal(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isLogisticsAdmin());
    }

    /**
     * Null = global (no company restriction), 0 = no valid company (deny),
     * positive = that account's company.
     */
    public static function companyId(?User $user): ?int
    {
        if (! $user) {
            return 0;
        }

        if (self::isGlobal($user)) {
            return null;
        }

        if (! $user->isLogisticsStaff()) {
            return 0;
        }

        $companyId = (int) ($user->logistics_company_id ?? 0);
        if ($companyId > 0) {
            return $companyId;
        }

        // Staff may be linked only through their assigned center.
        $centerId = (int) ($user->center_id ?? 0);
        if ($centerId <= 0) {
            return 0;
        }

        $companyId = (int) (LogisticsCenter::query()
            ->whereKey($centerId)
            ->value('logistics_company_id') ?? 0);

        return $companyId > 0 ? $companyId : 0;
    }

    /**
     * Null = not center-restricted (global or company-wide manager),
     * 0 = center-scoped account with no center (deny), positive = own center.
     */
    public static function centerId(?User $user): ?int
    {
        if (! $user) {
            return 0;
        }

        if (self::isGlobal($user)) {
            return null;
        }

        if (! $user->isLogisticsStaff()) {
            return 0;
        }

        // Company managers operate across every hub inside their company.
        if ($user->isCompanyManager()) {
            return null;
        }

        $centerId = (int) ($user->center_id ?? 0);

        return $centerId > 0 ? $centerId : 0;
    }

    public static function isCompanyScoped(?User $user): bool
    {
        return self::companyId($user) !== null;
    }

    public static function canAccessCompany(?User $user, ?int $companyId): bool
    {
        if (self::isGlobal($user)) {
            return true;
        }

        $scope = self::companyId($user);

        return $scope !== null
            && $scope > 0
            && $companyId !== null
            && (int) $companyId === $scope;
    }

    public static function canAccessCenter(?User $user, ?int $centerId): bool
    {
        if (self::isGlobal($user)) {
            return true;
        }

        if ($centerId === null || $centerId <= 0) {
            return false;
        }

        $scope = self::centerId($user);

        // Null scope means a company-wide manager; company ownership is
        // enforced separately through canAccessCompany(). A manager without
        // a company has no scope at all.
        if ($scope === null) {
            $companyId = self::companyId($user);

            return $companyId !== null && $companyId > 0;
        }

        return $scope > 0 && (int) $centerId === $scope;
    }

    public static function canAccessRider(?User $user, ?Rider $rider): bool
    {
        if (! $rider) {
            return false;
        }

        if (self::isGlobal($user)) {
            return true;
        }

        if (! self::canAccessCenter($user, (int) ($rider->center_id ?? 0))) {
            return false;
        }

        if (self::centerId($user) !== null) {
            return true;
        }

        $companyId = self::companyId($user);
        if ($companyId === null || $companyId <= 0) {
            return false;
        }

        $riderCompanyId = (int) (LogisticsCenter::query()
            ->whereKey((int) $rider->center_id)
            ->value('logistics_company_id') ?? 0);

        return $riderCompanyId > 0 && $riderCompanyId === $companyId;
    }

    /**
     * @param  bool  $allowUnrouted  Permit a parcel that has no handling center
     *                               yet (first receipt into the operator's hub).
     */
    public static function canAccessDelivery(?User $user, ?Delivery $delivery, bool $allowUnrouted = false): bool
    {
        if (! $delivery) {
            return false;
        }

        if (self::isGlobal($user)) {
            return true;
        }

        $centerId = $delivery->center_id !== null ? (int) $delivery->center_id : null;

        if ($centerId === null) {
            // Unrouted parcels belong to the global routing queue, not to a
            // company or hub. First receipt is validated separately against
            // the receiving center.
            return $allowUnrouted && self::companyId($user) !== null && self::companyId($user) > 0;
        }

        $scope = self::centerId($user);
        if ($scope !== null) {
            return $scope > 0 && $centerId === $scope;
        }

        $companyId = self::companyId($user);
        if ($companyId === null || $companyId <= 0) {
            return false;
        }

        $deliveryCompanyId = (int) (LogisticsCenter::query()
            ->whereKey($centerId)
            ->value('logistics_company_id') ?? 0);

        return $deliveryCompanyId > 0 && $deliveryCompanyId === $companyId;
    }

    /**
     * Restrict a query to centers owned by the account's company.
     * The column must exist on the queried table and hold a center id.
     *
     * Staff assigned to a legacy hub that has no company yet are narrowed to
     * that hub instead of being denied; that scope cannot cross companies.
     */
    public static function applyCompanyScope(Builder $query, ?User $user, string $column = 'center_id'): void
    {
        $companyId = self::companyId($user);

        if ($companyId === null) {
            return;
        }

        if ($companyId === 0) {
            $centerId = self::centerId($user);

            if ($centerId !== null && $centerId > 0) {
                $query->where($column, $centerId);
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $query->whereIn(
            $column,
            LogisticsCenter::query()->select('id')->where('logistics_company_id', $companyId)
        );
    }

    /**
     * Restrict a query to the account's assigned center. Company managers
     * and global accounts are not center-restricted.
     */
    public static function applyCenterScope(Builder $query, ?User $user, string $column = 'center_id'): void
    {
        $centerId = self::centerId($user);

        if ($centerId === null) {
            return;
        }

        if ($centerId === 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($column, $centerId);
    }

    /**
     * Scope a logistics-centers query to the account's company (or to the
     * account's own hub when the hub has no company assignment yet).
     */
    public static function scopeCenters(Builder $query, ?User $user): void
    {
        $companyId = self::companyId($user);

        if ($companyId === null) {
            return;
        }

        if ($companyId === 0) {
            $centerId = self::centerId($user);

            if ($centerId !== null && $centerId > 0) {
                $query->whereKey($centerId);
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $query->where('logistics_company_id', $companyId);
    }

    /**
     * Scope a service-areas query to centers owned by the account's company.
     */
    public static function scopeServiceAreas(Builder $query, ?User $user): void
    {
        $companyId = self::companyId($user);

        if ($companyId === null) {
            return;
        }

        if ($companyId === 0) {
            $centerId = self::centerId($user);

            if ($centerId !== null && $centerId > 0) {
                $query->where('logistics_center_id', $centerId);
            } else {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $query->whereIn(
            'logistics_center_id',
            LogisticsCenter::query()->select('id')->where('logistics_company_id', $companyId)
        );
        self::applyCenterScope($query, $user, 'logistics_center_id');
    }

    /**
     * Scope a riders query to centers owned by the account's company, then
     * to the account's own center when it is center-scoped.
     */
    public static function scopeRiders(Builder $query, ?User $user): void
    {
        self::applyCompanyScope($query, $user, 'center_id');
        self::applyCenterScope($query, $user, 'center_id');
    }

    /**
     * Scope a transactions query to the account's company/center.
     */
    public static function scopeTransactions(Builder $query, ?User $user): void
    {
        self::applyCompanyScope($query, $user, 'logistics_center_id');
        self::applyCenterScope($query, $user, 'logistics_center_id');
    }
}
