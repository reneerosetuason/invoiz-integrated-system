<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Standalone Sorting Center operational hub.
 *
 * Read-only aggregation over the existing parcel pipeline
 * (pending_arrival → received → scanned → sorted → dispatched); every
 * mutation posts to the existing deliveries.* routes, so no workflow
 * logic is duplicated here. The per-delivery workflow on deliveries.show
 * remains the canonical action surface (assignment included).
 *
 * Staff are center-scoped at the query level, mirroring Deliveries.
 */
class SortingCenterController extends Controller
{
    public const TABS = [
        'all', 'incoming', 'received', 'scanned', 'sorted',
        'dispatched', 'returned', 'reattempt',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:255',
            'tab' => 'sometimes|string|in:all,incoming,received,scanned,sorted,dispatched,returned,reattempt',
            'center_id' => 'sometimes|integer',
            'service_area_id' => 'sometimes|integer',
        ]);

        $user = Auth::user();
        $staffCenterId = ($user && $user->isLogisticsStaff() && $user->center_id) ? (int) $user->center_id : null;

        $base = Delivery::notArchived();
        if ($staffCenterId) {
            $base->where('center_id', $staffCenterId);
        }

        $counts = [
            'incoming' => (clone $base)->where('parcel_status', 'pending_arrival')->count(),
            'received' => (clone $base)->where('parcel_status', 'received')->count(),
            'scanned' => (clone $base)->where('parcel_status', 'scanned')->count(),
            'sorted' => (clone $base)->where('parcel_status', 'sorted')->count(),
            'dispatched' => (clone $base)->where('parcel_status', 'dispatched')->count(),
            'assigned' => (clone $base)->where('status', 'assigned')->count(),
            'returned' => (clone $base)->whereNotNull('returned_at')->count(),
            'reattempt' => (clone $base)->whereNotNull('reattempt_requested_at')->count(),
        ];

        $query = Delivery::with(['rider', 'logisticsCenter', 'destinationCenter', 'serviceArea'])
            ->notArchived();
        if ($staffCenterId) {
            $query->where('center_id', $staffCenterId);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('sender_name', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%');
            });
        }

        $tab = $validated['tab'] ?? 'all';
        match ($tab) {
            'incoming' => $query->where('parcel_status', 'pending_arrival'),
            'received' => $query->where('parcel_status', 'received'),
            'scanned' => $query->where('parcel_status', 'scanned'),
            'sorted' => $query->where('parcel_status', 'sorted'),
            'dispatched' => $query->where('parcel_status', 'dispatched'),
            'returned' => $query->whereNotNull('returned_at'),
            'reattempt' => $query->whereNotNull('reattempt_requested_at'),
            default => null,
        };

        // Admins may narrow by center; staff are already center-locked.
        $centerId = (int) ($validated['center_id'] ?? 0);
        if ($centerId > 0 && ! $staffCenterId) {
            $query->where('center_id', $centerId);
        }
        $serviceAreaId = (int) ($validated['service_area_id'] ?? 0);
        if ($serviceAreaId > 0) {
            $query->where('service_area_id', $serviceAreaId);
        }

        return view('logistics.sorting-center.index', [
            'counts' => $counts,
            'deliveries' => $query->latest()->paginate(15)->withQueryString(),
            'tab' => $tab,
            'search' => $search,
            'centerId' => $centerId,
            'serviceAreaId' => $serviceAreaId,
            'centers' => LogisticsCenter::orderBy('name')->get(),
            'serviceAreas' => ServiceArea::orderBy('name')->get(),
            'staffCenterId' => $staffCenterId,
        ]);
    }
}
