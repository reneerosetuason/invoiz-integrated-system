<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RiderApplicationDocument extends Model
{
    public const TYPES = [
        'valid_id' => 'Valid ID',
        'drivers_license' => "Driver's License",
        'proof_of_address' => 'Proof of Address',
        'vehicle_registration' => 'Vehicle Registration',
        'deed_of_sale' => 'Deed of Sale',
        'sales_invoice' => 'Sales Invoice',
        'owner_valid_id' => 'Owner Valid ID',
        'authorization_letter' => 'Authorization Letter',
        'encumbrance_certificate' => 'Certificate of Encumbrance',
        'other' => 'Other Supporting Document',
    ];

    protected $fillable = [
        'rider_application_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(RiderApplication::class, 'rider_application_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? Str::of($this->document_type)->replace('_', ' ')->title();
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Absolute path of the stored file. New uploads live on the private
     * local disk; legacy uploads live under public_path.
     *
     * Paths containing traversal segments are rejected so a tampered database
     * value can never escape the document directory.
     */
    public function absolutePath(): string
    {
        $storedPath = str_replace('\\', '/', ltrim((string) $this->stored_path, '/'));
        $storedPath = preg_replace('#/+#', '/', $storedPath);

        if ($storedPath === '' || str_contains('/' . $storedPath . '/', '/../')) {
            return '';
        }

        if (str_starts_with($storedPath, 'rider-documents/')) {
            return \Illuminate\Support\Facades\Storage::disk('local')->path($storedPath);
        }

        return public_path($storedPath);
    }

    public function fileExists(): bool
    {
        $path = $this->absolutePath();

        return $path !== '' && is_file($path);
    }

    public function fileSizeOnDisk(): int
    {
        if (! $this->fileExists()) {
            return 0;
        }

        return (int) @filesize($this->absolutePath());
    }

    /**
     * MIME type detected from the actual bytes on disk (not the extension or
     * the stored metadata). Null when the file is missing/empty or the MIME
     * extension is unavailable.
     */
    public function detectedMime(): ?string
    {
        $path = $this->absolutePath();

        if ($path === '' || ! is_file($path) || $this->fileSizeOnDisk() === 0) {
            return null;
        }

        if (! class_exists(\Finfo::class)) {
            return null;
        }

        $detected = (new \Finfo(\FILEINFO_MIME_TYPE))->file($path);

        return is_string($detected) && $detected !== '' ? $detected : null;
    }

    /**
     * MIME type to serve. The bytes win over the stored metadata so a file
     * falsely labelled as a PDF is never sent to the browser as one.
     */
    public function contentMime(): string
    {
        $detected = $this->detectedMime();

        if ($detected && ! in_array($detected, ['application/octet-stream', 'application/zip'], true)) {
            return $detected;
        }

        return $this->mime_type ?: ($detected ?: 'application/octet-stream');
    }

    /**
     * A PDF is only previewable when it is a real PDF, not merely a file whose
     * name ends in ".pdf". Checks the header, cross-reference table and EOF
     * marker so truncated/placeholder files are rejected instead of being
     * handed to the browser's PDF viewer.
     */
    public function hasValidPdfStructure(): bool
    {
        $path = $this->absolutePath();

        if ($path === '' || ! is_file($path) || $this->fileSizeOnDisk() < 16) {
            return false;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents) || ! str_starts_with($contents, '%PDF-')) {
            return false;
        }

        return str_contains($contents, 'startxref') && str_contains($contents, '%%EOF');
    }

    public function hasValidImageStructure(): bool
    {
        if (! $this->fileExists() || $this->fileSizeOnDisk() === 0) {
            return false;
        }

        return @getimagesize($this->absolutePath()) !== false;
    }

    /**
     * True when the stored file is present, non-empty and internally
     * consistent with the type we would serve. Missing, truncated or
     * mislabelled files are reported as invalid instead of served broken.
     */
    public function isServable(): bool
    {
        if (! $this->fileExists() || $this->fileSizeOnDisk() === 0) {
            return false;
        }

        $storedMime = (string) $this->mime_type;
        $detected = $this->detectedMime();

        if ($storedMime === 'application/pdf' || $detected === 'application/pdf') {
            return $this->hasValidPdfStructure();
        }

        if ($this->isImage() || ($detected !== null && str_starts_with($detected, 'image/'))) {
            return $this->hasValidImageStructure();
        }

        // Office documents and other supported uploads: a non-empty file with
        // a recognisable MIME type is sufficient; they are served as downloads.
        if ($detected === null) {
            return $storedMime !== '' && $storedMime !== 'application/octet-stream';
        }

        return $detected !== 'application/octet-stream';
    }

    /**
     * Browser preview is only safe/meaningful for PDFs and images.
     */
    public function isBrowserPreviewable(): bool
    {
        return $this->isServable()
            && in_array($this->contentMime(), ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }
}
