<?php

namespace App\Mail;

use App\Models\User;
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
        return new Envelope(
            subject: 'Daily Sales Report — '.$this->date->toFormattedDayDateString(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.daily-report',
            with: [
                'ownerName' => $this->owner->name,
                'date' => $this->date->toFormattedDayDateString(),
                'saleCount' => (int) ($this->summary?->sale_count ?? 0),
                'revenue' => number_format((float) ($this->summary?->revenue ?? 0), 2),
                'profit' => number_format((float) ($this->summary?->profit ?? 0), 2),
            ],
        );
    }
}
