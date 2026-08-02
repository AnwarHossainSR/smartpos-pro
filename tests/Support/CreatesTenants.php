<?php

namespace Tests\Support;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\TaxType;
use App\Models\TaxGroup;
use App\Models\TaxRate;
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

    /**
     * A single-component tax group — the ordinary "VAT 15%" case.
     *
     * Products carry a tax *group*, not a rate: the legacy `products.tax_rate`
     * column is no longer read by the engine, so a test that only sets it will
     * correctly see an untaxed sale.
     */
    protected function taxGroup(float $rate, string $name = 'VAT'): TaxGroup
    {
        $group = TaxGroup::create(['name' => "{$name} {$rate}%"]);

        $group->rates()->attach(
            TaxRate::create(['name' => "{$name} {$rate}%", 'rate' => $rate, 'type' => TaxType::Percentage])->id,
            ['priority' => 0, 'tenant_id' => $group->tenant_id],
        );

        return $group->load('rates');
    }

    /**
     * A multi-component group, e.g. GST split into CGST + SGST — the case a
     * single per-product rate column cannot express at all.
     *
     * @param  array<string, float>  $components  name => percentage
     */
    protected function taxGroupWithComponents(array $components, string $name = 'GST'): TaxGroup
    {
        $group = TaxGroup::create(['name' => $name]);
        $priority = 0;

        foreach ($components as $componentName => $rate) {
            $group->rates()->attach(
                TaxRate::create(['name' => $componentName, 'rate' => $rate, 'type' => TaxType::Percentage])->id,
                ['priority' => $priority++, 'tenant_id' => $group->tenant_id],
            );
        }

        return $group->load('rates');
    }

    /** Switch the tenant between net-quoting and tax-inclusive shelf pricing. */
    protected function setPriceDisplay(User $owner, PriceDisplay $display): void
    {
        $owner->business->update(['price_display' => $display]);
        $owner->unsetRelation('business');
    }
}
