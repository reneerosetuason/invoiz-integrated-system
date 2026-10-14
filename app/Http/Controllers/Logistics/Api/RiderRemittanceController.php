<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Remittance;
use App\Support\Remittances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderRemittanceController extends Controller
{
    /** Cash handoffs this rider still owes sellers (pending + sent). */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;
        if (! $rider) {
            return response()->json(['message' => 'Rider profile not found.'], 403);
        }

        $rows = Remittance::with(['order:id,order_number,total_amount,status', 'delivery:id,tracking_number,status'])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['pending', 'sent'])
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'order_id' => $r->order_id,
                'order_number' => $r->order->order_number ?? ('#' . $r->order_id),
                'tracking_number' => $r->delivery->tracking_number ?? null,
                'amount' => (float) $r->amount,
                'status' => $r->status,
                'reference' => $r->reference,
                'created_at' => $r->created_at,
            ]);

        return response()->json([
            'remittances' => $rows,
            'total_due' => round($rows->sum('amount'), 2),
        ]);
    }

    /** Rider marks the bank transfer sent (seller must still confirm). */
    public function markSent(Request $request, Remittance $remittance): JsonResponse
    {
        $rider = $request->user()->rider;
        if (! $rider) {
            return response()->json(['message' => 'Rider profile not found.'], 403);
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $remittance = Remittances::markSent($remittance, (int) $rider->id, $validated['reference'] ?? null);

        return response()->json([
            'message' => 'Marked as sent. The seller will confirm receipt to settle the sale.',
            'remittance' => $remittance,
        ]);
    }
}
