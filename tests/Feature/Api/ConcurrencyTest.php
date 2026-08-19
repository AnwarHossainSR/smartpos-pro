<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_two_simultaneous_checkouts_last_unit_of_stock(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = Product::create([
            'name' => 'Last Item', 'slug' => 'last-item-'.uniqid(), 'sku' => 'SKU-LAST-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10, 'tax_rate' => 0,
            'tax_group_id' => null, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 1, 5);

        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ];

        $response1 = $this->withHeader('Idempotency-Key', 'conc-key-1')->postJson('/api/v1/sales', $payload);
        $response2 = $this->withHeader('Idempotency-Key', 'conc-key-2')->postJson('/api/v1/sales', $payload);

        $response1->assertStatus(201);
        $response2->assertStatus(422);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
        $this->assertEquals(0, $product->fresh()->totalStock());
    }
}
