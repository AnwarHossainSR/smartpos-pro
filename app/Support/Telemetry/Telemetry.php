<?php

namespace App\Support\Telemetry;

use PostHog\PostHog;
use Throwable;

/**
 * Thin PostHog wrapper for product and error events. Every method is a no-op
 * when no project key is configured or telemetry is disabled, so local runs,
 * tests and self-hosted installs without a key never phone home.
 */
class Telemetry
{
    private bool $booted = false;

    public function __construct(
        private readonly ?string $key,
        private readonly string $host,
        private readonly bool $enabled,
    ) {}

    public function capture(string $event, array $properties = [], ?string $distinctId = null): void
    {
        if (! $this->boot()) {
            return;
        }

        PostHog::capture([
            'distinctId' => $distinctId ?? $this->distinctId(),
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    public function captureException(Throwable $e, array $properties = []): void
    {
        if (! $this->boot()) {
            return;
        }

        PostHog::captureException($e, $this->distinctId(), $properties);
    }

    private function distinctId(): string
    {
        return (string) (auth()->id() ?? 'system');
    }

    private function boot(): bool
    {
        if (! $this->enabled || blank($this->key)) {
            return false;
        }

        if (! $this->booted) {
            PostHog::init($this->key, ['host' => $this->host]);
            $this->booted = true;
        }

        return true;
    }
}
