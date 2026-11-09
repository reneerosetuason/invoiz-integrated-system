<?php

namespace App\Services;

use App\Models\LogisticsTruck;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\RiderDocumentChangeRequest;
use App\Models\RiderNotification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rider document change requests.
 *
 * The official document always lives in `rider_application_documents`. A
 * replacement is staged on the request row, and only an authorized Logistics
 * Admin approval promotes it. Riders never write the official record directly.
 */
class RiderDocumentChangeService
{
    /**
     * The application whose documents are currently official for this rider.
     * Mirrors RiderController::documentsPayload() (latest application row for
     * the rider's email), so the review screen and the rider's profile always
     * point at the same record.
     */
    public function officialApplication(Rider $rider): ?RiderApplication
    {
        $email = strtolower(trim((string) $rider->email));

        return RiderApplication::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->latest('id')
            ->first();
    }

    /**
     * Current official documents, one per type (the newest row wins if legacy
     * data contains duplicates).
     *
     * @return Collection<int, RiderApplicationDocument>
     */
    public function currentDocuments(Rider $rider): Collection
    {
        $application = $this->officialApplication($rider);

        if (! $application) {
            return collect();
        }

        return $application->supportingDocuments
            ->groupBy('document_type')
            ->map(fn (Collection $group) => $group->sortByDesc('id')->first())
            ->sortBy('document_type')
            ->values();
    }

    public function pendingFor(Rider $rider, string $documentType): ?RiderDocumentChangeRequest
    {
        return RiderDocumentChangeRequest::query()
            ->where('rider_id', $rider->id)
            ->where('document_type', $documentType)
            ->pending()
            ->latest('id')
            ->first();
    }

