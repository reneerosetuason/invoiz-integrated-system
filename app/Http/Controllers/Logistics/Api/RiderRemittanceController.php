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

        $base = Remittance::query()->where('rider_id', $rider->id);

        return response()->json([
            'remittances' => $rows,
            'total_due' => round($rows->sum('amount'), 2),
            'summary' => [
                'total_due' => (float) (clone $base)->whereIn('status', ['pending', 'sent'])->sum('amount'),
                'total_confirmed' => (float) (clone $base)->where('status', 'confirmed')->sum('amount'),
                'pending_count' => (clone $base)->where('status', 'pending')->count(),
                'sent_count' => (clone $base)->where('status', 'sent')->count(),
                'confirmed_count' => (clone $base)->where('status', 'confirmed')->count(),
            ],
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

    /**
     * Payout remittances earned by this rider (pickup rider fees, transport driver fees, etc.).
     * Shows whether Admin has released/remitted the payout and whether the rider has accepted it.
     */
    public function earningsPayouts(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;
        if (! $rider) {
            return response()->json(['message' => 'Rider profile not found.'], 403);
        }

        $payouts = \App\Models\EscrowRemittance::with(['order:id,order_number', 'delivery:id,tracking_number'])
            ->where(function ($q) use ($rider, $request) {
                $q->where('rider_id', $rider->id)
                  ->orWhere('recipient_user_id', $request->user()->id);
            })
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'order_number' => $p->order?->order_number ?? ('#' . $p->order_id),
                'tracking_number' => $p->delivery?->tracking_number,
                'role' => $p->recipient_role,
                'role_label' => $p->roleLabel(),
                'rule_applied' => $p->rule_applied,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'status_label' => match ($p->status) {
                    'accepted' => 'Accepted / Confirmed by Rider',
                    'remitted' => 'Remitted by Admin (Ready to Accept)',
                    'pending_remittance' => 'Awaiting Admin Settlement',
                    default => 'Held in Escrow',
                },
                'can_accept' => ($p->status === \App\Models\EscrowRemittance::STATUS_REMITTED),
                'bank_reference' => $p->bank_reference,
                'remitted_at' => $p->remitted_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'payouts' => $payouts,
            'total_earned' => round($payouts->sum('amount'), 2),
            'total_available_to_accept' => round($payouts->where('can_accept', true)->sum('amount'), 2),
            'total_confirmed' => round($payouts->where('status', 'accepted')->sum('amount'), 2),
            'total_pending_admin' => round($payouts->where('status', 'pending_remittance')->sum('amount'), 2),
        ]);
    }

    /**
     * Pickup rider confirms/accepts receipt of their remittance payout after Admin release.
     */
    public function acceptPayout(Request $request, int $id): JsonResponse
    {
        $rider = $request->user()->rider;
        if (! $rider) {
            return response()->json(['message' => 'Rider profile not found.'], 403);
        }

        $escrow = \App\Models\EscrowRemittance::where('id', $id)
            ->where(function ($q) use ($rider, $request) {
                $q->where('rider_id', $rider->id)
                  ->orWhere('recipient_user_id', $request->user()->id);
            })
            ->firstOrFail();

        if ($escrow->status !== \App\Models\EscrowRemittance::STATUS_REMITTED) {
            return response()->json([
                'message' => 'Remittance cannot be accepted yet. Current status: ' . $escrow->status,
            ], 422);
        }

        $escrow->accept($request->user(), $request->input('note'));

        return response()->json([
            'message' => "Remittance of ₱" . number_format((float) $escrow->amount, 2) . " accepted successfully. Earnings confirmed.",
            'payout'  => $escrow->fresh(),
        ]);
    }
}
