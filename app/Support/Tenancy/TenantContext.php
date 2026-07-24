<?php

namespace App\Support\Tenancy;

/**
 * Holds the current tenant id for the lifetime of a request (or job).
 * Every tenant-scoped model reads from here to constrain its queries and
 * to auto-fill tenant_id on insert.
 */
class TenantContext
{
    protected ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Run a callback with a specific tenant bound, restoring the previous one after.
     * Useful in queued jobs, seeders and tests.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function runAs(?int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
