<?php

namespace App\Notifications;

use App\Support\Notifications\TerminalChannel;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;

class VerifyEmail extends BaseVerifyEmail
{
    public function via(mixed $notifiable): array
    {
        return [app()->environment('local') ? TerminalChannel::class : 'mail'];
    }
}
