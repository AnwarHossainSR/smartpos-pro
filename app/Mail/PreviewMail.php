<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A one-off send of already-rendered template text, so a tenant can see how an
 * edited email looks in a real client before it goes to a customer.
 */
class PreviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $renderedSubject,
        public readonly string $renderedMarkdown,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Preview] '.$this->renderedSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.rendered', with: ['markdown' => $this->renderedMarkdown]);
    }
}
