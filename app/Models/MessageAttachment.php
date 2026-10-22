<?php

namespace App\Models;

use App\Models\Logistics\Message as LogisticsMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $table = 'logistics_message_attachments';

    protected $fillable = [
        'message_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'disk',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(LogisticsMessage::class, 'message_id');
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

    public function absolutePath(): string
    {
        $storedPath = str_replace('\\', '/', ltrim((string) $this->stored_path, '/'));
        $storedPath = preg_replace('#/+#', '/', $storedPath);

        if ($storedPath === '' || str_contains('/'.$storedPath.'/', '/../')) {
            return '';
        }

        if (str_starts_with($storedPath, 'message-attachments/')) {
            // Attachments may live on the 'local' (private) or 'public' disk
            // depending on which client uploaded them (web vs Rider App API).
            $disk = in_array($this->disk, ['local', 'public']) ? $this->disk : 'local';

            return \Illuminate\Support\Facades\Storage::disk($disk)->path($storedPath);
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
     * MIME type detected from the actual bytes (not the extension or the
     * stored metadata). Null when the file is missing/empty or the MIME
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
     * MIME type to serve. The bytes win over the stored metadata, except for
     * generic container types (zip/octet-stream) where the specific stored
     * value (e.g. an Office document) is more useful.
     */
    public function contentMime(): string
    {
        $detected = $this->detectedMime();

        if ($detected === null) {
            return $this->mime_type ?: 'application/octet-stream';
        }

        if (in_array($detected, ['application/octet-stream', 'application/zip'], true)) {
            return $this->mime_type ?: $detected;
        }

        return $detected;
    }

    /**
     * A PDF is only previewable when it is a real PDF, not merely a file whose
     * name ends in ".pdf". Placeholder/truncated files are rejected instead of
     * being handed to the browser's PDF viewer.
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
     * consistent with the type we would serve. Missing, empty or mislabelled
     * files are reported as invalid instead of served broken.
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

        // Office documents and other supported uploads are served as
        // downloads; a recognisable stored or detected type is sufficient.
        if ($detected === null || $detected === 'application/octet-stream') {
            return $storedMime !== '' && $storedMime !== 'application/octet-stream';
        }

        return true;
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
