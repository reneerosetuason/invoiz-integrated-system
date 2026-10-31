<?php

namespace App\Mail;

use App\Models\LogisticsCenterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Center rejection notice. Carries the admin's reason so the applicant
 * knows what to correct before re-applying. Contains no credentials.
 */
class CenterApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LogisticsCenterApplication $application,
        public readonly string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Logistics Hub Application Not Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'logistics.emails.centers.application-rejected',
            with: [
                'centerName' => $this->application->business_name,
                'ownerName' => $this->application->displayOwnerName(),
                'reason' => $this->reason,
            ],
        );
    }
}
