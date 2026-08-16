<?php

namespace App\Notifications;

use App\Support\Notifications\TerminalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmail extends Notification
{
    use Queueable;

    public function __construct(protected string $code) {}

    public function via(object $notifiable): array
    {
        return [app()->environment('local') ? TerminalChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email address')
            ->line('Your verification code is:')
            ->line("**{$this->code}**")
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
