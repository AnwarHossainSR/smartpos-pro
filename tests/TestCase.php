<?php

namespace Tests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * spatie's registrar keeps permissions in a process-wide in-memory collection on
     * top of the cache store, and remembers the current team id. Neither is reset by
     * RefreshDatabase, so without this a test can inherit the previous test's grants
     * (or its tenant) and pass or fail for the wrong reason.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(TenantContext::class)->forget();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
