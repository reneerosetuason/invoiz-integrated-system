<?php

namespace App\Support;

use App\Models\HubTransportDriver;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\User;
use App\Rules\SeparateAccountEmail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Cross-role email uniqueness for logistics accounts.
 *
 * One email may belong to only one of: Logistics Admin, Logistics Staff,
 * Delivery Rider, Hub Transport Driver. The check is advisory at the
 * application layer (no new database constraint) and never merges, renames,
 * or deletes existing rows — callers reject the new account instead.
 */
final class AccountEmailGuard
{
    /** Roles that represent logistics personnel inside the users table. */
    public const LOGISTICS_ROLES = ['admin', 'staff', 'logistics_staff', 'logistics_admin'];

    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /**
     * First conflicting role label for the email, excluding the given rows
     * (used when an account edits its own record).
     *
     * @param  array{user_id?: int, rider_id?: int, driver_id?: int}  $ignore
     */
    public static function conflict(?string $email, array $ignore = []): ?string
    {
        $email = self::normalize($email);
        if ($email === '') {
            return null;
        }

        if (SeparateAccountEmail::marketplaceExists($email, $ignore['user_id'] ?? null)) {
            return 'This email is already registered to a Buyer or Seller account and cannot be reused for logistics.';
        }

        $logisticsAccount = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where(function ($query): void {
                $query->whereIn('role', self::LOGISTICS_ROLES);
                if (Schema::hasColumn('users', 'is_admin')) {
                    $query->orWhere('is_admin', true);
                }
            })
            ->when(! empty($ignore['user_id']), fn ($query) => $query->whereKeyNot($ignore['user_id']))
            ->exists();

        if ($logisticsAccount) {
            return 'This email is already registered to a Logistics account and cannot be reused.';
        }

        $riderExists = Schema::hasTable('riders') && Rider::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when(! empty($ignore['rider_id']), fn ($query) => $query->whereKeyNot($ignore['rider_id']))
            ->exists();

        if ($riderExists) {
            return 'This email is already registered to a Delivery Rider account and cannot be reused.';
        }

        $driverExists = Schema::hasTable('hub_transport_drivers') && HubTransportDriver::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when(! empty($ignore['driver_id']), fn ($query) => $query->whereKeyNot($ignore['driver_id']))
            ->exists();

        if ($driverExists) {
            return 'This email is already registered to a Hub Transport Driver account and cannot be reused.';
        }

        return null;
    }

    /** Pending applications hold an email reservation before an account exists. */
    public static function hasPendingApplication(?string $email): bool
    {
        $email = self::normalize($email);
        if ($email === '') {
            return false;
        }

        return Schema::hasTable('rider_applications') && RiderApplication::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where('status', 'pending')
            ->exists();
    }

    /** @throws ValidationException */
    public static function assertAvailable(?string $email, array $ignore = []): void
    {
        if ($message = self::conflict($email, $ignore)) {
            throw ValidationException::withMessages(['email' => $message]);
        }

        if (self::hasPendingApplication($email)) {
            throw ValidationException::withMessages([
                'email' => 'This email already has a pending rider or driver application on file.',
            ]);
        }
    }
}
