<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function __construct(public ?string $otp = null)
    {
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $name = $notifiable->first_name ?: ($notifiable->name ?: 'there');
        $code = $this->otp ?: $notifiable->otp;

        $mail = (new MailMessage)
            ->subject('Verify Your Email Address — INVOIZ')
            ->greeting("Hello {$name}!")
            ->line('Thank you for creating an account with INVOIZ. Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 60 minutes.');

        if (! empty($code)) {
            $mail->line("Alternatively, you may enter this 6-digit verification code: {$code}");
        }

        $mail->line('If you did not create an account, no further action is required.')
            ->salutation("Best regards,\nThe INVOIZ Team");

        return $mail;
    }
}
