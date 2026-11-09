<?php

namespace App\Http\Controllers\Logistics;

use App\Models\RiderDocumentChangeRequest as ChangeRequest;
use App\Models\User;
use App\Services\RiderDocumentChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Logistics Web — review of rider document change requests.
 *
 * Restricted to the designated Logistics Admin account; Logistics Staff never
 * receive review or document-preview access here.
 */
class RiderDocumentChangeController extends Controller
{
    public function __construct(private readonly RiderDocumentChangeService $service) {}

    public function index(Request $request): View
    {
        $this->ensureAdministrator($request->user());

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 15;

        $query = ChangeRequest::with(['rider', 'reviewer']);

        if (in_array($status, ChangeRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->whereHas('rider', function ($rider) use ($like) {
                $rider->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $requests = $query
            ->orderByRaw("status = 'pending' DESC")
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $counts = ChangeRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('logistics.document-change-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, ChangeRequest $changeRequest): View
    {
        $this->ensureAdministrator($request->user());

        $changeRequest->load(['rider.logisticsCenter', 'application', 'reviewer', 'approvedDocument']);

        $rider = $changeRequest->rider;
        $current = $rider
            ? $this->service->currentDocuments($rider)->firstWhere('document_type', $changeRequest->document_type)
            : null;

        $history = ChangeRequest::with('reviewer')
            ->where('rider_id', $changeRequest->rider_id)
            ->where('document_type', $changeRequest->document_type)
            ->whereKeyNot($changeRequest->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('logistics.document-change-requests.show', [
            'changeRequest' => $changeRequest,
            'rider' => $rider,
            'currentDocument' => $current,
            'previousDocument' => $changeRequest->previousDocumentModel(),
            'history' => $history,
            'licenseStatus' => $rider?->licenseStatus(),
            'licenseBlockReason' => $rider?->assignmentBlockReason(),
            'isLicense' => $changeRequest->document_type === 'drivers_license',
        ]);
    }

    public function approve(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->ensureAdministrator($request->user());

        $this->service->approve($changeRequest, $request->user());

        $rider = $changeRequest->fresh()->rider;
        $message = 'Document replacement approved. '
            .($rider?->name ?? 'The rider').'\'s official document'
            .($changeRequest->document_type === 'drivers_license' ? ' and license expiry were updated.' : ' was updated.');

        if ($rider && $rider->licenseExpired()) {
            $message .= ' The rider still has other eligibility checks outstanding.';
        } elseif ($rider && $rider->canGoOnline()) {
            $message .= ' The rider is eligible for new assignments again.';
        }

        return redirect()
            ->route('document-change-requests.show', $changeRequest)
            ->with('success', $message);
    }

    public function reject(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->ensureAdministrator($request->user());

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ], [
            'reason.required' => 'Give the rider a reason for the rejection.',
            'reason.min' => 'Give the rider a clear reason (at least 5 characters).',
        ]);

        $this->service->reject($changeRequest, $request->user(), $validated['reason']);

        return redirect()
            ->route('document-change-requests.show', $changeRequest)
            ->with('success', 'Document change request rejected. The current official document is unchanged and the rider can see your reason.');
    }

    public function viewDocument(Request $request, ChangeRequest $changeRequest, string $kind): BinaryFileResponse
    {
        return $this->serveDocument($request, $changeRequest, $kind, 'inline');
    }

    public function downloadDocument(Request $request, ChangeRequest $changeRequest, string $kind): BinaryFileResponse
    {
        return $this->serveDocument($request, $changeRequest, $kind, 'attachment');
    }

    /**
     * Only the designated Logistics Admin may open proposed or previous files.
     * Responses are private and never cached.
     */
    private function serveDocument(Request $request, ChangeRequest $changeRequest, string $kind, string $disposition): BinaryFileResponse
    {
        $this->ensureAdministrator($request->user());

        $document = match ($kind) {
            'proposed' => $changeRequest->documentModel(),
            'previous' => $changeRequest->previousDocumentModel(),
            default => abort(404),
        };

        abort_unless($document !== null, 404, 'Document is missing or was deleted.');
        abort_unless($document->fileExists(), 404, 'Document is missing or was deleted.');
        abort_if($document->fileSizeOnDisk() === 0, 422, 'Document file is empty and cannot be opened.');
        abort_unless($document->isServable(), 422, 'Document file is corrupt or its contents do not match the expected file type.');

        $preview = $disposition === 'inline' && $document->isBrowserPreviewable();
        $filename = $document->original_filename ?: basename($document->stored_path);

        return response()
            ->file($document->absolutePath(), [
                'Content-Type' => $document->contentMime(),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->setPrivate()
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
