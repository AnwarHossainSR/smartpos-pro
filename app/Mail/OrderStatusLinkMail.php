<?php

namespace App\Mail;

use App\Models\Sale;
use App\Services\Mail\EmailTemplateRenderer;
use App\Support\Mail\EmailTemplateCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly string $sellerName,
        public readonly string $url,
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
        return app(EmailTemplateRenderer::class)->render(EmailTemplateCatalog::ORDER_STATUS_LINK, [
            'invoice_number' => $this->sale->invoice_number,
            'seller_name' => $this->sellerName,
            'total' => number_format((float) $this->sale->total, 2),
            'due' => number_format((float) $this->sale->due_amount, 2),
            'url' => $this->url,
        ], $this->sale->tenant_id);
    }
}
