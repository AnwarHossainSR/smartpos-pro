<?php

namespace App\Support\Tenancy;

/**
 * Holds the current tenant id for the lifetime of a request (or job).
 * Every tenant-scoped model reads from here to constrain its queries and
 * to auto-fill tenant_id on insert.
 */
class TenantContext
{
    protected ?string $tenantId = null;

    /** True only inside runUnscoped() — an explicit, temporary opt-out of the fail-closed guard. */
    protected bool $unscoped = false;

    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?string
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

    public function unscopedAllowed(): bool
    {
        return $this->unscoped;
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
    public function runAs(?string $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }

    /**
     * Run a callback with the fail-closed guard explicitly suspended — for code you don't
     * control the queries of (framework internals like the password-reset broker) on a
     * legitimately cross-tenant/public code path. Prefer `Model::withoutTenancy()` when you
     * do control the query directly; it's more localized.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function runUnscoped(callable $callback): mixed
    {
        $previous = $this->unscoped;
        $this->unscoped = true;

        try {
            return $callback();
        } finally {
            $this->unscoped = $previous;
        }
    }
}