    /**
     * Submit a replacement. The proposed file is stored privately and never
     * touches the official document row.
     */
    public function submit(Rider $rider, array $input, UploadedFile $file): RiderDocumentChangeRequest
    {
        $documentType = (string) $input['document_type'];

        if (! array_key_exists($documentType, RiderApplicationDocument::TYPES)) {
            throw ValidationException::withMessages([
                'document_type' => 'Choose a valid document type.',
            ]);
        }

        $application = $this->officialApplication($rider);

        if (! $application) {
            throw ValidationException::withMessages([
                'document_type' => 'No approved document record was found for this rider account.',
            ]);
        }

        $proposedExpiry = $this->normalizeExpiry($documentType, $input['expiry_date'] ?? null);

        $storedPath = null;

        try {
            return DB::transaction(function () use ($rider, $application, $documentType, $proposedExpiry, $input, $file, &$storedPath) {
                // Serialises submissions for this rider so two concurrent taps
                // cannot both create a pending request for the same type.
                Rider::query()->whereKey($rider->id)->lockForUpdate()->first();

                $existing = RiderDocumentChangeRequest::query()
                    ->where('rider_id', $rider->id)
                    ->where('document_type', $documentType)
                    ->pending()
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'document_type' => 'A pending change request already exists for this document. Wait for the Logistics Admin decision before submitting again.',
                    ]);
                }

                $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
                $storedPath = $file->storeAs(
                    "rider-documents/change-requests/{$rider->id}",
                    Str::uuid()->toString().'.'.strtolower($extension),
                );

                if (! $storedPath) {
                    throw ValidationException::withMessages([
                        'document' => 'The document could not be stored. Please try again.',
                    ]);
                }

                $changeRequest = RiderDocumentChangeRequest::create([
                    'rider_id' => $rider->id,
                    'rider_application_id' => $application->id,
                    'document_type' => $documentType,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize() ?: 0,
                    'proposed_expiry' => $proposedExpiry,
                    'reason' => isset($input['reason']) && trim((string) $input['reason']) !== ''
                        ? trim((string) $input['reason'])
                        : null,
                    'status' => RiderDocumentChangeRequest::STATUS_PENDING,
                ]);

                if (! $changeRequest->isServable()) {
                    $changeRequest->delete();
                    Storage::disk('local')->delete($storedPath);
                    $storedPath = null;

                    throw ValidationException::withMessages([
                        'document' => 'The uploaded file could not be verified. Upload a clear photo, PDF, or Word document.',
                    ]);
                }

                Notification::create([
                    'type' => 'rider_document_change_request',
                    'title' => 'Document Change Request',
                    'message' => "{$rider->name} submitted a replacement for ".$changeRequest->typeLabel().'.',
                    'icon' => 'document',
                    'priority' => 'normal',
                    'audience' => 'admin',
                    'link' => route('document-change-requests.show', $changeRequest),
                ]);

                return $changeRequest->fresh();
            });
        } catch (\Throwable $e) {
            // A failed insert/validation must not leave an orphan file.
            if ($storedPath !== null
                && RiderDocumentChangeRequest::where('stored_path', $storedPath)->doesntExist()) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Promote the proposed file to the official record atomically.
     */
    public function approve(RiderDocumentChangeRequest $changeRequest, User $admin): RiderDocumentChangeRequest
    {
        $this->ensureAuthorizedReviewer($admin);

        return DB::transaction(function () use ($changeRequest, $admin) {
            $request = RiderDocumentChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertReviewable($request, $admin);

            $rider = Rider::query()->whereKey($request->rider_id)->lockForUpdate()->firstOrFail();

            $application = RiderApplication::query()
                ->whereKey($request->rider_application_id)
                ->lockForUpdate()
                ->first() ?? $this->officialApplication($rider);

            if (! $application) {
                abort(422, 'The rider\'s application record no longer exists.');
            }

            $document = RiderApplicationDocument::query()
                ->where('rider_application_id', $application->id)
                ->where('document_type', $request->document_type)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $isLicense = $request->document_type === 'drivers_license';
            $previousExpiry = $isLicense ? $rider->license_expiry?->format('Y-m-d') : null;

            // Snapshot before mutating the official row; the old file itself is
            // intentionally left on disk so history stays downloadable.
            $request->forceFill([
                'previous_document_id' => $document?->id,
                'previous_stored_path' => $document?->stored_path,
                'previous_original_filename' => $document?->original_filename,
                'previous_mime_type' => $document?->mime_type,
                'previous_file_size' => $document?->file_size,
                'previous_expiry' => $previousExpiry,
            ]);

            $file = [
                'original_filename' => $request->original_filename,
                'stored_path' => $request->stored_path,
                'mime_type' => $request->mime_type,
                'file_size' => $request->file_size,
            ];

            if ($document) {
                $document->update($file);
            } else {
                $document = RiderApplicationDocument::create(array_merge($file, [
                    'rider_application_id' => $application->id,
                    'document_type' => $request->document_type,
                ]));
            }

            // The fleet record for a Hub Transport Driver's own vehicle keeps
            // pointing at the official registration file. Scoped to the driver
            // linked on this application, inside the same transaction, so a
            // failed approval leaves no mismatched fleet document.
            if ($request->document_type === 'vehicle_registration') {
                $this->syncDriverTruckRegistrationDocument($application, (string) $document->stored_path);
            }

            if ($isLicense && $request->proposed_expiry !== null) {
                $expiry = $request->proposed_expiry->format('Y-m-d');
                $rider->update(['license_expiry' => $expiry]);
                $application->update(['license_expiry' => $expiry]);
            }

            $request->forceFill([
                'status' => RiderDocumentChangeRequest::STATUS_APPROVED,
                'approved_document_id' => $document->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            RiderNotification::create([
                'rider_id' => $rider->id,
                'type' => 'document_change_approved',
                'title' => 'Document Change Approved',
                'body' => 'Your '.$request->typeLabel().' replacement was approved and is now your official document'
                    .($isLicense && $request->proposed_expiry ? ' (expires '.$request->proposed_expiry->format('M d, Y').').' : '.'),
                'data' => [
                    'change_request_id' => $request->id,
                    'document_type' => $request->document_type,
                ],
            ]);

            return $request->fresh();
        });
    }

    /**
     * Reject a request. The official document and expiry are untouched.
     */
    public function reject(RiderDocumentChangeRequest $changeRequest, User $admin, string $reason): RiderDocumentChangeRequest
    {
        $this->ensureAuthorizedReviewer($admin);

        return DB::transaction(function () use ($changeRequest, $admin, $reason) {
            $request = RiderDocumentChangeRequest::query()
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertReviewable($request, $admin);

            $reason = trim($reason);

            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'reason' => 'Give the rider a clear reason (at least 5 characters).',
                ]);
            }

            $request->forceFill([
                'status' => RiderDocumentChangeRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            RiderNotification::create([
                'rider_id' => $request->rider_id,
                'type' => 'document_change_rejected',
                'title' => 'Document Change Rejected',
                'body' => 'Your '.$request->typeLabel().' replacement was rejected. Reason: '.$reason,
                'data' => [
                    'change_request_id' => $request->id,
                    'document_type' => $request->document_type,
                ],
            ]);

            return $request->fresh();
        });
    }

    /**
     * Everything the rider app needs for the My Documents screen.
     */
    public function overview(Rider $rider): array
    {
        $documents = $this->currentDocuments($rider);
        $requests = RiderDocumentChangeRequest::query()
            ->with('reviewer')
            ->where('rider_id', $rider->id)
            ->latest('id')
            ->get();

        $pendingByType = $requests
            ->where('status', RiderDocumentChangeRequest::STATUS_PENDING)
            ->keyBy('document_type');

        $documentTypes = collect(RiderApplicationDocument::TYPES)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'has_current' => $documents->contains('document_type', $value),
                'pending' => $pendingByType->has($value),
            ])
            ->values()
            ->all();

        return [
            'documents' => $documents->map(fn (RiderApplicationDocument $doc) => [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'label' => $doc->typeLabel(),
                'name' => $doc->original_filename,
                'mime_type' => $doc->contentMime(),
                'is_image' => $doc->isImage(),
                'size' => $doc->humanSize(),
                'status' => 'approved',
                'expires_on' => $doc->document_type === 'drivers_license'
                    ? $rider->license_expiry?->format('Y-m-d')
                    : null,
                'url' => URL::temporarySignedRoute(
                    'api.logistics.rider.documents.show',
                    now()->addMinutes(30),
                    ['document' => $doc->id],
                ),
            ])->values()->all(),
            'requests' => $requests->map(function (RiderDocumentChangeRequest $request) use ($rider) {
                $isLicense = $request->document_type === 'drivers_license';
                $previous = $request->previousDocumentModel();

                return [
                    'id' => $request->id,
                    'type' => $request->document_type,
                    'type_label' => $request->typeLabel(),
                    'status' => $request->status,
                    'status_label' => $request->statusLabel(),
                    'reason' => $request->reason,
                    'rejection_reason' => $request->rejection_reason,
                    'proposed_expiry' => $request->proposed_expiry?->format('Y-m-d'),
                    'previous_expiry' => $isLicense
                        ? ($request->previous_expiry?->format('Y-m-d')
                            ?? ($request->isPending() ? $rider->license_expiry?->format('Y-m-d') : null))
                        : null,
                    'submitted_at' => $request->created_at?->toIso8601String(),
                    'reviewed_at' => $request->reviewed_at?->toIso8601String(),
                    'reviewer_name' => $request->reviewer?->name,
                    'can_resubmit' => $request->status === RiderDocumentChangeRequest::STATUS_REJECTED,
                    'document' => [
                        'name' => $request->original_filename,
                        'mime_type' => $request->contentMime(),
                        'is_image' => $request->isImage(),
                        'size' => $request->humanSize(),
                        'exists' => $request->fileExists(),
                        'url' => URL::temporarySignedRoute(
                            'api.logistics.rider.document-change-requests.document',
                            now()->addMinutes(30),
                            ['changeRequest' => $request->id, 'kind' => 'proposed'],
                        ),
                    ],
                    'previous_document' => $previous ? [
                        'name' => $previous->original_filename,
                        'size' => $previous->humanSize(),
                        'exists' => $previous->fileExists(),
                        'url' => URL::temporarySignedRoute(
                            'api.logistics.rider.document-change-requests.document',
                            now()->addMinutes(30),
                            ['changeRequest' => $request->id, 'kind' => 'previous'],
                        ),
                    ] : null,
                ];
            })->values()->all(),
            'document_types' => $documentTypes,
            'license' => [
                'expires_on' => $rider->license_expiry?->format('Y-m-d'),
                'status' => $rider->licenseStatus(),
                'expired' => $rider->licenseExpired(),
                'expiring_soon' => $rider->licenseExpiringSoon(),
                'pending_change' => $pendingByType->has('drivers_license'),
                'can_go_online' => $rider->canGoOnline(),
                'block_reason' => $rider->assignmentBlockReason(),
            ],
        ];
    }

    /**
     * Only a designated Logistics Admin may review. Logistics Staff and
     * marketplace admins are rejected even if they reach the route.
     */
    private function ensureAuthorizedReviewer(User $admin): void
    {
        abort_unless(
            $admin->canManageRiders() && $admin->isLogisticsAdmin(),
            403,
            'Document change requests are reviewed by logistics administrators only.',
        );
    }

    private function assertReviewable(RiderDocumentChangeRequest $request, User $admin): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'This request has already been reviewed.',
            ]);
        }

        // Defence in depth: no account may approve its own rider's request.
        if ($admin->rider && (int) $admin->rider->id === (int) $request->rider_id) {
            abort(403, 'You cannot review your own document change request.');
        }
    }

    /**
     * Point the fleet record of a Hub Transport Driver's own vehicle at the
     * newly promoted registration file.
     *
     * The application carries the provisioned driver link, so only trucks
     * owned by that same driver are eligible. When the driver owns several
     * vehicles and neither the application nor the plate identifies exactly
     * one, nothing changes rather than guessing.
     */
    private function syncDriverTruckRegistrationDocument(RiderApplication $application, string $storedPath): void
    {
        $driverId = (int) $application->hub_transport_driver_id;
        $storedPath = trim($storedPath);

        if ($driverId <= 0 || $storedPath === '') {
            return;
        }

        $candidates = LogisticsTruck::query()
            ->where('hub_transport_driver_id', $driverId)
            ->where('ownership_type', LogisticsTruck::OWNERSHIP_DRIVER)
            ->lockForUpdate()
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $truck = null;
        $applicationTruckId = (int) $application->logistics_truck_id;

        if ($applicationTruckId > 0) {
            $truck = $candidates->first(fn (LogisticsTruck $candidate) => (int) $candidate->id === $applicationTruckId);
        }

        $plate = strtoupper(trim((string) $application->license_plate));

        if (! $truck && $plate !== '') {
            $truck = $candidates->first(
                fn (LogisticsTruck $candidate) => strtoupper(trim((string) $candidate->plate_number)) === $plate
            );
        }

        if (! $truck && $candidates->count() === 1) {
            $truck = $candidates->first();
        }

        if ($truck) {
            $truck->update(['document_path' => $storedPath]);
        }
    }

    /**
     * Driver's-license replacements carry an expiry; all other document types
     * do not, so any stray value is ignored rather than stored.
     */
    private function normalizeExpiry(string $documentType, mixed $value): ?Carbon
    {
        if ($documentType !== 'drivers_license') {
            return null;
        }

        if (! filled($value)) {
            throw ValidationException::withMessages([
                'expiry_date' => 'Enter the new driver\'s license expiration date.',
            ]);
        }

        $expiry = Carbon::parse((string) $value)->startOfDay();

        if ($expiry->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'expiry_date' => 'The replacement license expiration date cannot be in the past.',
            ]);
        }

        return $expiry;
    }
}
