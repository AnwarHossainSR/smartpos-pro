<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to an invited team member instead of the Str::random(16) password that
 * used to be generated and never delivered anywhere. Reuses the password-reset
 * token mechanism (same table, same 60-minute expiry) — "set your password"
 * and "reset your password" are the same operation from the user's side.
 */
class SetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Set up your SmartPOS account')
            ->greeting('Welcome to SmartPOS!')
            ->line('An account has been created for you. Set a password to get started.')
            ->action('Set your password', $url)
            ->line('This link expires in 60 minutes.');
    }
}
