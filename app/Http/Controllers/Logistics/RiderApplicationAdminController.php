<?php

namespace App\Http\Controllers\Logistics;

use App\Mail\RiderAccountApprovedMail;
use App\Mail\RiderApplicationRejectedMail;
use App\Models\LogisticsCenter;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\RiderAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Logistics Web ??? Rider Application review & account provisioning.
 *
 * Admins review mobile/web rider applications and, on approval, provision a
 * working rider login. This is the counterpart to the mobile apply flow and
 * closes the gap where an approved application had no login.
 */
class RiderApplicationAdminController extends Controller
{
    public function __construct(private readonly RiderAccountProvisioner $provisioner)
    {
    }

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $status = $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $query = RiderApplication::with(['logisticsCenter', 'serviceArea'])
            ->withCount('supportingDocuments');

        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('phone', 'like', $like);
            });
        }

        $applications = $query->latest('id')->paginate($perPage)->withQueryString();

        return view('logistics.rider-applications.index', [
            'applications' => $applications,
            'status' => $status,
        ]);
    }

    public function show(RiderApplication $application): View
    {
        $application->load([
            'logisticsCenter',
            'serviceArea',
            'approver',
            'supportingDocuments',
            'logs.changer',
        ]);

        return view('logistics.rider-applications.show', [
            'application' => $application,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
            'areas' => ServiceArea::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, RiderApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'center_id' => 'required|exists:logistics_centers,id',
            'service_area_id' => 'required|exists:service_areas,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = $request->user();

        $this->ensureAdministrator($admin);

        // A blank password field means "auto-generate a secure temporary
        // initial password". It is hashed by the provisioner and delivered to
        // the rider by email; it is never stored, logged, or flashed to the
        // manager in plaintext.
        $password = $validated['password'] ?? Str::random(12);

        $rider = $this->provisioner->approve($application, $admin, [
            'password' => $password,
            'center_id' => (int) $validated['center_id'],
            'service_area_id' => (int) $validated['service_area_id'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Email only after successful provisioning, so the rider never
        // receives credentials for an account that was not created. The
        // plaintext password travels in memory to this single send and is
        // discarded afterwards. If delivery fails the account is kept and
        // the manager is told to use a safe alternative channel.
        // Accounts that kept their own login (marketplace/admin) get no
        // credentials mail — their password never changed.
        $keptLogin = ($u = User::where('email', $application->email)->first()) && $u->keepsOwnLogin();
        $mailSent = false;
        if (! $keptLogin) {
            try {
                Mail::to($application->email)->send(
                    new RiderAccountApprovedMail($application->fresh(), $password)
                );
                $mailSent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($keptLogin) {
            $success = "Rider approved successfully. {$rider->email} keeps its existing login and is now linked as a rider.";
        } else {
            $success = $mailSent
                ? "Rider approved successfully. Login credentials have been sent to: {$rider->email}"
                : "Rider account created successfully, but the login credentials could not be emailed to {$rider->email}. Please contact the rider through a secure channel.";
        }

        return redirect()
            ->route('rider-applications.show', $application)
            ->with('success', $success);
    }

    /**
     * Resend the rider's login credentials.
     *
     * For an already approved and provisioned rider only. A brand-new secure
     * temporary password is generated, stored on the rider's users row as a
     * hash (never plaintext, never logged, never in a URL, notification, or
     * the API, or the Manager UI), and mailed to the applicant's registered
     * email. The previous temporary password is rotated out immediately. If
     * delivery fails, the rider account is kept and the manager is told to
     * use a secure alternative channel.
     */
    public function resendCredentials(Request $request, RiderApplication $application): RedirectResponse
    {
        $this->ensureAdministrator($request->user());

        abort_unless(
            $application->status === 'approved' && $application->provisioned_at !== null,
            422,
            'Only approved and provisioned rider accounts can have their credentials resent.'
        );

        $user = User::where('email', $application->email)->where('role', 'rider')->first();

        abort_unless($user instanceof User, 422, 'No provisioned rider account exists for this application.');

        $password = Str::random(12);

        $user->update(['password' => Hash::make($password)]);

        // Identical to the approval send: the plaintext password lives only in
        // memory for this single mailable and is discarded afterwards. If
        // delivery fails the rider account is never deleted.
        $mailSent = false;
        try {
            Mail::to($application->email)->send(
                new RiderAccountApprovedMail($application->fresh(), $password)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $mailSent
            ? "New login credentials have been generated and sent to: {$application->email}"
            : "New login credentials could not be emailed to {$application->email}. Please contact the rider through a secure channel.";

        return redirect()
            ->route('rider-applications.show', $application)
            ->with('success', $message);
    }

    public function reject(Request $request, RiderApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:300',
        ]);

        $admin = $request->user();

        $this->ensureAdministrator($admin);

        $this->provisioner->reject($application, $admin, $validated['reason']);

        $mailSent = false;
        try {
            \Illuminate\Support\Facades\Mail::to($application->email)->send(
                new RiderApplicationRejectedMail($application->fresh(), $validated['reason'])
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('rider-applications.show', $application)
            ->with('success', $mailSent
                ? 'Application rejected. The applicant has been emailed the reason.'
                : 'Application rejected, but the reason email could not be delivered. Please contact the applicant through a secure channel.');
    }

    /**
     * Preview an uploaded application document in the browser.
     */
    public function viewDocument(RiderApplicationDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveDocument($document, 'inline');
    }

    /**
     * Download an uploaded application document.
     */
    public function downloadDocument(RiderApplicationDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveDocument($document, 'attachment');
    }

    /**
     * Application documents are private: they live on the `local` (private)
     * disk and are served only to administrators through the admin-gated
     * review routes.
     */
    private function serveDocument(RiderApplicationDocument $document, string $disposition): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = request()->user();
        if (! $user || (! in_array($user->role, ['admin', 'logistics_admin'], true) && ! $user->isAdmin())) {
            abort(403, 'This action is restricted to administrators.');
        }

        abort_unless($document->fileExists(), 404, 'Document is missing or was deleted.');
        abort_if($document->fileSizeOnDisk() === 0, 422, 'Document file is empty and cannot be opened.');
        abort_unless($document->isServable(), 422, 'Document file is corrupt or its contents do not match the expected file type.');

        // Only genuine PDFs/images are served inline; everything else is a
        // download so the browser never renders an invalid preview.
        $preview = $disposition === 'inline' && $document->isBrowserPreviewable();
        $filename = $document->original_filename ?: basename($document->stored_path);

        return response()
            ->file($document->absolutePath(), [
                'Content-Type' => $document->contentMime(),
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->setContentDisposition(
                $preview
                    ? \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE
                    : \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            );
    }

    private function ensureAdministrator(?User $user): void
    {
        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['admin', 'logistics_admin'], true) && ! $user->isAdmin()) {
            abort(403, 'This action is restricted to administrators.');
        }
    }
}




