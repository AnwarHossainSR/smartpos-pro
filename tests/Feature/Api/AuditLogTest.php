<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $stock, float $price): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'tax_rate' => 0,
            'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_a_sale_produces_a_queryable_audit_row(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);

        Sanctum::actingAs($owner);
        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated()->json('data');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.created',
            'auditable_type' => Sale::class,
            'auditable_id' => $sale['id'],
        ]);
    }

    public function test_a_role_change_produces_a_queryable_audit_row(): void
    {
        $owner = $this->createTenant();
        $role = Role::where('slug', 'cashier')->firstOrFail();

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Cashier (Updated)', 'permissions' => ['pos.view', 'pos.create', 'sales.view'],
        ])->assertOk();

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, AuditLog::where('action', 'role.updated')->where('auditable_id', $role->id)->count());
    }

    public function test_price_override_is_visible_through_the_audit_log_endpoint(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 60]],
            'payments' => [['method' => 'cash', 'amount' => 60]],
        ])->assertCreated();

        $this->getJson('/api/v1/audit-logs?action=price_override')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'sale.price_override');
    }

    public function test_audit_log_endpoint_requires_permission(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_audit_log_endpoint_paginates_and_filters_by_action(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated();

        $response = $this->getJson('/api/v1/audit-logs?action=sale.created&per_page=5')->assertOk();
        $response->assertJsonPath('meta.current_page', 1);
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertStringContainsString('sale.created', $row['action']);
        }
    }
}
