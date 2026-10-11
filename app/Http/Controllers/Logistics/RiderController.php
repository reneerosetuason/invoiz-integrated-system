<?php

namespace App\Http\Controllers\Logistics;

use App\Mail\RiderCredentialsResetMail;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RiderController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $serviceAreaId = (int) $request->query('service_area_id', 0);
        $centerId = (int) $request->query('center_id', 0);

        $query = Rider::query()->with(['logisticsCenter', 'serviceArea']);

        $user = Auth::user();

        // Staff are always restricted to their assigned logistics center. This is
        // enforced at the query level so a query parameter can never bypass it.
        if ($user->isStaff() && $user->center_id) {
            $query->where('center_id', $user->center_id);
        } elseif ($centerId > 0) {
            // Only admins (or staff without a center) may pick an arbitrary center.
            $query->where('center_id', $centerId);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('phone', 'like', $like)
                  ->orWhere('vehicle_type', 'like', $like)
                  ->orWhere('license_plate', 'like', $like);
            });
        }

        if ($status !== '' && in_array($status, ['available', 'delivering', 'inactive'])) {
            $query->where('status', $status);
        }

        // Online/offline availability filter (dashboard "Online Riders" link).
        $online = trim((string) $request->query('online', ''));
        if ($online === 'online') {
            $query->where('is_online', true);
        } elseif ($online === 'offline') {
            $query->where('is_online', false);
        }

        if ($serviceAreaId > 0) {
            $query->where('service_area_id', $serviceAreaId);
        }

        $riders = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('logistics.riders.index', [
            'riders' => $riders,
            'filterServiceAreas' => ServiceArea::orderBy('name')->get(['id', 'name']),
            'filterCenters' => LogisticsCenter::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Rider $rider): View
    {
        $rider->load(['logisticsCenter', 'serviceArea']);
        $rider->load(['deliveries' => function ($query) {
            $query->notArchived()->latest()->take(10);
        }]);

        $currentDelivery = $rider->activeDeliveries()->with('rider')->first();

        return view('logistics.riders.show', [
            'rider' => $rider,
            'currentDelivery' => $currentDelivery,
        ]);
    }

    public function activate(Rider $rider): RedirectResponse
    {
        $this->authorizeRiderCenter($rider);

        if ($rider->status !== 'inactive') {
            return back()->with('error', 'Rider is already active.');
        }

        $hasActiveDelivery = $rider->deliveries()
            ->whereIn('status', Delivery::ACTIVE_STATUSES)
            ->exists();

        $rider->update(['status' => $hasActiveDelivery ? 'delivering' : 'available']);

        Notification::create([
            'type' => 'rider_status_changed',
            'title' => 'Rider Activated',
            'message' => "Rider {$rider->name} is now {$rider->fresh()->status}.",
            'icon' => '????',
            'priority' => 'normal',
            'link' => route('riders.show', $rider),
        ]);

        return back()->with('success', "Rider {$rider->name} activated and eligible for delivery assignment.");
    }

    public function deactivate(Rider $rider): RedirectResponse
    {
        $this->authorizeRiderCenter($rider);

        if ($rider->status === 'inactive') {
            return back()->with('error', 'Rider is already inactive.');
        }

        $rider->update(['status' => 'inactive']);

        Notification::create([
            'type' => 'rider_status_changed',
            'title' => 'Rider Deactivated',
            'message' => "Rider {$rider->name} is now inactive.",
            'icon' => '????',
            'priority' => 'normal',
            'link' => route('riders.show', $rider),
        ]);

        return back()->with('success', "Rider {$rider->name} deactivated. Existing deliveries have been preserved.");
    }

    /**
     * Generate a NEW login credential for the rider (credential reset).
     *
     * Same provisioning concept as rider-application approval: a brand-new
     * secure temporary password is generated, stored on the rider's users
     * row as a hash (never plaintext, never logged, never exposed via the
     * API or UI), and mailed to the rider's registered email. The previous
     * password is rotated out immediately and cannot be recovered. Rider
     * status and assignments are left untouched.
     */
    public function resetCredentials(Request $request, Rider $rider): RedirectResponse
    {
        $this->authorizeRiderCenter($rider);

        $user = User::where('email', $rider->email)->where('role', 'rider')->first();

        abort_unless($user instanceof User, 422, 'No rider login account exists for this rider.');

        $password = Str::random(12);

        $user->update(['password' => Hash::make($password)]);

        // The plaintext password lives only in memory for this single
        // mailable and is discarded afterwards. If delivery fails the
        // rider account is kept with the new hash in place.
        $mailSent = false;
        try {
            Mail::to($rider->email)->send(
                new RiderCredentialsResetMail($rider->fresh(), $password)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $mailSent
            ? "New login credentials have been generated and sent to: {$rider->email}"
            : "New login credentials could not be emailed to {$rider->email}. Please contact the rider through a secure channel.";

        return back()->with('success', $message);
    }

    /**
     * Staff may only manage riders from their assigned logistics center.
     * Admins (and staff without an assigned center) are unrestricted.
     */
    private function authorizeRiderCenter(Rider $rider): void
    {
        $user = Auth::user();

        if ($user->isStaff() && $user->center_id && $rider->center_id != $user->center_id) {
            abort(403, 'You can only manage riders from your assigned logistics center.');
        }
    }
}




