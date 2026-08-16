<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SalePricingSecurityTest extends TestCase
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

    private function cashierFor(User $owner): User
    {
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        return $cashier->fresh('roles');
    }

    private function managerFor(User $owner): User
    {
        $manager = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Manager', 'email' => 'manager@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $manager->assignRole('branch_manager');

        return $manager->fresh('roles');
    }

    public function test_cashier_cannot_override_unit_price(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        $cashier = $this->cashierFor($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 0]],
            'payments' => [['method' => 'cash', 'amount' => 0]],
        ])->assertForbidden();

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
    }

    public function test_cashier_cannot_apply_line_discount(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        $cashier = $this->cashierFor($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => 50]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ])->assertForbidden();
    }

    public function test_manager_override_succeeds_and_is_priced_from_catalog_when_unset(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        $manager = $this->managerFor($owner);

        Sanctum::actingAs($manager);
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 60]],
            'payments' => [['method' => 'cash', 'amount' => 60]],
        ])->assertCreated();

        $response->assertJsonPath('data.total', 60);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, AuditLog::where('action', 'sale.price_override')->count());
    }

    public function test_sale_level_discount_cannot_exceed_subtotal(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'discount_amount' => 500,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('discount_amount');
    }

    public function test_price_is_always_taken_from_catalog_when_no_override_field_sent(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        $cashier = $this->cashierFor($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ])->assertCreated()->assertJsonPath('data.total', 200);
    }
}
