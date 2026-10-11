<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderEarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderEarningsController extends Controller
{
    /**
     * Rider earnings summary and daily breakdown.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $daily = (float) RiderEarning::where('rider_id', $rider->id)
            ->where('earned_on', $today->toDateString())
            ->sum('amount');

        $weekly = (float) RiderEarning::where('rider_id', $rider->id)
            ->where('earned_on', '>=', $weekStart->toDateString())
            ->sum('amount');

        $monthly = (float) RiderEarning::where('rider_id', $rider->id)
            ->where('earned_on', '>=', $monthStart->toDateString())
            ->sum('amount');

        $history = RiderEarning::where('rider_id', $rider->id)
            ->where('earned_on', '>=', $today->copy()->subDays(30)->toDateString())
            ->get()
            ->groupBy('earned_on')
            ->map(function ($earnings, $date) {
                return [
                    'date' => $date,
                    'amount' => (float) $earnings->sum('amount'),
                    'deliveries' => $earnings->count(),
                ];
            })
            ->sortKeysDesc()
            ->values();

        return response()->json([
            'today' => $daily,
            'this_week' => $weekly,
            'this_month' => $monthly,
            'history' => $history,
        ]);
    }
}




