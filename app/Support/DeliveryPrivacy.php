<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\User;

/**
 * Viewer-aware presentation rules for parcel status history.
 *
 * Origin-private detail (rider assignment notes, origin handoff/pickup rider
 * identities) belongs to the company that owns the route origin. Logistics
 * staff use their company; riders use the company of their persisted assigned
 * hub. Global oversight sees everything and company-less or unknown accounts
 * fail closed.
 */
final class DeliveryPrivacy
{
    /**
     * Columns the privacy predicates need from a status actor. Truncated
     * selections hide role/company fields and silently make every actor look
     * non-logistics, so eager loads must use this list.
     */
    public const ACTOR_COLUMNS = 'id,name,role,is_admin,email,center_id,logistics_company_id';

    private const ASSIGNMENT_STATUSES = ['assigned', 'waiting_for_rider'];

    private const ORIGIN_ACTOR_STATUSES = ['sorting_center_handoff', 'sorting_center_pickup'];

    /**
     * Null = global oversight, 0 = unknown/denied, positive = the viewer's
     * company. Riders authenticate outside the logistics staff model, so their
     * company comes from their persisted hub rather than the request.
     */
    public static function viewerCompanyId(?User $viewer): ?int
    {
        if (! $viewer) {
            return 0;
        }

        if (LogisticsAccess::isGlobal($viewer)) {
            return null;
        }

        $companyId = LogisticsAccess::companyId($viewer);
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        $centerId = (int) ($viewer->rider?->center_id ?? 0);
        if ($centerId <= 0) {
            return 0;
        }

        $riderCompanyId = (int) (LogisticsCenter::query()
            ->whereKey($centerId)
            ->value('logistics_company_id') ?? 0);

        return $riderCompanyId > 0 ? $riderCompanyId : 0;
    }

    public static function canViewOriginPrivate(?User $viewer, Delivery $delivery): bool
    {
        $viewerCompanyId = self::viewerCompanyId($viewer);
        if ($viewerCompanyId === null) {
            return true;
        }

        $originCenterId = (int) ($delivery->routeOriginId() ?? 0);
        if ($viewerCompanyId <= 0 || $originCenterId <= 0) {
            return false;
        }

        $originCompanyId = (int) (LogisticsCenter::query()
            ->whereKey($originCenterId)
            ->value('logistics_company_id') ?? 0);

        return $originCompanyId > 0 && $originCompanyId === $viewerCompanyId;
    }

    /**
     * A blank note is never rendered. Assignment notes carry the rider name
     * and plate inside the text, so they follow the authoring company; handoff
     * and pickup notes follow the route origin. Other statuses stay visible.
     */
    public static function noteVisible(?User $viewer, Delivery $delivery, DeliveryStatusLog $log): bool
    {
        if (blank($log->notes)) {
            return false;
        }

        if (in_array($log->status, self::ASSIGNMENT_STATUSES, true)) {
            return self::sameCompanyAuthor($viewer, $log);
        }

        if (in_array($log->status, self::ORIGIN_ACTOR_STATUSES, true)) {
            return self::canViewOriginPrivate($viewer, $delivery);
        }

        return true;
    }

    public static function actorVisible(?User $viewer, Delivery $delivery, DeliveryStatusLog $log): bool
    {
        if (! LogisticsAccess::canViewLogisticsActor($viewer, $log->changer)) {
            return false;
        }

        return ! in_array($log->status, self::ORIGIN_ACTOR_STATUSES, true)
            || self::canViewOriginPrivate($viewer, $delivery);
    }

    /**
     * Timeline flags consumed by the parcel-detail and tracking views.
     *
     * @return array{canViewOriginPrivate: bool, hiddenAssignmentNoteIds: array<int, int>, hiddenChangerIds: array<int, int>}
     */
    public static function forTimeline(?User $viewer, Delivery $delivery): array
    {
        $hiddenAssignmentNoteIds = $delivery->statusLogs
            ->filter(fn (DeliveryStatusLog $log) => in_array($log->status, self::ASSIGNMENT_STATUSES, true)
                && filled($log->notes)
                && ! self::noteVisible($viewer, $delivery, $log))
            ->map(fn (DeliveryStatusLog $log) => (int) $log->id)
            ->values()
            ->all();

        $hiddenChangerIds = $delivery->statusLogs
            ->filter(fn (DeliveryStatusLog $log) => ! self::actorVisible($viewer, $delivery, $log))
            ->map(fn (DeliveryStatusLog $log) => (int) ($log->changed_by ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'canViewOriginPrivate' => self::canViewOriginPrivate($viewer, $delivery),
            'hiddenAssignmentNoteIds' => $hiddenAssignmentNoteIds,
            'hiddenChangerIds' => $hiddenChangerIds,
        ];
    }

    private static function sameCompanyAuthor(?User $viewer, DeliveryStatusLog $log): bool
    {
        $viewerCompanyId = self::viewerCompanyId($viewer);
        if ($viewerCompanyId === null) {
            return true;
        }

        $authorCompanyId = LogisticsAccess::companyId($log->changer);

        // Same-company visibility requires two valid, positive company ids.
        return $viewerCompanyId > 0
            && $authorCompanyId !== null
            && $authorCompanyId > 0
            && $authorCompanyId === $viewerCompanyId;
    }
}
