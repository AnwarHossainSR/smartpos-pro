<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TenantScopeFailClosedTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_an_unscoped_query_with_no_tenant_bound_throws(): void
    {
        app(TenantContext::class)->forget();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unscoped query on tenant-owned model/');

        Product::count();
    }

    public function test_without_tenancy_explicitly_bypasses_the_guard(): void
    {
        app(TenantContext::class)->forget();

        // Does not throw — this is the explicit, documented escape hatch.
        $this->assertIsInt(Product::withoutTenancy()->count());
    }

    public function test_run_unscoped_explicitly_bypasses_the_guard(): void
    {
        app(TenantContext::class)->forget();

        $result = app(TenantContext::class)->runUnscoped(fn () => Product::count());

        $this->assertIsInt($result);

        // The escape hatch is scoped to the callback only — it doesn't leak.
        $this->expectException(RuntimeException::class);
        Product::count();
    }

    public function test_query_is_correctly_scoped_when_a_tenant_is_bound(): void
    {
        $owner = $this->createTenant();

        $this->assertSame(0, Product::count());
    }

    public function test_a_job_bound_to_one_tenant_cannot_read_another_tenants_rows(): void
    {
        $ownerA = $this->createTenant('owner-a@example.test');
        $warehouseA = $this->warehouseFor($ownerA);
        Sale::forceCreate([
            'tenant_id' => $ownerA->tenant_id, 'business_id' => $ownerA->business_id,
            'branch_id' => $ownerA->branch_id, 'warehouse_id' => $warehouseA->id, 'user_id' => $ownerA->id,
            'invoice_number' => 'INV-A-1', 'status' => 'completed', 'payment_status' => 'paid',
            'total' => 100, 'sold_at' => now(),
        ]);

        $ownerB = $this->createTenant('owner-b@example.test');
        $warehouseB = $this->warehouseFor($ownerB);
        Sale::forceCreate([
            'tenant_id' => $ownerB->tenant_id, 'business_id' => $ownerB->business_id,
            'branch_id' => $ownerB->branch_id, 'warehouse_id' => $warehouseB->id, 'user_id' => $ownerB->id,
            'invoice_number' => 'INV-B-1', 'status' => 'completed', 'payment_status' => 'paid',
            'total' => 200, 'sold_at' => now(),
        ]);

        $context = app(TenantContext::class);

        // Simulates a queued job scoped to a single tenant (SendDailyReportJob's own pattern):
        // bind, read, and it must only ever see that tenant's rows.
        $context->forget();
        $salesForA = $context->runAs($ownerA->tenant_id, fn () => Sale::sum('total'));
        $salesForB = $context->runAs($ownerB->tenant_id, fn () => Sale::sum('total'));

        $this->assertEquals(100, $salesForA);
        $this->assertEquals(200, $salesForB);

        // A job that forgot to bind a tenant fails loudly instead of reading every tenant's rows.
        $context->forget();
        $this->expectException(RuntimeException::class);
        Sale::sum('total');
    }
}
