<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SaleCouponTest extends TestCase
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

    private function checkout(string $branchId, string $warehouseId, Product $product, ?string $couponCode = null): TestResponse
    {
        return $this->postJson('/api/v1/sales', [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'coupon_code' => $couponCode,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);
    }

    public function test_valid_coupon_discount_is_applied_and_used_count_incremented(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        $coupon = Coupon::create([
            'code' => 'SAVE10', 'type' => 'percentage', 'value' => 10,
            'usage_limit' => 5, 'used_count' => 0, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $response = $this->checkout($owner->branch_id, $warehouse->id, $product, 'save10');

        $response->assertCreated()
            ->assertJsonPath('data.coupon_discount', 10)
            ->assertJsonPath('data.coupon_code', 'SAVE10')
            ->assertJsonPath('data.total', 90);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_coupon_at_usage_limit_is_rejected_at_checkout(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);
        Coupon::create([
            'code' => 'ONECE', 'type' => 'fixed', 'value' => 5,
            'usage_limit' => 1, 'used_count' => 1, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->checkout($owner->branch_id, $warehouse->id, $product, 'ONECE')
            ->assertStatus(422)
            ->assertJsonValidationErrors('coupon_code');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
    }

    public function test_two_sequential_checkouts_on_a_limit_one_coupon_redeem_exactly_once(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $productA = $this->seedProduct($warehouse->id, 20, 100);
        $productB = $this->seedProduct($warehouse->id, 20, 100);
        $coupon = Coupon::create([
            'code' => 'LIMIT1', 'type' => 'fixed', 'value' => 5,
            'usage_limit' => 1, 'used_count' => 0, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->checkout($owner->branch_id, $warehouse->id, $productA, 'LIMIT1')->assertCreated();
        $this->checkout($owner->branch_id, $warehouse->id, $productB, 'LIMIT1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('coupon_code');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, Sale::count());
    }

    public function test_unknown_coupon_code_is_rejected(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $product = $this->seedProduct($warehouse->id, 20, 100);

        Sanctum::actingAs($owner);
        $this->checkout($owner->branch_id, $warehouse->id, $product, 'NOPE')
            ->assertStatus(422)
            ->assertJsonValidationErrors('coupon_code');
    }
}
