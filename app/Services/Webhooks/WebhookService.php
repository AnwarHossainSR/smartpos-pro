<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

class WebhookService
{
    public const EVENTS = [
        'sale.completed',
        'sale.returned',
        'customer.created',
        'inventory.low',
    ];

    /**
     * Dispatch all active webhooks subscribed to the given event.
     */
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::query()
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::create([
                'tenant_id' => $webhook->tenant_id,
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'attempts' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id, $webhook->id)
                ->onQueue('webhooks');
        }
    }

    /**
     * Generate a fresh secret for a webhook.
     */
    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Compute HMAC-SHA256 signature for a payload.
     */
    public static function sign(string $secret, string $payload): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
