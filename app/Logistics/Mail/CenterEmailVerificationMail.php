<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Logistics Center application email-verification code.
 *
 * Mirrors RiderEmailVerificationMail: carries the plaintext OTP in memory
 * ONLY for this single send (it must appear in the email body). It is never
 * persisted, logged, notified, or returned through any endpoint, and is
 * discarded with this instance. Contains no password or account
 * credentials — verification only.
 */
class CenterEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $applicantName,
        public readonly string $code,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email — INVOIZ Logistics Center Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centers.email-verification',
            with: [
                'applicantName' => $this->applicantName,
                'code' => $this->code,
            ],
        );
    }
}
