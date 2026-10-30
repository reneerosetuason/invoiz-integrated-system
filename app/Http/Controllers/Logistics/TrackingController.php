<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function index(): View
    {
        return view('logistics.tracking.index');
    }

    public function search(Request $request): View
    {
        $rawTracking = $request->input('tracking_number');
        $request->merge([
            'tracking_number' => is_string($rawTracking) ? strtoupper(trim($rawTracking)) : $rawTracking,
        ]);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40'],
        ]);

        $delivery = null;
        $centerId = $this->centerScopeId();

        // A centerless scoped account has no valid delivery scope.
        if ($centerId !== 0) {
            $query = Delivery::with([
                'statusLogs.changer',
                'proofs.rider',
                'rider',
                'creator',
            ])->where('tracking_number', $validated['tracking_number']);

            if ($centerId !== null) {
                $query->atOrIncomingTo($centerId);
            }

            $delivery = $query->first();
        }

        return view('logistics.tracking.show', [
            'delivery' => $delivery,
            'transitions' => config('logistics.transitions'),
        ]);
    }

    /**
     * Null means global access, 0 means a scoped account with no center, and a
     * positive id means that account's assigned center.
     */
    private function centerScopeId(): ?int
    {
        $user = Auth::user();

        if (!$user
            || !$user->isLogisticsStaff()
            || $this->isLogisticsManager()) {
            return null;
        }

        $centerId = (int) ($user->center_id ?? 0);

        return $centerId > 0 ? $centerId : 0;
    }

    private function isLogisticsManager(): bool
    {
        $user = Auth::user();

        return (bool) ($user
            && ($user->isAdmin() || in_array($user->role, ['admin', 'logistics_admin'], true)));
    }
}
