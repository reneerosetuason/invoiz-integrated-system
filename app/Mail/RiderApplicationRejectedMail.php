<?php

namespace App\Mail;

use App\Models\RiderApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rider rejection notice. Carries the admin's reason so the applicant
 * knows what to correct before re-applying. Contains no credentials.
 */
class RiderApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RiderApplication $application,
        public readonly string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Rider Application Update',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'logistics.emails.riders.application-rejected',
            with: [
                'riderName' => $this->application->name,
                'reason' => $this->reason,
            ],
        );
    }
}
