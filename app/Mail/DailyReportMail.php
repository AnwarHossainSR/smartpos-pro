<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Mail\EmailTemplateRenderer;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DailyReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $owner,
        public readonly mixed $summary,
        public readonly Carbon $date,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rendered',
            with: ['markdown' => $this->rendered()['markdown']],
        );
    }

    /** @return array{subject: string, html: string, markdown: string} */
    private function rendered(): array
    {
        return app(EmailTemplateRenderer::class)->render(EmailTemplateCatalog::DAILY_REPORT, [
            'owner_name' => $this->owner->name,
            'date' => $this->date->toFormattedDayDateString(),
            'sale_count' => (int) ($this->summary?->sale_count ?? 0),
            'revenue' => number_format((float) ($this->summary?->revenue ?? 0), 2),
            'profit' => number_format((float) ($this->summary?->profit ?? 0), 2),
        ], $this->owner->tenant_id);
    }
}
