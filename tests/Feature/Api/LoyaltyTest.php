<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_points_are_earned_on_sale_then_redeemed(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        // Enable 1 point per currency unit for this business.
        Business::whereKey($owner->business_id)->update(['settings' => ['loyalty_earn_rate' => 1]]);

        $customer = Customer::create(['name' => 'Loyal Larry']);
        $product = Product::create([
            'name' => 'Item', 'slug' => 'item-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 40);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated();

        // Total 100 => 100 points earned.
        $this->getJson("/api/v1/customers/{$customer->id}/loyalty")
            ->assertOk()->assertJsonPath('data.points', 100);

        $this->postJson("/api/v1/customers/{$customer->id}/loyalty/redeem", ['points' => 30])
            ->assertOk()->assertJsonPath('data.balance', 70);

        $this->postJson("/api/v1/customers/{$customer->id}/loyalty/redeem", ['points' => 1000])
            ->assertStatus(422);
    }
}
