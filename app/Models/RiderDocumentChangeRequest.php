<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rider's proposed replacement for an already-approved document.
 *
 * The uploaded file is private and lives beside application documents under
 * `rider-documents/`; it is never treated as official until an authorized
 * Logistics Admin approves it. Approval updates the existing
 * `rider_application_documents` row in place and records the previous file on
 * this row, so the official record stays single-sourced while history remains
 * complete.
 */
class RiderDocumentChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'rider_id',
        'rider_application_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'proposed_expiry',
        'reason',
        'status',
        'previous_document_id',
        'previous_stored_path',
        'previous_original_filename',
        'previous_mime_type',
        'previous_file_size',
        'previous_expiry',
        'approved_document_id',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'proposed_expiry' => 'date:Y-m-d',
            'previous_expiry' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'file_size' => 'integer',
            'previous_file_size' => 'integer',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RiderApplication::class, 'rider_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function previousDocument(): BelongsTo
    {
        return $this->belongsTo(RiderApplicationDocument::class, 'previous_document_id');
    }

    public function approvedDocument(): BelongsTo
    {
        return $this->belongsTo(RiderApplicationDocument::class, 'approved_document_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function typeLabel(): string
    {
        return $this->documentModel()->typeLabel();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Pending Approval',
        };
    }

    /**
     * Transient document carrying this request's proposed file metadata, so
     * the existing storage/preview validation on RiderApplicationDocument is
     * reused instead of duplicated for change-request files.
     */
    public function documentModel(): RiderApplicationDocument
    {
        return $this->documentModelForPath(
            (string) $this->stored_path,
            $this->original_filename,
            $this->mime_type,
            (int) $this->file_size,
            $this->document_type,
        );
    }

    public function previousDocumentModel(): ?RiderApplicationDocument
    {
        if (! filled($this->previous_stored_path)) {
            return null;
        }

        return $this->documentModelForPath(
            (string) $this->previous_stored_path,
            $this->previous_original_filename,
            $this->previous_mime_type,
            (int) $this->previous_file_size,
            $this->document_type,
        );
    }

    private function documentModelForPath(
        string $storedPath,
        ?string $filename,
        ?string $mime,
        int $size,
        ?string $type,
    ): RiderApplicationDocument {
        return new RiderApplicationDocument([
            'document_type' => $type,
            'original_filename' => $filename ?: basename($storedPath),
            'stored_path' => $storedPath,
            'mime_type' => $mime,
            'file_size' => $size,
        ]);
    }

    public function absolutePath(): string
    {
        return $this->documentModel()->absolutePath();
    }

    public function fileExists(): bool
    {
        return $this->documentModel()->fileExists();
    }

    public function fileSizeOnDisk(): int
    {
        return $this->documentModel()->fileSizeOnDisk();
    }

    public function contentMime(): string
    {
        return $this->documentModel()->contentMime();
    }

    public function isServable(): bool
    {
        return $this->documentModel()->isServable();
    }

    public function isBrowserPreviewable(): bool
    {
        return $this->documentModel()->isBrowserPreviewable();
    }

    public function humanSize(): string
    {
        return $this->documentModel()->humanSize();
    }

    public function isImage(): bool
    {
        return $this->documentModel()->isImage();
    }
}
