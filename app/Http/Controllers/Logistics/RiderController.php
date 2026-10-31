<?php

namespace App\Http\Controllers\Logistics;

use App\Mail\RiderCredentialsResetMail;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\RiderNotification;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $today = now()->toDateString();

        // Workload counters for the directory table (LOG-RIDER-006). Both are
        // eager sub-counts so the list stays a single query with no N+1:
        // - active_jobs: current active deliveries (0..MAX_ACTIVE_DELIVERIES)
        // - completed_today: delivered today, by the delivery completion date
        $query = Rider::query()
            ->with(['logisticsCenter', 'serviceArea'])
            ->withCount(['activeDeliveries as active_jobs'])
            ->withCount(['deliveries as completed_today' => function ($q) use ($today) {
                $q->where('status', 'delivered')
                  ->whereDate('delivered_at', $today);
            }]);

        $user = Auth::user();
        $isCenterScoped = $user->isLogisticsStaff()
            && ! $user->isLogisticsAdmin()
            && ! $user->isAdmin();
        $staffCenterId = $isCenterScoped ? (int) ($user->center_id ?? 0) : null;

        // Staff are always restricted to their assigned logistics center. This is
        // enforced at the query level so a query parameter can never bypass it.
        if ($isCenterScoped) {
            if ($staffCenterId === 0) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('center_id', $staffCenterId);
            }
        } elseif ($centerId > 0) {
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

        // Company isolation: staff only see their own center's service areas.
        $serviceAreaOptions = ServiceArea::query();
        $centerOptions = LogisticsCenter::query();

        if ($isCenterScoped) {
            if ($staffCenterId === 0) {
                $serviceAreaOptions->whereRaw('1 = 0');
                $centerOptions->whereRaw('1 = 0');
            } else {
                $serviceAreaOptions->where('logistics_center_id', $staffCenterId);
                $centerOptions->whereKey($staffCenterId);
            }
        }

        return view('logistics.riders.index', [
            'riders' => $riders,
            'filterServiceAreas' => $serviceAreaOptions->orderBy('name')->get(['id', 'name']),
            'filterCenters' => $centerOptions->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Rider $rider): View
    {
        $this->authorizeRiderCenter($rider);

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
        $this->ensureCanManageRiders();
        $this->authorizeRiderCenter($rider);

        if ($rider->status !== 'inactive') {
            return back()->with('error', 'Rider is already active.');
        }

        // Reactivation is the re-verification gate: an expired licence or an
        // unverified vehicle must be renewed through Update Verification
        // before the rider can take work again.
        if ($rider->licenseExpired()) {
            return back()->with('error', 'This rider has an expired driver\'s license. Update and re-verify the licence details before activating.');
        }

        if ($rider->vehicle_verification !== 'verified') {
            return back()->with('error', 'This rider\'s vehicle is not verified. Complete verification before activating.');
        }

        $hasActiveDelivery = $rider->deliveries()
            ->whereIn('status', Delivery::ACTIVE_STATUSES)
            ->exists();

        $rider->update(['status' => $hasActiveDelivery ? 'delivering' : 'available']);

        Notification::create([
            'type' => 'rider_status_changed',
            'title' => 'Rider Activated',
            'message' => "Rider {$rider->name} is now {$rider->fresh()->status}.",
            'icon' => '✅',
            'priority' => 'normal',
            'center_id' => $rider->center_id,
            'audience' => $rider->center_id ? 'center' : 'admin',
            'link' => route('riders.show', $rider),
        ]);

        return back()->with('success', "Rider {$rider->name} activated and eligible for delivery assignment.");
    }

    /**
     * Record renewed licence / vehicle details and mark the rider verified
     * again. Required to clear an expired-licence block before the rider can
     * be reactivated or assigned new deliveries.
     */
    public function reverify(Request $request, Rider $rider): RedirectResponse
    {
        $this->ensureCanManageRiders();
        $this->authorizeRiderCenter($rider);

        $validated = $request->validate([
            'license_number' => 'required|string|max:100',
            'license_expiry' => 'required|date|after_or_equal:today',
            'vehicle_registration' => 'nullable|string|max:100',
            'verification_note' => 'nullable|string|max:500',
        ]);

        $rider->update([
            'license_number' => $validated['license_number'],
            'license_expiry' => $validated['license_expiry'],
            'vehicle_registration' => $validated['vehicle_registration'] ?? $rider->vehicle_registration,
            'is_verified' => true,
            'vehicle_verification' => 'verified',
            'vehicle_verification_note' => $validated['verification_note'] ?? null,
            'vehicle_verified_at' => now(),
            'vehicle_verified_by' => Auth::id(),
        ]);

        Notification::create([
            'type' => 'rider_status_changed',
            'title' => 'Rider Re-verified',
            'message' => "Rider {$rider->name}'s licence and vehicle were re-verified.",
            'icon' => '✅',
            'priority' => 'normal',
            'center_id' => $rider->center_id,
            'audience' => $rider->center_id ? 'center' : 'admin',
            'link' => route('riders.show', $rider),
        ]);

        return back()->with('success', "Rider {$rider->name} re-verified. Their licence expires on {$rider->fresh()->license_expiry->format('M d, Y')}.");
    }

    public function deactivate(Request $request, Rider $rider, AuditLogger $audit): RedirectResponse
    {
        $this->ensureCanManageRiders();
        $this->authorizeRiderCenter($rider);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $reason = trim($validated['reason']);

        $result = DB::transaction(function () use ($rider, $reason, $audit): array {
            $lockedRider = Rider::query()
                ->whereKey($rider->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRider->status === 'inactive') {
                return ['already_inactive' => true];
            }

            $prePickupStatuses = ['assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop'];

            $activeDeliveries = Delivery::query()
                ->where('rider_id', $lockedRider->id)
                ->whereIn('status', Delivery::ACTIVE_STATUSES)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $prePickupDeliveries = $activeDeliveries
                ->whereIn('status', $prePickupStatuses)
                ->values();

            $oldValues = [
                'status' => $lockedRider->status,
                'is_online' => (bool) $lockedRider->is_online,
                'active_delivery_ids' => $activeDeliveries->pluck('id')->all(),
            ];

            $lockedRider->update([
                'status' => 'inactive',
                'is_online' => false,
            ]);

            foreach ($prePickupDeliveries as $delivery) {
                $delivery->update([
                    'rider_id' => null,
                    'status' => 'waiting_for_rider',
                    'assigned_at' => null,
                    'accepted_at' => null,
                ]);

                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'waiting_for_rider',
                    'notes' => "Unassigned from suspended rider {$lockedRider->name}. Reason: {$reason}",
                    'changed_by' => Auth::id(),
                ]);
            }

            $newValues = [
                'status' => 'inactive',
                'is_online' => false,
                'unassigned_delivery_ids' => $prePickupDeliveries->pluck('id')->all(),
                'preserved_delivery_ids' => $activeDeliveries
                    ->whereNotIn('status', $prePickupStatuses)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ];

            Notification::create([
                'type' => 'rider_status_changed',
                'title' => 'Rider Suspended',
                'message' => "Rider {$lockedRider->name} was suspended. Reason: {$reason}",
                'icon' => '⛔',
                'priority' => 'high',
                'center_id' => $lockedRider->center_id,
                'audience' => $lockedRider->center_id ? 'center' : 'admin',
                'link' => route('riders.show', $lockedRider),
            ]);

            RiderNotification::notifyOnce(
                $lockedRider->id,
                'system',
                ['reason' => $reason],
                'Account suspended',
                "Your rider account has been suspended. Reason: {$reason}",
            );

            $audit->record(
                Auth::user(),
                'rider.suspended',
                Rider::class,
                $lockedRider->id,
                $oldValues,
                $newValues,
                $reason,
            );

            return [
                'rider' => $lockedRider,
                'unassigned_count' => $prePickupDeliveries->count(),
            ];
        });

        if (! empty($result['already_inactive'])) {
            return back()->with('error', 'Rider is already inactive.');
        }

        $message = $result['unassigned_count'] > 0
            ? "Rider {$rider->name} suspended. {$result['unassigned_count']} pre-pickup delivery assignment(s) were released; picked-up deliveries remain assigned."
            : "Rider {$rider->name} suspended. Picked-up deliveries, if any, remain assigned.";

        return back()->with('success', $message);
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
        $this->ensureCanManageRiders();
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
     */
    private function ensureCanManageRiders(): void
    {
        abort_unless(Auth::user()?->canManageRiders(), 403,
            'Rider management is restricted to authorized logistics personnel.');
    }

    private function authorizeRiderCenter(Rider $rider): void
    {
        $user = Auth::user();

        if ($user->isAdmin() || $user->isLogisticsAdmin()) {
            return;
        }

        if (
            $user->isLogisticsStaff()
            && $user->center_id
            && $rider->center_id
            && (int) $rider->center_id === (int) $user->center_id
        ) {
            return;
        }

        abort(403, 'You can only manage riders from your assigned logistics center.');
    }
}



