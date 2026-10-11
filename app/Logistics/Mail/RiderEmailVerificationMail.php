<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rider application email-verification code.
 *
 * Carries the plaintext OTP in memory ONLY for this single send (it must
 * appear in the email body). It is never persisted, logged, notified, or
 * returned through any API, and is discarded with this instance.
 * Contains no password or account credentials — verification only.
 */
class RiderEmailVerificationMail extends Mailable
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
            subject: 'Verify Your Email – INVOIZ Rider Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.riders.email-verification',
            with: [
                'applicantName' => $this->applicantName,
                'code' => $this->code,
            ],
        );
    }
}
