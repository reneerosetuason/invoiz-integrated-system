<?php

namespace App\Mail;

use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rider credential-reset email (Logistics Rider Management).
 *
 * Carries the freshly generated temporary password in memory ONLY for this
 * single send operation. It is never persisted (no database/logs), never
 * exposed through any API, and discarded with this instance after sending.
 * The previous password was already replaced by its hash before this mail
 * is even constructed, so the old credential cannot be recovered.
 */
class RiderCredentialsResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rider $rider,
        public readonly string $temporaryPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Rider Account – New Login Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.riders.credentials-reset',
            with: [
                'riderName' => $this->rider->name,
                'riderEmail' => $this->rider->email,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
