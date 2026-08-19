<?php

namespace App\Mail;

use App\Models\Sale;
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
        return new Envelope(
            subject: "Your order {$this->sale->invoice_number} from {$this->sellerName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-status-link',
            with: [
                'invoiceNumber' => $this->sale->invoice_number,
                'sellerName' => $this->sellerName,
                'total' => number_format((float) $this->sale->total, 2),
                'due' => number_format((float) $this->sale->due_amount, 2),
                'hasDue' => (float) $this->sale->due_amount > 0,
                'url' => $this->url,
            ],
        );
    }
}
