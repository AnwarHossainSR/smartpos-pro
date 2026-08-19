<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sentry\SentrySdk;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** Exponential backoff: 30s, 120s, 300s */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $deliveryId,
        public readonly string $webhookId,
    ) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        $webhook = Webhook::find($this->webhookId);

        if (! $delivery || ! $webhook) {
            return;
        }

        $delivery->increment('attempts');

        $payload = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $signature = WebhookService::sign($webhook->secret, $payload);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Delivery' => $delivery->id,
            ])
                ->timeout(30)
                ->withBody($payload, 'application/json')
                ->post($webhook->url);

            $delivery->update([
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => now(),
            ]);
        } catch (\Exception $e) {
            $delivery->update([
                'response_code' => 0,
                'response_body' => $e->getMessage(),
            ]);

            Log::warning('Webhook delivery failed', [
                'webhook_id' => $this->webhookId,
                'delivery_id' => $this->deliveryId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        SentrySdk::captureException($e);
    }
}
