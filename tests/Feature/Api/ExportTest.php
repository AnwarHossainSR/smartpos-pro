<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createTenant();
        Sanctum::actingAs($this->owner);
    }

    public function test_each_module_export_returns_csv(): void
    {
        $modules = ['products', 'inventory', 'sales', 'purchases', 'customers', 'suppliers', 'reports', 'accounting'];

        foreach ($modules as $module) {
            $response = $this->getJson("/api/v1/{$module}/export?format=csv");
            $response->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        }
    }

    public function test_filtered_export_matches_index_filters(): void
    {
        $owner = $this->owner;

        $product = Product::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'TEST001',
            'selling_price' => 10.00,
            'purchase_price' => 5.00,
            'is_active' => true,
        ]);

        dump(['svc' => app(\App\Services\Export\ExportService::class)->exportCsv('products', []), 'q' => \App\Models\Product::query()->get()->count()]);
        $response = $this->getJson('/api/v1/products/export?format=csv&is_active=true');
        $response->assertStatus(200);
        $data = $response->streamedContent();
        $this->assertStringContainsString('TEST001', $data);
        $this->assertStringContainsString('Test Product', $data);

        $response2 = $this->getJson('/api/v1/products/export?format=csv&is_active=false');
        $response2->assertStatus(200);
        $data2 = $response2->streamedContent();
        $this->assertStringNotContainsString('TEST001', $data2);
    }

    public function test_permission_403_without_export_slug(): void
    {
        // The owner in setUp() holds every slug, so the negative case needs a role that
        // does not: `cashier` has none of the *.export permissions.
        $cashier = User::create([
            'business_id' => $this->owner->business_id, 'branch_id' => $this->owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));

        foreach (['products', 'inventory', 'sales', 'purchases', 'customers', 'suppliers', 'reports', 'accounting'] as $module) {
            $this->getJson("/api/v1/{$module}/export?format=csv")->assertForbidden();
        }
    }
}
