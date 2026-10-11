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
     */
    public function absolutePath(): string
    {
        if (str_starts_with($this->stored_path, 'rider-documents/')) {
            return \Illuminate\Support\Facades\Storage::disk('local')->path($this->stored_path);
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
