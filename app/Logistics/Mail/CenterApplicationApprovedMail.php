<?php

namespace App\Mail;

use App\Models\LogisticsCenterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Center approval credentials email. Carries the plaintext temporary
 * password in memory ONLY for this single send operation — never persisted,
 * logged, or exposed through any API.
 */
class CenterApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LogisticsCenterApplication $application,
        public readonly string $temporaryPassword,
    ) {
    }

    public static function referenceFor(LogisticsCenterApplication $application): string
    {
        return 'CEN-' . now()->year . '-' . str_pad((string) $application->id, 4, '0', STR_PAD_LEFT);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Logistics Center Approved – Login Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centers.account-approved',
            with: [
                'centerName' => $this->application->business_name,
                'ownerName' => $this->application->owner_name,
                'centerEmail' => $this->application->email,
                'temporaryPassword' => $this->temporaryPassword,
                'referenceNumber' => self::referenceFor($this->application),
            ],
        );
    }
}