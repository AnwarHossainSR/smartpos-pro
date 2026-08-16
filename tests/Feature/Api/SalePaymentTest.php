<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SalePaymentTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $stock, float $price): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    private function makeDueSale(mixed $owner, mixed $warehouse, mixed $customer, float $price = 100): array
    {
        $product = $this->seedProduct($warehouse->id, 10, $price);

        return $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 40]],
        ])->assertCreated()->json('data');
    }

    public function test_partial_payment_can_be_collected_against_a_due_sale(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $customer = Customer::create(['name' => 'Jane Doe']);

        $sale = $this->makeDueSale($owner, $warehouse, $customer, price: 100);
        $this->assertSame('partial', $sale['payment_status']);
        $this->assertEquals(60, $sale['due_amount']);
        $this->assertEquals(60, (float) $customer->fresh()->balance);

        $response = $this->postJson("/api/v1/sales/{$sale['id']}/payments", [
            'method' => 'cash', 'amount' => 30,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.due_amount', 30)
            ->assertJsonPath('data.paid_amount', 70);

        $this->assertEquals(30, (float) $customer->fresh()->balance);
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $sale['id'], 'amount' => 30]);
    }

    public function test_paying_off_the_remaining_balance_marks_the_sale_paid(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $customer = Customer::create(['name' => 'Jane Doe']);

        $sale = $this->makeDueSale($owner, $warehouse, $customer, price: 100);

        $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['method' => 'cash', 'amount' => 60])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.due_amount', 0);

        $this->assertSame(0.0, (float) $customer->fresh()->balance);
    }

    public function test_overpayment_beyond_the_outstanding_balance_is_rejected(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $customer = Customer::create(['name' => 'Jane Doe']);

        $sale = $this->makeDueSale($owner, $warehouse, $customer, price: 100);

        $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['method' => 'cash', 'amount' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_fully_paid_sale_rejects_further_payments(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 10, 50);

        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ])->assertCreated()->json('data');

        $this->assertSame('paid', $sale['payment_status']);

        $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['method' => 'cash', 'amount' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_cashier_without_sales_update_permission_cannot_collect_payments(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $customer = Customer::create(['name' => 'Jane Doe']);
        $sale = $this->makeDueSale($owner, $warehouse, $customer);

        $cashier = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson("/api/v1/sales/{$sale['id']}/payments", ['method' => 'cash', 'amount' => 10])
            ->assertForbidden();
    }

    public function test_payments_are_isolated_between_tenants(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        $warehouseA = $this->warehouseFor($ownerA);
        Sanctum::actingAs($ownerA);
        $customerA = Customer::create(['name' => 'Customer A']);
        $saleA = $this->makeDueSale($ownerA, $warehouseA, $customerA);

        $ownerB = $this->createTenant('b@example.test');
        Sanctum::actingAs($ownerB);
        $this->postJson("/api/v1/sales/{$saleA['id']}/payments", ['method' => 'cash', 'amount' => 10])
            ->assertNotFound();

        app(TenantContext::class)->set($ownerA->tenant_id);
        $this->assertSame(0, Sale::findOrFail($saleA['id'])->payments()->where('amount', 10)->count());
    }
}
