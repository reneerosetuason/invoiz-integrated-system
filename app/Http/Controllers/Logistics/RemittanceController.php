<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Remittance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only COD remittance oversight (rider -> seller cash handoffs).
 *
 * The seller remains the confirming party (see App\Support\Remittances), so
 * this surface never mutates a remittance. Its only job is to let logistics
 * staff see, for their own center, which riders still owe which sellers.
 */
class RemittanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $base = Remittance::query();

        // Center isolation for non-admins: a remittance belongs to a center
        // when its delivery was handled by that center or its rider is based
        // there. Admins are unrestricted.
        if (! $user->isAdmin() && $user->center_id) {
            $centerId = (int) $user->center_id;

            $base->where(function ($query) use ($centerId) {
                $query->whereHas('delivery', function ($delivery) use ($centerId) {
                    $delivery->where(function ($scope) use ($centerId) {
                        $scope->where('center_id', $centerId)
                            ->orWhere('destination_center_id', $centerId);
                    });
                })->orWhereHas('rider', fn ($rider) => $rider->where('center_id', $centerId));
            });
        }

        $status = $request->query('status');

        if (in_array($status, ['pending', 'sent', 'confirmed'], true)) {
            $base->where('status', $status);
        }

        $totals = [
            'pending' => (float) (clone $base)->where('status', 'pending')->sum('amount'),
            'sent' => (float) (clone $base)->where('status', 'sent')->sum('amount'),
            'confirmed' => (float) (clone $base)->where('status', 'confirmed')->sum('amount'),
            'total' => (float) (clone $base)->sum('amount'),
        ];

        $remittances = (clone $base)
            ->with(['delivery', 'rider', 'order'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('logistics.remittances.index', [
            'remittances' => $remittances,
            'totals' => $totals,
            'status' => $status,
        ]);
    }
}
