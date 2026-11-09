<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\RiderDocumentChangeRequest;
use App\Services\RiderDocumentChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Rider-facing document change requests.
 *
 * Everything is scoped to the authenticated rider; the rider id is never taken
 * from request input. Proposed files are served through short-lived signed
 * URLs generated only inside the owner's own payload.
 */
class RiderDocumentChangeController extends Controller
{
    public function __construct(private readonly RiderDocumentChangeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return response()->json($this->service->overview($rider));
    }

    public function store(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys(\App\Models\RiderApplicationDocument::TYPES))],
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'max:5120',
            ],
            'expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::requiredIf($request->input('document_type') === 'drivers_license'),
            ],
            'reason' => 'nullable|string|max:500',
        ]);

        $changeRequest = $this->service->submit($rider, $validated, $request->file('document'));

        return response()->json([
            'message' => 'Document change request submitted. Your current document stays official until a Logistics Admin approves the replacement.',
            'request' => [
                'id' => $changeRequest->id,
                'document_type' => $changeRequest->document_type,
                'status' => $changeRequest->status,
            ],
        ], 201);
    }

    /**
     * Serve the proposed replacement (`kind=proposed`) or the previously
     * official file (`kind=previous`) for a signed, unexpired URL.
     */
    public function document(Request $request, RiderDocumentChangeRequest $changeRequest, string $kind): BinaryFileResponse
    {
        if (! in_array($kind, ['proposed', 'previous'], true)) {
            abort(404);
        }

        $document = $kind === 'previous'
            ? $changeRequest->previousDocumentModel()
            : $changeRequest->documentModel();

        abort_unless($document !== null, 404, 'Document is missing or was deleted.');
        abort_unless($document->fileExists(), 404, 'Document is missing or was deleted.');
        abort_if($document->fileSizeOnDisk() === 0, 422, 'Document file is empty and cannot be opened.');
        abort_unless($document->isServable(), 422, 'Document file is corrupt or its contents do not match the expected file type.');

        $preview = $document->isBrowserPreviewable();
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

    private function rider(Request $request): Rider
    {
        $rider = $request->user()?->rider;

        abort_unless($rider instanceof Rider, 403, 'No rider profile is linked to this account.');

        return $rider;
    }
}
