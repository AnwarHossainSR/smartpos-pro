<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ImpersonationStartedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $owner,
        public readonly string $reason,
        public readonly string $adminName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A SmartPOS support admin accessed your account');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.impersonation-started',
            with: [
                'ownerName' => $this->owner->name,
                'reason' => $this->reason,
                'adminName' => $this->adminName,
            ],
        );
    }
}
