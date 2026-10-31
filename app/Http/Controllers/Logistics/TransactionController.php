<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Transaction;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\Rider;
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
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $centerId = (int) $request->query('center_id', 0);

        $query = Transaction::with(['delivery', 'rider', 'logisticsCenter', 'serviceArea']);

        $user = Auth::user();
        $staffCenterId = LogisticsAccess::centerId($user);
        $isCenterScoped = $staffCenterId !== null;

        // Company ownership is enforced first; center scoping narrows it
        // further. Missing assignments produce an empty ledger, never all.
        LogisticsAccess::scopeTransactions($query, $user);

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

        $transactions = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $totals = $query->toBase()->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(rider_fee), 0) as total_rider_fee,
            COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
            COALESCE(SUM(admin_commission), 0) as total_commission
        ')->first();

        $centersQuery = LogisticsCenter::query()->where('is_active', true);
        LogisticsAccess::scopeCenters($centersQuery, $user);

        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $centersQuery->whereRaw('1 = 0');
            } else {
                $centersQuery->whereKey($staffCenterId);
            }
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

            if ($calc) {
                $optional = [
                    'origin_center_id' => $calc['origin_center_id'],
                    'destination_center_id' => $calc['destination_center_id'],
                    'pickup_rider_id' => EscrowEarnings::pickupRiderIdFor($delivery),
                    'origin_hub_fee' => $calc['origin_hub_fee'],
                    'pickup_rider_fee' => $calc['pickup_rider_fee'],
                    'inter_hub_driver_fee' => $calc['inter_hub_driver_fee'],
                    'destination_hub_fee' => $calc['destination_hub_fee'],
                    'last_mile_rider_fee' => $calc['last_mile_rider_fee'],
                ];
                foreach ($optional as $column => $value) {
                    if (Schema::hasColumn('transactions', $column)) {
                        $payload[$column] = $value;
                    }
                }
            }

            Transaction::create($payload);
        });
    }
}




