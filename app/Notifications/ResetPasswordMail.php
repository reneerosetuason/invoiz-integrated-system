<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordMail extends BaseResetPassword
{
    /**
     * Build the reset email pointing at the appropriate reset page.
     * Logistics accounts use the dedicated Logistics Hub reset page,
     * while buyer, seller, and platform admin accounts use the shared reset page.
     */
    public function toMail($notifiable)
    {
        $isLogisticsUser = false;

        if (is_object($notifiable)) {
            $role = (string) ($notifiable->role ?? '');
            if (in_array($role, ['staff', 'logistics_staff', 'logistics_admin', 'rider'], true)) {
                $isLogisticsUser = true;
            } elseif (method_exists($notifiable, 'hasApprovedRiderProfile') && $notifiable->hasApprovedRiderProfile()) {
                $isLogisticsUser = true;
            } elseif (method_exists($notifiable, 'isLogisticsAdmin') && $notifiable->isLogisticsAdmin()) {
                $isLogisticsUser = true;
            } elseif (method_exists($notifiable, 'isCompanyManager') && $notifiable->isCompanyManager()) {
                $isLogisticsUser = true;
            }
        }

        // Also check if requested from logistics route by an account that is not buyer, seller, or platform admin
        if (! $isLogisticsUser && app()->bound('request') && request()) {
            if (request()->is('logistics/*') || request()->routeIs('logistics.*') || request()->routeIs('password.*')) {
                $role = (string) ($notifiable->role ?? '');
                $isMarketplace = in_array($role, ['buyer', 'seller'], true) || (method_exists($notifiable, 'isAdmin') && $notifiable->isAdmin());
                if (! $isMarketplace) {
                    $isLogisticsUser = true;
                }
            }
        }

        if ($isLogisticsUser) {
            $url = url('/logistics/reset-password/' . $this->token . '?email=' . urlencode($notifiable->getEmailForPasswordReset()));

            return (new MailMessage)
                ->subject('Reset your Logistics Hub password')
                ->greeting('Hello!')
                ->line('You requested a password reset for your Logistics Hub account.')
                ->action('Reset Logistics Password', $url)
                ->line('This link expires in 60 minutes. If you did not request this, no action is needed.');
        }

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
