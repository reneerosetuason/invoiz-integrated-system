<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionNotice extends Notification
{
    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('INVOIZ account deletion update')
            ->line($this->message)->line('Only an administrator can restore an archived account. Contact INVOIZ Support if you need help.');
    }
}
