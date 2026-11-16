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
        $returnEarnings=\App\Support\ReturnServiceEarnings::rider($rider);

        $daily = (float) RiderEarning::where('rider_id', $rider->id)
            ->whereDate('earned_on', $today->toDateString())
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
                    'breakdown' => $earnings->map(fn ($e) => [
                        'type' => $e->type,
                        'amount' => (float) $e->amount,
                        'description' => $e->description,
                        'delivery_id' => $e->delivery_id,
                    ])->values(),
                ];
            })
            ->sortKeysDesc()
            ->values();

        $days=$history->mapWithKeys(fn($day)=>[substr($day['date'],0,10)=>$day]);
        foreach($returnEarnings as $earning){
            if(!$earning->accepted_at || $earning->accepted_at->lt($today->copy()->subDays(30)))continue;
            $date=$earning->accepted_at->toDateString();$day=$days->get($date,['date'=>$date,'amount'=>0,'deliveries'=>0,'breakdown'=>collect()]);
            $day['amount']+=(float)$earning->amount_cents/100;$day['deliveries']++;
            $day['breakdown']=collect($day['breakdown'])->push(['type'=>'return_service','amount'=>$earning->amount_cents/100,
                'description'=>'Return #'.$earning->settlement->return_request_id.' · '.str_replace('_',' ',$earning->role),'delivery_id'=>null,'return_id'=>$earning->settlement->return_request_id]);
            $days->put($date,$day);
        }
        return response()->json([
            'today' => $daily+\App\Support\ReturnServiceEarnings::amount($returnEarnings,$today->toDateString()),
            'this_week' => $weekly+\App\Support\ReturnServiceEarnings::amount($returnEarnings,$weekStart->toDateString()),
            'this_month' => $monthly+\App\Support\ReturnServiceEarnings::amount($returnEarnings,$monthStart->toDateString()),
            'history' => $days->sortKeysDesc()->values(),
        ]);
    }
}




