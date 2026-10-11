<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rider self-service password reset email.
 *
 * Carries the one-time reset token in memory ONLY for this single send.
 * The token is never persisted (only its hash lives in
 * password_reset_tokens), never logged, and becomes useless after first
 * use or after the broker expiry (60 minutes). Contains no password.
 */
class RiderPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $riderName,
        public readonly string $riderEmail,
        public readonly string $resetToken,
        public readonly string $resetDeepLink,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'INVOIZ Rider Account ? Password Reset Request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'logistics.emails.riders.password-reset',
            with: [
                'riderName' => $this->riderName,
                'riderEmail' => $this->riderEmail,
                'resetToken' => $this->resetToken,
                'resetDeepLink' => $this->resetDeepLink,
            ],
        );
    }
}
