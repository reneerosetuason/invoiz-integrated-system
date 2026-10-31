<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Support\LogisticsAccess;
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
        $user = Auth::user();

        // Scoping is applied unconditionally: global accounts are
        // unrestricted, unassigned accounts resolve to no rows, and every
        // other account is confined to its company/hub. The tracking page
        // must never become a cross-company lookup. A receiving hub may also
        // see a parcel that active fleet records are bringing to it.
        $query = Delivery::with([
            'statusLogs.changer',
            'proofs.rider',
            'rider',
            'creator',
        ])->where('tracking_number', $validated['tracking_number']);

        LogisticsAccess::applyCompanyScope($query, $user);

        $staffCenterId = LogisticsAccess::centerId($user);
        if ($staffCenterId !== null) {
            if ($staffCenterId === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $query->atOrIncomingToHub($staffCenterId);
            }
        }

        $delivery = $query->first();

        return view('logistics.tracking.show', [
            'delivery' => $delivery,
            'transitions' => config('logistics.transitions'),
        ]);
    }
}
