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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Logistics Web ??? Rider Application review & account provisioning.
 *
 * Admins review mobile/web rider applications and, on approval, provision a
 * working rider login. This is the counterpart to the mobile apply flow and
 * closes the gap where an approved application had no login.
 */
class RiderApplicationAdminController extends Controller
{
    public function __construct(private readonly RiderAccountProvisioner $provisioner) {}

    public function index(Request $request): View
    {
        $this->ensureAdministrator($request->user());

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $status = $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $query = RiderApplication::with(['logisticsCenter', 'serviceArea'])
            ->withCount('supportingDocuments');

        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
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
        $this->ensureAdministrator(request()->user());

        $application->load([
            'logisticsCenter',
            'serviceArea',
            'approver',
            'supportingDocuments',
            'logs.changer',
        ]);

        return view('logistics.rider-applications.show', [
            'application' => $application,
            'keepsExistingLogin' => User::where('email', $application->email)->first()?->keepsOwnLogin() ?? false,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
            'areas' => ServiceArea::with('logisticsCenter')->where('is_active', true)
                ->whereHas('logisticsCenter', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, RiderApplication $application): RedirectResponse
    {
        $admin = $request->user();
        $this->ensureAdministrator($admin);

        $validated = $request->validate([
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'center_id' => ['bail', 'required', 'integer',
                Rule::exists('logistics_centers', 'id')->where('is_active', true)],
            'service_area_id' => ['bail', 'required', 'integer',
                Rule::exists('service_areas', 'id')
                    ->where('logistics_center_id', $request->integer('center_id'))
                    ->where('is_active', true)],
            'notes' => 'nullable|string|max:1000',
        ], [
            'center_id.exists' => 'Choose an active logistics center.',
            'service_area_id.required' => 'Select an active service area for this center. If none are listed, add a service area first.',
            'service_area_id.exists' => 'Choose an active service area belonging to the selected logistics center.',
        ]);

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
        // Existing identities receive an approval notice with instructions to
        // use their current password, without generating new credentials.
        $keptLogin = ($u = User::where('email', $application->email)->first()) && $u->keepsOwnLogin();
        $mailSent = false;
        try {
            Mail::to($application->email)->send(
                new RiderAccountApprovedMail($application->fresh(), $keptLogin ? null : $password)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($keptLogin) {
            $success = $mailSent
                ? "Rider approved. An approval email has been sent to {$rider->email}; use the existing INVOIZ password to sign in."
                : 'Rider approved, but the approval email could not be sent. The existing password still works. Use Resend Approval Email to retry.';
        } else {
            $success = $mailSent
                ? "Rider approved successfully. Login credentials have been sent to: {$rider->email}"
                : 'Rider account created, but the credentials email could not be sent. Use Resend Login Credentials below to retry.';
        }

        return redirect()
            ->route('rider-applications.show', $application)
            ->with($mailSent ? 'success' : 'warning', $success);
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

        $user = User::with('rider')->where('email', $application->email)->first();

        abort_unless($user instanceof User && $user->rider?->email === $application->email,
            422, 'No provisioned rider account exists for this application.');

        $keptLogin = $user->keepsOwnLogin();
        $previousPassword = $user->password;
        $password = $keptLogin ? null : Str::random(12);
        if (! $keptLogin) {
            $user->update(['password' => Hash::make($password)]);
        }

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
            if (! $keptLogin) {
                // A failed retry must not invalidate the rider's working login.
                // Avoid overwriting any password changed concurrently.
                User::whereKey($user->id)->where('password', $user->password)
                    ->update(['password' => $previousPassword]);
            }
            report($e);
        }

        $message = $mailSent
            ? ($keptLogin ? 'Approval email sent. The existing INVOIZ password is unchanged.'
                : "New login credentials have been generated and sent to: {$application->email}")
            : 'The email could not be sent. The previous password is unchanged. Please retry when the mail service is available.';

        return redirect()
            ->route('rider-applications.show', $application)
            ->with($mailSent ? 'success' : 'warning', $message);
    }

    public function reject(Request $request, RiderApplication $application): RedirectResponse
    {
        $this->ensureAdministrator($request->user());

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:300',
        ]);

        $admin = $request->user();

        $this->ensureAdministrator($admin);

        $this->provisioner->reject($application, $admin, $validated['reason']);

        $mailSent = false;
        try {
            Mail::to($application->email)->send(
                new RiderApplicationRejectedMail($application->fresh(), $validated['reason'])
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('rider-applications.show', $application)
            ->with($mailSent ? 'success' : 'warning', $mailSent
                ? 'Application rejected. The applicant has been emailed the reason.'
                : 'Application rejected, but the email could not be sent. Use Resend Rejection Email below to retry.');
    }

    public function resendRejection(Request $request, RiderApplication $application): RedirectResponse
    {
        $this->ensureAdministrator($request->user());
        abort_unless($application->status === 'rejected' && filled($application->notes),
            422, 'Only rejected applications with a recorded reason can receive a rejection email.');

        $mailSent = false;
        try {
            Mail::to($application->email)->send(new RiderApplicationRejectedMail($application, $application->notes));
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('rider-applications.show', $application)
            ->with($mailSent ? 'success' : 'warning', $mailSent
                ? 'Rejection email sent with the recorded reason.'
                : 'The rejection email could not be sent. The decision is saved; please retry when the mail service is available.');
    }

    /**
     * Preview an uploaded application document in the browser.
     */
    public function viewDocument(RiderApplicationDocument $document): BinaryFileResponse
    {
        return $this->serveDocument($document, 'inline');
    }

    /**
     * Download an uploaded application document.
     */
    public function downloadDocument(RiderApplicationDocument $document): BinaryFileResponse
    {
        return $this->serveDocument($document, 'attachment');
    }

    /**
     * Application documents are private: they live on the `local` (private)
     * disk and are served only to administrators through the admin-gated
     * review routes.
     */
    private function serveDocument(RiderApplicationDocument $document, string $disposition): BinaryFileResponse
    {
        $user = request()->user();
        $this->ensureAdministrator($user);

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
                    ? ResponseHeaderBag::DISPOSITION_INLINE
                    : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            );
    }

    private function ensureAdministrator(?User $user): void
    {
        if (! $user) {
            abort(401);
        }

        if (! $user->canManageRiders() || ! $user->isLogisticsAdmin()) {
            abort(403, 'This action is restricted to logistics administrators.');
        }
    }
}
