<?php

namespace App\Support;

use App\Models\{EscrowRemittance, HubTransportDriver, LogisticsCenter, PayoutAccount, ReturnAllocation, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** A reusable receiving account, separate from payment amounts and statuses. */
final class PayoutAccounts
{
    public static function ready(EscrowRemittance|ReturnAllocation $row): bool
    {
        $role = $row instanceof ReturnAllocation ? $row->role : $row->recipient_role;
        // Platform commission is retained internally, not sent to an external payee.
        if (in_array($role, ['admin', 'admin_service'], true)) return true;
        $details = self::forRemittance($row)['details'] ?? null;
        if (!self::complete($details) && $row instanceof ReturnAllocation) {
            $details = $row->receiving_destination;
        }
        return self::complete($details);
    }

    private static function complete(?array $details): bool
    {
        return filled(trim((string) ($details['provider'] ?? '')))
            && filled(trim((string) ($details['holder'] ?? '')))
            && mb_strlen(trim((string) ($details['account'] ?? ''))) >= 4;
    }

    public static function assertReady(EscrowRemittance|ReturnAllocation $row): void
    {
        if (!self::ready($row)) throw \Illuminate\Validation\ValidationException::withMessages([
            'payout_account' => 'The recipient must save complete banking or e-wallet details before this remittance can be released.',
        ]);
    }
    public static function targets($actor): Collection
    {
        if ($actor instanceof HubTransportDriver) {
            if ($actor->status === 'inactive' || ($actor->user && ! $actor->user->isActive())) return collect();
            return collect([self::target('driver', $actor->id, $actor->name)]);
        }
        if (! $actor instanceof User || ! $actor->isActive() || LogisticsAccess::isGlobal($actor)) return collect();

        $driver = Schema::hasTable('hub_transport_drivers') ? $actor->hubTransportDriver : null;
        if ($driver) return self::targets($driver);

        if ($actor->isLogisticsStaff()) {
            return LogisticsCenter::get()->filter(fn ($center) =>
                (LogisticsAccess::isCenterStaff($actor, $center->id)
                    && ((LogisticsAccess::companyId($actor) ?? 0) <= 0 || (int)$center->logistics_company_id === LogisticsAccess::companyId($actor)))
                || ($actor->isCompanyManager() && LogisticsAccess::canAccessCenter($actor, $center->id))
            )->map(fn ($center) => self::target('hub', $center->id, $center->name))->values();
        }
        if ($actor->isSeller() || $actor->role === 'rider') {
            return collect([self::target('user', $actor->id, $actor->name)]);
        }
        return collect();
    }

    private static function target(string $type, int $id, string $label): array
    {
        return ['key' => $type.'-'.$id, 'type' => $type, 'id' => $id, 'label' => $label];
    }

    public static function saved(string $type, int $id): ?PayoutAccount
    {
        return Schema::hasTable('payout_accounts')
            ? PayoutAccount::where('owner_type', $type)->where('owner_id', $id)->first() : null;
    }

    public static function payload(array $target): array
    {
        $saved = self::saved($target['type'], $target['id']);
        return ['key' => $target['key'], 'label' => $target['label'],
            'details' => $saved?->details, 'updated_at' => $saved?->updated_at?->toIso8601String()];
    }

    public static function forActor($actor): Collection
    {
        return self::targets($actor)->map(fn ($target) => self::payload($target));
    }

    /** Resolve the actual payee, never a hub manager's personal account for a hub cut. */
    public static function forRemittance(EscrowRemittance|ReturnAllocation $row): ?array
    {
        $isReturn = $row instanceof ReturnAllocation;
        $role = $isReturn ? $row->role : $row->recipient_role;
        if (in_array($role, ['origin_hub', 'destination_hub', 'collection_hub', 'seller_hub'], true)) {
            $center = $isReturn ? $row->center : $row->logisticsCenter;
            return $center ? self::payload(self::target('hub', $center->id, $center->name)) : null;
        }
        if ($role === 'inter_hub_driver') {
            $driver = $isReturn ? $row->driver : $row->transportDriver;
            if ($driver) return self::payload(self::target('driver', $driver->id, $driver->name));
            // Older order records may use a rider for the inter-hub leg.
        }
        if (in_array($role, ['seller', 'seller_protection_reimbursement', 'pickup_rider', 'last_mile_rider', 'inter_hub_driver',
            'collection_rider', 'seller_delivery_rider', 'replacement_delivery_rider'], true)) {
            $user = $row->recipientUser ?? $row->rider?->user;
            return $user ? self::payload(self::target('user', $user->id, $user->name)) : null;
        }
        return null;
    }
}
