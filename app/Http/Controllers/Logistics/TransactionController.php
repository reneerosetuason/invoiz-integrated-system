<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Transaction;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        if ($user->isStaff() && $user->center_id) {
            $query->where('logistics_center_id', $user->center_id);
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

        if ($centerId > 0) {
            $query->where('logistics_center_id', $centerId);
        }

        $transactions = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $totals = $query->toBase()->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(rider_fee), 0) as total_rider_fee,
            COALESCE(SUM(admin_commission), 0) as total_commission
        ')->first();

        return view('logistics.transactions.index', [
            'transactions' => $transactions,
            'totals' => $totals,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
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

            $amount = (float) ($delivery->delivery_fee ?? 0);
            $riderFee = Transaction::RIDER_FEE_PER_PARCEL;
            $commission = round($amount * Transaction::ADMIN_COMMISSION_RATE, 2);

            Transaction::create([
                'delivery_id' => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'rider_id' => $delivery->rider_id,
                'logistics_center_id' => $delivery->center_id,
                'service_area_id' => $delivery->service_area_id,
                'amount' => $amount,
                'rider_fee' => $riderFee,
                'admin_commission' => $commission,
                'status' => 'completed',
            ]);
        });
    }
}




