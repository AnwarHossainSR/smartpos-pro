<?php

namespace Tests\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Auth\TenantProvisioner;
use App\Support\Tenancy\TenantContext;

/**
 * Test helper that provisions a fully-formed tenant (owner, business, branch,
 * warehouse, roles) and binds the tenant context, mirroring registration.
 */
trait CreatesTenants
{
    protected function createTenant(string $email = 'owner@example.test'): User
    {
        $owner = app(TenantProvisioner::class)->register([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password123',
            'business_name' => 'Test Store '.uniqid(),
        ]);

        // Fully-formed test tenants are "established" — verified, same as any owner who has
        // gone through onboarding. Tests targeting the verification gate itself create their
        // own unverified user rather than going through this helper.
        $owner->forceFill(['email_verified_at' => now()])->save();

        app(TenantContext::class)->set($owner->tenant_id);

        return $owner->fresh(['roles', 'branch']);
    }

    protected function warehouseFor(User $owner): Warehouse
    {
        return Warehouse::where('business_id', $owner->business_id)->firstOrFail();
    }
}
