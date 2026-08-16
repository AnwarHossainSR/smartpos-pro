<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\StockMovementType;
use App\Models\CashRegister;
use App\Models\Product;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PosBackendTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $price, float $stock = 20): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_a_held_sale_does_not_move_stock_until_resumed(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 100);

        $held = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id, 'status' => 'held',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated()->json('data');

        $this->assertSame('held', $held['status']);
        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(20, $product->fresh()->totalStock(), 0.001);

        $resumed = $this->postJson("/api/v1/sales/{$held['id']}/resume", [
            'payments' => [['method' => 'cash', 'amount' => 300]],
        ])->assertOk()->json('data');

        $this->assertSame('completed', $resumed['status']);
        $this->assertSame('paid', $resumed['payment_status']);
        $this->assertEqualsWithDelta(17, $product->fresh()->totalStock(), 0.001);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::Sale->value, 'quantity' => 3]);
    }

    public function test_voiding_a_completed_sale_reverses_its_stock(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 50);

        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ])->assertCreated()->json('data');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(16, $product->fresh()->totalStock(), 0.001);

        $voided = $this->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'Rang up the wrong item'])
            ->assertOk()->json('data');

        $this->assertSame('cancelled', $voided['status']);
        $this->assertEqualsWithDelta(20, $product->fresh()->totalStock(), 0.001);
    }

    public function test_a_held_sale_cannot_be_voided_directly(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 50);

        $held = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id, 'status' => 'held',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/sales/{$held['id']}/void")->assertUnprocessable();
    }

    public function test_pos_bootstrap_returns_the_registers_open_session_and_tax_profile(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front till', 'is_active' => true]);
        $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 100])->assertCreated();

        $bootstrap = $this->getJson('/api/v1/pos/bootstrap')->assertOk()->json('data');

        $this->assertSame($owner->branch_id, $bootstrap['branch']['id']);
        $this->assertSame($warehouse->id, $bootstrap['warehouse']['id']);
        $this->assertSame($register->id, $bootstrap['cash_register']['id']);
        $this->assertNotNull($bootstrap['session']);
        $this->assertNotEmpty($bootstrap['payment_methods']);
        $this->assertArrayHasKey('price_display', $bootstrap);
    }
}
