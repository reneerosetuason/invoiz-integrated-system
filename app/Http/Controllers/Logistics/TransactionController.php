<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Transaction;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);

        $query = Transaction::with(['delivery', 'rider', 'logisticsCenter', 'serviceArea']);

        $user = Auth::user();
        $isCenterScoped = $user
            && $user->isLogisticsStaff()
            && ! $user->isAdmin()
            && ! $user->isLogisticsAdmin();
        $staffCenterId = $isCenterScoped ? (int) ($user->center_id ?? 0) : null;

        if ($isCenterScoped) {
            if ($staffCenterId === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('logistics_center_id', $staffCenterId);
            }
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('tracking_number', 'like', $like)
                  ->orWhereHas('rider', fn ($r) => $r->where('name', 'like', $like));
            });
        }

        if ($status !== '' && in_array($status, ['pending', 'completed', 'failed'])) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '' && strtotime($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (! $isCenterScoped && $centerId > 0) {
            $query->where('logistics_center_id', $centerId);
        }

        $totals = (clone $query)->reorder()->toBase()->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(rider_fee), 0) as total_rider_fee,
            COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
            COALESCE(SUM(admin_commission), 0) as total_commission
        ')->first();

        $transactions = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $centersQuery = LogisticsCenter::query();

        if ($isCenterScoped) {
            if ($staffCenterId === 0) {
                $centersQuery->whereRaw('1 = 0');
            } else {
                $centersQuery->whereKey($staffCenterId);
            }
        } else {
            $centersQuery->where('is_active', true);
        }

        return view('logistics.transactions.index', [
            'transactions' => $transactions,
            'totals' => $totals,
            'centers' => $centersQuery->orderBy('name')->get(),
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

            // Configurable 7-recipient money breakdown per delivery.
            $order = $delivery->order_id ? $delivery->order()->with('items')->first() : null;
            $sellerId = (int) ($delivery->seller_id ?? $order?->items->pluck('seller_id')->filter()->first() ?? 0);

            $calc = ($order && $sellerId > 0)
                ? \App\Support\EscrowEarnings::calculateForSeller($order, $sellerId, $delivery)
                : null;

            $amount = (float) ($calc['last_mile_rider_fee'] ?? ($delivery->delivery_fee ?? \App\Support\EscrowEarnings::lastMileRiderFee()));
            $originHubFee = (float) ($calc['origin_hub_fee'] ?? \App\Support\EscrowEarnings::originHubFee());
            $pickupRiderFee = (float) ($calc['pickup_rider_fee'] ?? \App\Support\EscrowEarnings::pickupRiderPerItemFee());
            $interHubDriverFee = (float) ($calc['inter_hub_driver_fee'] ?? 0.0);
            $destinationHubFee = (float) ($calc['destination_hub_fee'] ?? 0.0);
            $lastMileRiderFee = $amount;
            $logisticsFee = round($originHubFee + $pickupRiderFee + $interHubDriverFee + $destinationHubFee, 2);

            $pickupRiderId = $delivery->sorting_center_handoff_rider_id
                ?: ($delivery->picked_up_at ? $delivery->rider_id : null);
            $interHubRiderId = Schema::hasColumn('deliveries', 'inter_hub_rider_id')
                ? $delivery->inter_hub_rider_id
                : null;
            $originCenterId = $delivery->origin_center_id ?? $delivery->center_id;
            $destCenterId = $delivery->destination_center_id ?? $delivery->center_id;

            $record = [
                'delivery_id' => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'rider_id' => $delivery->rider_id,
                'logistics_center_id' => $delivery->center_id,
                'service_area_id' => $delivery->service_area_id,
                'amount' => $amount,
                'rider_fee' => $amount,
                'logistics_fee' => $logisticsFee,
                'admin_commission' => 0,
                'status' => 'completed',
            ];

            if (Schema::hasColumn('transactions', 'origin_hub_fee')) {
                $record += [
                    'origin_center_id'      => $originCenterId,
                    'destination_center_id' => $destCenterId,
                    'pickup_rider_id'       => $pickupRiderId,
                    'inter_hub_rider_id'    => $interHubRiderId,
                    'origin_hub_fee'        => $originHubFee,
                    'pickup_rider_fee'      => $pickupRiderFee,
                    'inter_hub_driver_fee'  => $interHubDriverFee,
                    'destination_hub_fee'   => $destinationHubFee,
                    'last_mile_rider_fee'   => $lastMileRiderFee,
                ];
            }

            Transaction::create($record);
        });
    }
}




