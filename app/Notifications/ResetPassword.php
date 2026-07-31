<?php

namespace App\Notifications;

use App\Support\Notifications\TerminalChannel;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

class ResetPassword extends BaseResetPassword
{
    public function via(mixed $notifiable): array
    {
        return [app()->environment('local') ? TerminalChannel::class : 'mail'];
    }
}
