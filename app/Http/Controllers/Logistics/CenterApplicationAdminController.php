<?php

namespace App\Http\Controllers\Logistics;

use App\Mail\CenterApplicationApprovedMail;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCenterApplicationDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Logistics Web ??? Logistics Center application review & center provisioning.
 * The admin-side counterpart to the mobile center apply flow: on approval a
 * LogisticsCenter is created and a staff login linked to it is provisioned.
 */
class CenterApplicationAdminController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $status = $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $query = LogisticsCenterApplication::withCount('supportingDocuments');

        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('business_name', 'like', $like)
                  ->orWhere('owner_name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('phone', 'like', $like);
            });
        }

        $applications = $query->latest('id')->paginate($perPage)->withQueryString();

        return view('logistics.center-applications.index', [
            'applications' => $applications,
            'status' => $status,
        ]);
    }

    public function show(LogisticsCenterApplication $application): View
    {
        $application->load(['approver', 'supportingDocuments']);

        return view('logistics.center-applications.show', [
            'application' => $application,
        ]);
    }

    /**
     * Approve: create the LogisticsCenter and a staff login for it, then
     * email the credentials. The plaintext password lives only in memory.
     */
    public function approve(Request $request, LogisticsCenterApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = $request->user();
        $this->ensureAdministrator($admin);

        abort_unless($application->status === 'pending', 422, 'Only pending applications can be approved.');

        $password = $validated['password'] ?? Str::random(12);

        $center = LogisticsCenter::create([
            'name' => $application->business_name,
            'address' => $application->composedAddress(),
            'city' => $application->municipality ?: '',
            'province' => $application->province,
            'phone' => $application->phone,
            'is_active' => true,
        ]);

        $nameParts = array_values(array_filter(array_map('trim', explode(' ', $application->owner_name))));
        $first = $nameParts[0] ?? $application->owner_name;
        $last = $nameParts[1] ?? $application->owner_name;

        $user = User::where('email', $application->email)->first();
        if (! $user) {
            $user = User::create([
                'name' => $application->owner_name,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $application->email,
                'phone' => $application->phone,
                'birthday' => '1970-01-01',
                'age' => 0,
                'password' => Hash::make($password),
                'role' => 'logistics_staff',
                'status' => 'active',
                'center_id' => $center->id,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'role' => 'logistics_staff',
                'status' => 'active',
                'center_id' => $center->id,
                'password' => Hash::make($password),
            ]);
        }

        $application->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'approved_by' => $admin->id,
            'provisioned_at' => now(),
            'notes' => $validated['notes'] ?? $application->notes,
        ]);

        Notification::create([
            'type' => 'center_approved',
            'title' => 'Logistics Center Approved',
            'message' => "{$application->business_name} was approved and a staff account was provisioned.",
            'icon' => '????',
            'priority' => 'high',
            'link' => null,
        ]);

        $mailSent = false;
        try {
            Mail::to($application->email)->send(
                new CenterApplicationApprovedMail($application->fresh(), $password)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $success = $mailSent
            ? "Center approved successfully. Login credentials have been sent to: {$application->email}"
            : "Center account created successfully, but the login credentials could not be emailed to {$application->email}. Please contact the center through a secure channel.";

        return redirect()
            ->route('center-applications.show', $application)
            ->with('success', $success);
    }

    public function resendCredentials(Request $request, LogisticsCenterApplication $application): RedirectResponse
    {
        $this->ensureAdministrator($request->user());

        abort_unless(
            $application->status === 'approved' && $application->provisioned_at !== null,
            422,
            'Only approved and provisioned center accounts can have their credentials resent.'
        );

        $user = User::where('email', $application->email)->whereIn('role', ['staff', 'logistics_staff'])->first();

        abort_unless($user instanceof User, 422, 'No provisioned staff account exists for this application.');

        $password = Str::random(12);
        $user->update(['password' => Hash::make($password)]);

        $mailSent = false;
        try {
            Mail::to($application->email)->send(
                new CenterApplicationApprovedMail($application->fresh(), $password)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $mailSent
            ? "New login credentials have been generated and sent to: {$application->email}"
            : "New login credentials could not be emailed to {$application->email}. Please contact the center through a secure channel.";

        return redirect()
            ->route('center-applications.show', $application)
            ->with('success', $message);
    }

    public function reject(Request $request, LogisticsCenterApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $admin = $request->user();
        $this->ensureAdministrator($admin);

        abort_unless($application->status === 'pending', 422, 'Only pending applications can be rejected.');

        $application->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'approved_by' => $admin->id,
            'notes' => $validated['reason'],
        ]);

        Notification::create([
            'type' => 'center_rejected',
            'title' => 'Center Application Rejected',
            'message' => "{$application->business_name}'s center application was rejected.",
            'icon' => '????',
            'priority' => 'normal',
            'link' => null,
        ]);

        return redirect()
            ->route('center-applications.show', $application)
            ->with('success', 'Application rejected.');
    }

    public function viewDocument(LogisticsCenterApplicationDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveDocument($document, 'inline');
    }

    public function downloadDocument(LogisticsCenterApplicationDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveDocument($document, 'attachment');
    }

    private function serveDocument(LogisticsCenterApplicationDocument $document, string $disposition): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = request()->user();
        if (! $user || ! in_array($user->role, ['admin', 'logistics_admin'], true)) {
            abort(403, 'This action is restricted to administrators.');
        }

        abort_unless($document->fileExists(), 404, 'Document is missing or was deleted.');

        return response()->file($document->absolutePath(), [
            'Content-Type' => $document->contentMime(),
            'Content-Disposition' => $disposition . '; filename="' . basename($document->original_filename) . '"',
        ]);
    }

    private function ensureAdministrator(?User $user): void
    {
        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['admin', 'logistics_admin'], true)) {
            abort(403, 'This action is restricted to administrators.');
        }
    }
}



