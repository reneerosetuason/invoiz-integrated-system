<?php

namespace App\Models;

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
        return $this->belongsTo(Message::class);
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
        if (str_starts_with($this->stored_path, 'message-attachments/')) {
            // Attachments may live on the 'local' (private) or 'public' disk
            // depending on which client uploaded them (web vs Rider App API).
            $disk = in_array($this->disk, ['local', 'public']) ? $this->disk : 'local';
            return \Illuminate\Support\Facades\Storage::disk($disk)->path($this->stored_path);
        }

        return public_path($this->stored_path);
    }

    public function fileExists(): bool
    {
        return is_file($this->absolutePath());
    }

    /**
     * MIME type to serve. Trusts the stored value unless it is missing or the
     * generic octet-stream fallback; in those cases the actual file contents
     * are sniffed so browsers can preview images/PDFs inline instead of
     * silently downloading them.
     */
    public function contentMime(): string
    {
        if ($this->mime_type && $this->mime_type !== 'application/octet-stream') {
            return $this->mime_type;
        }

        $path = $this->absolutePath();
        if (is_file($path) && class_exists(\Finfo::class)) {
            $detected = (new \Finfo(\FILEINFO_MIME_TYPE))->file($path);
            if ($detected && $detected !== 'application/octet-stream') {
                return $detected;
            }
        }

        return $this->mime_type ?: 'application/octet-stream';
    }
}
