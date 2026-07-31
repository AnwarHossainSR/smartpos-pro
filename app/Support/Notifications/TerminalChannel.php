<?php

namespace App\Support\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Local-dev stand-in for the "mail" channel: no SMTP setup needed to click a
 * verification/reset link while developing. Prints the notification's mail
 * content straight to the terminal instead of sending a real email.
 */
class TerminalChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        /** @var MailMessage $message */
        $message = $notification->toMail($notifiable);

        $lines = array_map(fn ($line) => is_string($line) ? $line : $line->toHtml(), $message->introLines);

        Log::channel('stderr')->info(sprintf(
            "\n[LOCAL EMAIL] to=%s subject=\"%s\"\n%s\n%s\n",
            $notifiable->routeNotificationFor('mail', $notification) ?? $notifiable->email ?? 'unknown',
            $message->subject,
            implode("\n", $lines),
            $message->actionUrl ? "-> {$message->actionText}: {$message->actionUrl}" : '',
        ));
    }
}
