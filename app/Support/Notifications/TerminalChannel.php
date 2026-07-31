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
        // Only notifications that actually build a MailMessage can be rendered here.
        // Anything else (a notification routed at this channel by mistake, or one that
        // returns a Mailable) is skipped rather than fatalling the whole send.
        if (! method_exists($notification, 'toMail')) {
            return;
        }

        $message = $notification->toMail($notifiable);

        if (! $message instanceof MailMessage) {
            return;
        }

        $lines = array_map(fn ($line) => is_string($line) ? $line : $line->toHtml(), $message->introLines);

        $text = sprintf(
            "LOCAL EMAIL to=%s subject=\"%s\"\n%s\n%s",
            $notifiable->routeNotificationFor('mail', $notification) ?? $notifiable->email ?? 'unknown',
            $message->subject,
            implode("\n", $lines),
            $message->actionUrl ? "-> {$message->actionText}: {$message->actionUrl}" : '',
        );

        // `php artisan serve` strips any output line starting with "[" (it mistakes it for
        // its own request-log format — see ServeCommand::flushOutputBuffer), which eats both
        // Monolog's bracketed header and a bracketed tag. Write raw to STDERR with no leading
        // "[" to survive that, and mirror to the log file as a durable fallback.
        fwrite(STDERR, "\n{$text}\n\n");
        Log::channel('single')->info($text);
    }
}
