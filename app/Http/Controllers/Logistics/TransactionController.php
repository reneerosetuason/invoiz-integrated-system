<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Transaction;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Support\EscrowEarnings;
use App\Support\LogisticsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50], true) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);
        $riderId = (int) $request->query('rider_id', 0);
        $rawAssignmentType = trim((string) $request->query('assignment_type', ''));
        $assignmentType = in_array($rawAssignmentType, [Transaction::ASSIGNMENT_PICKUP, Transaction::ASSIGNMENT_HUB_TO_DOOR], true)
            ? $rawAssignmentType
            : '';

        $hasPickupRiderCol = Schema::hasColumn('transactions', 'pickup_rider_id');
        $hasOriginCenterCol = Schema::hasColumn('transactions', 'origin_center_id');
        $hasDestCenterCol = Schema::hasColumn('transactions', 'destination_center_id');
        $hasHandoffRiderCol = Schema::hasColumn('deliveries', 'sorting_center_handoff_rider_id');

        $relations = ['delivery', 'rider', 'logisticsCenter', 'serviceArea'];
        if ($hasPickupRiderCol) {
            $relations[] = 'pickupRider';
        }
        if ($hasOriginCenterCol) {
            $relations[] = 'originCenter';
        }
        if ($hasDestCenterCol) {
            $relations[] = 'destinationCenter';
        }
        if ($hasHandoffRiderCol) {
            $relations[] = 'delivery.sortingCenterHandoffRider';
        }

        $query = Transaction::with($relations);

        $user = Auth::user();
        $staffCenterId = LogisticsAccess::centerId($user);
        $isCenterScoped = $staffCenterId !== null;

        // Company ownership is enforced first; center scoping narrows it
        // further. Missing assignments produce an empty ledger, never all.
        LogisticsAccess::scopeTransactions($query, $user);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like, $hasPickupRiderCol, $hasHandoffRiderCol) {
                $q->where('tracking_number', 'like', $like)
                  ->orWhereHas('rider', fn ($r) => $r->where('name', 'like', $like));

                if ($hasPickupRiderCol) {
                    $q->orWhereHas('pickupRider', fn ($r) => $r->where('name', 'like', $like));
                }

                if ($hasHandoffRiderCol) {
                    $q->orWhereHas('delivery.sortingCenterHandoffRider', fn ($r) => $r->where('name', 'like', $like));
                }
            });
        }

        if ($status !== '' && in_array($status, ['pending', 'completed', 'failed'], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '' && strtotime($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (! $isCenterScoped && $centerId > 0) {
            $query->where(function ($q) use ($centerId, $hasOriginCenterCol, $hasDestCenterCol) {
                $q->where('logistics_center_id', $centerId);
                if ($hasOriginCenterCol) {
                    $q->orWhere('origin_center_id', $centerId);
                }
                if ($hasDestCenterCol) {
                    $q->orWhere('destination_center_id', $centerId);
                }
            });
        }

        $allMatching = (clone $query)->orderByDesc('created_at')->get();

        if ($assignmentType !== '' || $riderId > 0) {
            $allMatching = $allMatching->filter(function (Transaction $tx) use ($assignmentType, $riderId): bool {
                if ($assignmentType !== '' && ! $tx->matchesAssignmentType($assignmentType)) {
                    return false;
                }

                if ($riderId > 0) {
                    if ($assignmentType === Transaction::ASSIGNMENT_PICKUP) {
                        return $tx->pickupRiderId() === $riderId;
                    }
                    if ($assignmentType === Transaction::ASSIGNMENT_HUB_TO_DOOR) {
                        return $tx->hubToDoorRiderId() === $riderId;
                    }

                    return $tx->pickupRiderId() === $riderId
                        || $tx->hubToDoorRiderId() === $riderId
                        || (int) ($tx->rider_id ?? 0) === $riderId;
                }

                return true;
            })->values();

            $matchingIds = $allMatching->pluck('id')->all();
            if ($matchingIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereKey($matchingIds);
            }
        }

        $transactions = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $totalCount = $allMatching->count();
        $totalAmount = round((float) $allMatching->sum(fn (Transaction $tx) => (float) $tx->amount), 2);
        $totalPickupParcels = (int) $allMatching->sum(function (Transaction $tx) use ($assignmentType, $riderId): int {
            if ($riderId > 0 && $tx->pickupRiderId() !== $riderId) {
                return 0;
            }

            return $tx->pickupParcelsCount($assignmentType ?: null);
        });
        $totalHubToDoorParcels = (int) $allMatching->sum(function (Transaction $tx) use ($assignmentType, $riderId): int {
            if ($riderId > 0 && $tx->hubToDoorRiderId() !== $riderId) {
                return 0;
            }

            return $tx->hubToDoorParcelsCount($assignmentType ?: null);
        });

        $totalPickupFees = Transaction::calculatePickupFee($totalPickupParcels);
        $totalHubToDoorFees = Transaction::calculateHubToDoorFee($totalHubToDoorParcels);
        $totalRiderFees = round($totalPickupFees + $totalHubToDoorFees, 2);
        $totalLogisticsFee = round((float) $allMatching->sum(fn (Transaction $tx) => (float) ($tx->logistics_fee ?? 0)), 2);
        $totalCommissions = round((float) $allMatching->sum(fn (Transaction $tx) => $tx->managementCommission()), 2);

        $totals = (object) [
            'total_count' => $totalCount,
            'total_transactions' => $totalCount,
            'total_amount' => $totalAmount,
            'total_pickup_parcels' => $totalPickupParcels,
            'total_pickup_fees' => $totalPickupFees,
            'total_pickup_fee' => $totalPickupFees,
            'total_hub_to_door_parcels' => $totalHubToDoorParcels,
            'total_hub_to_door_fees' => $totalHubToDoorFees,
            'total_hub_to_door_fee' => $totalHubToDoorFees,
            'total_rider_fees' => $totalRiderFees,
            'total_rider_fee' => $totalRiderFees,
            'total_logistics_fee' => $totalLogisticsFee,
            'total_commissions' => $totalCommissions,
            'total_commission' => $totalCommissions,
        ];

        $riderAssignmentGroups = [];
        foreach ($allMatching as $tx) {
            foreach ($tx->assignmentLegs($assignmentType ?: null) as $leg) {
                if ($riderId > 0 && (int) ($leg['rider_id'] ?? 0) !== $riderId) {
                    continue;
                }
                $groupKey = ($leg['type'] ?? 'unknown') . ':' . ($leg['rider_id'] ?? 'unassigned');
                if (! isset($riderAssignmentGroups[$groupKey])) {
                    $riderAssignmentGroups[$groupKey] = [
                        'type' => $leg['type'],
                        'label' => $leg['label'],
                        'rider_id' => $leg['rider_id'],
                        'rider_name' => $leg['rider_name'],
                        'hub_name' => $leg['hub_name'],
                        'parcels' => 0,
                        'fee_per_parcel' => $leg['fee_per_parcel'],
                        'total_fee' => 0.0,
                    ];
                }
                $riderAssignmentGroups[$groupKey]['parcels'] += 1;
                $riderAssignmentGroups[$groupKey]['total_fee'] = round(
                    $riderAssignmentGroups[$groupKey]['parcels'] * $riderAssignmentGroups[$groupKey]['fee_per_parcel'],
                    2
                );
            }
        }

        $centersQuery = LogisticsCenter::query()->where('is_active', true);
        LogisticsAccess::scopeCenters($centersQuery, $user);

        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $centersQuery->whereRaw('1 = 0');
            } else {
                $centersQuery->whereKey($staffCenterId);
            }
        }

        $riders = collect();
        if (Schema::hasTable('riders')) {
            $ridersQuery = Rider::query();
            LogisticsAccess::scopeRiders($ridersQuery, $user);
            $riders = $ridersQuery->orderBy('name')->get();
        }

        return view('logistics.transactions.index', [
            'transactions' => $transactions,
            'totals' => $totals,
            'centers' => $centersQuery->orderBy('name')->get(),
            'riders' => $riders,
            'assignmentType' => $assignmentType,
            'riderId' => $riderId,
            'riderAssignmentSummary' => array_values($riderAssignmentGroups),
            'riderAssignmentMap' => $riderAssignmentGroups,
        ]);
    }

    public function storeForDelivery(Delivery $delivery): void
    {
        if ($delivery->status !== 'delivered') {
            return;
        }

        DB::transaction(function () use ($delivery) {
            $exists = Transaction::where('delivery_id', $delivery->id)->exists();
            if ($exists) {
                return;
            }

            // Derive the ledger row from the same allocation the escrow and
            // settlement use. Rider pay is the last-mile fee; hub, pickup and
            // linehaul costs are logistics. The platform commission lives on
            // payment_transactions only, so the seller is never double-charged.
            $order = $delivery->order()->with(['items', 'orderVouchers.voucher'])->first();
            $sellerId = (int) ($delivery->seller_id
                ?? $order?->items->pluck('seller_id')->filter()->first()
                ?? 0);
            $calc = ($order && $sellerId > 0)
                ? EscrowEarnings::calculateForSeller($order, $sellerId, $delivery)
                : null;

            $riderFee = $calc
                ? round((float) $calc['last_mile_rider_fee'], 2)
                : round((float) ($delivery->delivery_fee ?? Order::SHIPPING_FEE_PER_SELLER), 2);
            $logisticsFee = $calc
                ? round((float) $calc['merch_logistics_deducted'], 2)
                : Order::LOGISTICS_FEE_PER_DELIVERY;

            $pickupRiderId = $this->resolveActualPickupRiderId($delivery);
            $pickupFee = $pickupRiderId !== null ? Transaction::PICKUP_FEE_PER_PARCEL : 0.00;
            $lastMileFee = $delivery->rider_id !== null ? Transaction::HUB_TO_DOOR_FEE_PER_PARCEL : 0.00;

            $payload = [
                'delivery_id' => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'rider_id' => $delivery->rider_id,
                'logistics_center_id' => $delivery->center_id,
                'service_area_id' => $delivery->service_area_id,
                'amount' => $riderFee,
                'rider_fee' => $riderFee,
                'logistics_fee' => $logisticsFee,
                'admin_commission' => 0,
                'status' => 'completed',
            ];

            $optional = [
                'origin_center_id' => $calc ? $calc['origin_center_id'] : ($delivery->origin_center_id ?? $delivery->center_id),
                'destination_center_id' => $calc ? $calc['destination_center_id'] : ($delivery->destination_center_id ?? $delivery->center_id),
                'pickup_rider_id' => $pickupRiderId,
                'origin_hub_fee' => $calc ? $calc['origin_hub_fee'] : 0.00,
                'pickup_rider_fee' => $pickupFee,
                'inter_hub_driver_fee' => $calc ? $calc['inter_hub_driver_fee'] : 0.00,
                'destination_hub_fee' => $calc ? $calc['destination_hub_fee'] : 0.00,
                'last_mile_rider_fee' => $lastMileFee,
            ];
            foreach ($optional as $column => $value) {
                if (Schema::hasColumn('transactions', $column)) {
                    $payload[$column] = $value;
                }
            }

            Transaction::create($payload);
        });
    }

    /**
     * Resolve the actual pickup rider for a delivery without falsely treating
     * the last-mile Hub-to-Door rider as a pickup rider.
     */
    private function resolveActualPickupRiderId(Delivery $delivery): ?int
    {
        if ($delivery->sorting_center_handoff_rider_id !== null && (int) $delivery->sorting_center_handoff_rider_id > 0) {
            return (int) $delivery->sorting_center_handoff_rider_id;
        }

        try {
            if (Schema::hasTable('rider_earnings')) {
                $earning = RiderEarning::where('delivery_id', $delivery->id)
                    ->where('type', 'pickup')
                    ->first();
                if ($earning && (int) ($earning->rider_id ?? 0) > 0) {
                    return (int) $earning->rider_id;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}




