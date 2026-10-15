<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordMail extends BaseResetPassword
{
    /**
     * Build the reset email pointing at the unified reset page.
     * (The default notification would use route('password.reset'),
     * which collides with the logistics auth routes.)
     */
    public function toMail($notifiable)
    {
        $url = url(route('auth.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset your Invoiz password')
            ->greeting('Hello!')
            ->line('You requested a password reset for your Invoiz account.')
            ->action('Reset Password', $url)
            ->line('This link expires in 60 minutes. If you did not request this, no action is needed.');
    }
}
