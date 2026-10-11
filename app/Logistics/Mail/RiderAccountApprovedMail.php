<?php

namespace App\Mail;

use App\Models\RiderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rider approval credentials email.
 *
 * Carries the plaintext temporary password in memory ONLY for this single
 * send operation. It is never persisted (no database/logs), never exposed
 * through any API, and discarded with this instance after sending.
 */
class RiderAccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RiderApplication $application,
        public readonly string $temporaryPassword,
    ) {
    }

    /**
     * Application reference in the same RID-YYYY-NNNN format the Rider App
     * derives from the application id.
     */
    public static function referenceFor(RiderApplication $application): string
    {
        return 'RID-' . now()->year . '-' . str_pad((string) $application->id, 4, '0', STR_PAD_LEFT);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Rider Account Approved – Login Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.riders.account-approved',
            with: [
                'riderName' => $this->application->name,
                'riderEmail' => $this->application->email,
                'temporaryPassword' => $this->temporaryPassword,
                'referenceNumber' => self::referenceFor($this->application),
            ],
        );
    }
}
