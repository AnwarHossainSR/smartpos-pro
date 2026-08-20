<?php

namespace Database\Seeders;

use App\Services\Auth\TenantProvisioner;

/**
 * Kept for seeds that build a tenant by hand; registration provisions the same
 * chart itself, so this only has to fill gaps for older tenants.
 */
class AccountSeeder extends TransactionalSeeder
{
    protected function seed(): void
    {
        app(TenantProvisioner::class)->provisionChartOfAccounts();
    }
}
