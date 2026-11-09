<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\User;
use App\Services\FulfillmentWorkflow;
use App\Support\DeliveryPrivacy;
use App\Support\LogisticsAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logistics scanning for the unified mobile app.
 *
 * Facility authority always comes from the authenticated account
 * (users.center_id / logistics_company_id through LogisticsAccess). A
 * facility id supplied by the client is never read or trusted. Parcel state
 * transitions reuse the same rules as the Logistics Web counter scanner
 * (DeliveryController::scanByTracking) and write to the shared deliveries /
 * delivery_status_logs tables, so mobile and web show one parcel history.
 */
class LogisticsScanController extends Controller
{
    private const TRACKING_PATTERN = '/^TRK-\d{8}-[A-Z0-9]{4}$/i';

    public function context(Request $request): JsonResponse
    {
        return response()->json($this->accountPayload($request->user()));
    }

    /**
     * Read-only eligibility preview. Nothing is written here; the client uses
     * it to show the parcel, its hub, and whether Confirm Scan can succeed.
     */
    public function lookup(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! LogisticsAccess::canProcessParcels($user)) {
            return $this->readOnlyOversight();
        }

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40', 'regex:'.self::TRACKING_PATTERN],
        ]);
        $tracking = strtoupper(trim((string) $validated['tracking_number']));

        $delivery = Delivery::notArchived()->where('tracking_number', $tracking)->first();

        if (! $delivery) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'No parcel found with tracking number '.$tracking.'.',
            ], 404);
        }

        if (! LogisticsAccess::canAccessDelivery($user, $delivery)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'This parcel belongs to another logistics hub.',
            ], 403);
        }

        [$status, $payload] = $this->evaluate($delivery, $user);

        return response()->json($payload, $status);
    }

    /**
     * Confirm the scan. The parcel must be received at the operator's hub and
     * both pickup custody and fleet-transfer rules must already be satisfied.
     * Repeating the request is idempotent: it reports "already scanned" and
     * never writes a second status row.
     */
    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! LogisticsAccess::canProcessParcels($user)) {
            return $this->readOnlyOversight();
        }

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40', 'regex:'.self::TRACKING_PATTERN],
        ]);
        $tracking = strtoupper(trim((string) $validated['tracking_number']));

        $delivery = Delivery::notArchived()->where('tracking_number', $tracking)->first();

        if (! $delivery) {
            return response()->json([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'No parcel found with tracking number '.$tracking.'.',
            ], 404);
        }

        if (! LogisticsAccess::canAccessDelivery($user, $delivery)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'This parcel belongs to another logistics hub.',
            ], 403);
        }

        return DB::transaction(function () use ($delivery, $user) {
            $fresh = Delivery::whereKey($delivery->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->archived_at) {
                return response()->json([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'No parcel found with tracking number '.$delivery->tracking_number.'.',
                ], 404);
            }

            if (! LogisticsAccess::canAccessDelivery($user, $fresh)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'message' => 'This parcel belongs to another logistics hub.',
                ], 403);
            }

            if (FulfillmentWorkflow::hasActiveHubTransfer($fresh)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'transfer_in_progress',
                    'message' => 'This parcel remains in fleet-transfer custody until receiving is finalized.',
                    'delivery' => $this->deliveryPayload($fresh),
                    'history' => $this->history($fresh, $user),
                ], 409);
            }

            if ($fresh->parcel_status === 'scanned') {
                return response()->json([
                    'ok' => true,
                    'already' => true,
                    'eligible' => false,
                    'message' => 'Parcel was already scanned'
                        .($fresh->scanned_at ? ' on '.$fresh->scanned_at->format('M d, Y h:i A') : '').'.',
                    'delivery' => $this->deliveryPayload($fresh),
                    'history' => $this->history($fresh, $user),
                ]);
            }

            if ($fresh->order_id && ! FulfillmentWorkflow::hasPickupHandoff($fresh)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'receipt_unverified',
                    'message' => 'This parcel has no recorded pickup and hub handoff. Correct custody before scanning it.',
                    'delivery' => $this->deliveryPayload($fresh),
                    'history' => $this->history($fresh, $user),
                ], 409);
            }

            if ($fresh->parcel_status !== 'received') {
                return response()->json([
                    'ok' => false,
                    'error' => 'invalid_state',
                    'message' => 'Parcel must be received before scanning (current status: '
                        .str_replace('_', ' ', $fresh->parcel_status ?? 'pending_arrival').').',
                    'delivery' => $this->deliveryPayload($fresh),
                    'history' => $this->history($fresh, $user),
                ], 422);
            }

            // Facility is resolved from the authenticated account, never from
            // the request body.
            $facility = $this->scanFacility($user, $fresh);

            $fresh->update([
                'parcel_status' => 'scanned',
                'scanned_at' => now(),
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $fresh->id,
                'status' => 'scanned',
                'location' => $facility['name'] ?? null,
                'notes' => 'Parcel scanned and verified via mobile scanner.',
                'changed_by' => $user->id,
            ]);

            return response()->json([
                'ok' => true,
                'already' => false,
                'eligible' => false,
                'message' => 'Parcel scanned successfully.',
                'facility' => $facility,
                'delivery' => $this->deliveryPayload($fresh),
                'history' => $this->history($fresh, $user),
            ]);
        });
    }

    /**
     * Account + facility payload shared by login and the scan context endpoint.
     */
    public function accountPayload(User $user): array
    {
        $type = $this->accountType($user);
        $companyId = LogisticsAccess::companyId($user);
        $centerId = LogisticsAccess::centerId($user);
        $center = $centerId !== null && $centerId > 0
            ? LogisticsCenter::find($centerId)
            : null;
        $company = null;
        $resolvedCompanyId = $companyId ?? ($center?->logistics_company_id ? (int) $center->logistics_company_id : null);
        if ($resolvedCompanyId) {
            $company = LogisticsCompany::find($resolvedCompanyId);
        }

        $scope = match (true) {
            $center !== null => 'hub',
            LogisticsAccess::isGlobal($user) => 'global',
            default => 'company',
        };

        $logistics = [
            'role' => $type,
            'scope' => $scope,
            'can_scan' => $this->canScan($user),
            'company' => $company ? ['id' => $company->id, 'name' => $company->name] : null,
            'facility' => $center ? [
                'id' => $center->id,
                'name' => $center->name,
                'address' => $center->address,
                'city' => $center->city,
            ] : null,
        ];

        return [
            'account_type' => $type,
            'role' => $type,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'account_type' => $type,
                'rider' => null,
                'logistics' => $logistics,
            ],
            'logistics' => $logistics,
        ];
    }

    private function accountType(User $user): string
    {
        return match (true) {
            $user->isAdmin() => 'platform_admin',
            $user->isLogisticsAdmin() => 'logistics_admin',
            $user->isCompanyManager() => 'company_manager',
            default => 'logistics_staff',
        };
    }

    private function canScan(User $user): bool
    {
        // Counter scanning is a staff operation; management accounts have
        // read-only oversight through the Logistics Web.
        return LogisticsAccess::canProcessParcels($user);
    }

    /** @return array{0: int, 1: array} */
    private function evaluate(Delivery $delivery, User $user): array
    {
        $facility = $this->scanFacility($user, $delivery);
        $deliveryPayload = $this->deliveryPayload($delivery);
        $history = $this->history($delivery, $user);

        if (FulfillmentWorkflow::hasActiveHubTransfer($delivery)) {
            return [409, [
                'ok' => false,
                'eligible' => false,
                'error' => 'transfer_in_progress',
                'message' => 'This parcel remains in fleet-transfer custody until receiving is finalized.',
                'facility' => $facility,
                'delivery' => $deliveryPayload,
                'history' => $history,
            ]];
        }

        if ($delivery->parcel_status === 'scanned') {
            return [200, [
                'ok' => true,
                'eligible' => false,
                'already' => true,
                'error' => null,
                'message' => 'Parcel was already scanned'
                    .($delivery->scanned_at ? ' on '.$delivery->scanned_at->format('M d, Y h:i A') : '').'.',
                'facility' => $facility,
                'delivery' => $deliveryPayload,
                'history' => $history,
            ]];
        }

        if ($delivery->order_id && ! FulfillmentWorkflow::hasPickupHandoff($delivery)) {
            return [409, [
                'ok' => false,
                'eligible' => false,
                'error' => 'receipt_unverified',
                'message' => 'This parcel has no recorded pickup and hub handoff. Correct custody before scanning it.',
                'facility' => $facility,
                'delivery' => $deliveryPayload,
                'history' => $history,
            ]];
        }

        if ($delivery->parcel_status !== 'received') {
            return [422, [
                'ok' => false,
                'eligible' => false,
                'error' => 'invalid_state',
                'message' => 'Parcel must be received before scanning (current status: '
                    .str_replace('_', ' ', $delivery->parcel_status ?? 'pending_arrival').').',
                'facility' => $facility,
                'delivery' => $deliveryPayload,
                'history' => $history,
            ]];
        }

        return [200, [
            'ok' => true,
            'eligible' => true,
            'already' => false,
            'error' => null,
            'message' => 'Parcel is ready to scan.',
            'facility' => $facility,
            'delivery' => $deliveryPayload,
            'history' => $history,
        ]];
    }

    /** Hub that performs this scan, taken from the account (or the parcel for global oversight). */
    private function scanFacility(User $user, ?Delivery $delivery = null): ?array
    {
        $centerId = LogisticsAccess::centerId($user);
        if ($centerId === null) {
            $centerId = $delivery?->center_id !== null ? (int) $delivery->center_id : null;
        }

        if (! $centerId || $centerId <= 0) {
            return null;
        }

        $center = LogisticsCenter::find($centerId);

        return $center ? [
            'id' => $center->id,
            'name' => $center->name,
            'address' => $center->address,
            'city' => $center->city,
        ] : null;
    }

    private function deliveryPayload(Delivery $delivery): array
    {
        $center = $delivery->logisticsCenter;
        $destination = $delivery->destinationCenter;

        return [
            'tracking_number' => $delivery->tracking_number,
            'recipient_name' => $delivery->recipient_name,
            'recipient_phone' => $delivery->recipient_phone,
            'recipient_address' => $delivery->recipient_address,
            'parcel_status' => $delivery->parcel_status,
            'status' => $delivery->status,
            'package_type' => $delivery->package_type,
            'weight' => $delivery->weight,
            'current_hub' => $center ? ['id' => $center->id, 'name' => $center->name] : null,
            'destination_hub' => $destination ? ['id' => $destination->id, 'name' => $destination->name] : null,
            'service_area' => $delivery->serviceArea?->name,
            'received_at' => $delivery->received_at?->toIso8601String(),
            'scanned_at' => $delivery->scanned_at?->toIso8601String(),
            'sorted_at' => $delivery->sorted_at?->toIso8601String(),
            'dispatched_at' => $delivery->dispatched_at?->toIso8601String(),
            'updated_at' => $delivery->updated_at?->toIso8601String(),
        ];
    }

    private function history(Delivery $delivery, User $viewer): array
    {
        return DeliveryStatusLog::query()
            ->where('delivery_id', $delivery->id)
            ->with('changer:'.DeliveryPrivacy::ACTOR_COLUMNS)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (DeliveryStatusLog $log) => [
                'status' => $log->status,
                'notes' => DeliveryPrivacy::noteVisible($viewer, $delivery, $log) ? $log->notes : null,
                'location' => $log->location,
                'actor' => DeliveryPrivacy::actorVisible($viewer, $delivery, $log) ? $log->changer?->name : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function readOnlyOversight(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'read_only',
            'message' => 'Parcel scanning is reserved for logistics staff assigned to a hub. Management accounts have read-only access.',
        ], 403);
    }
}
