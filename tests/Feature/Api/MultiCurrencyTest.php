<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $ownerId, string $warehouseId, float $stock, float $price): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'tax_rate' => 0,
            'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)
            ->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_sale_in_base_currency_works_normally(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 100);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', 200)
            ->assertJsonPath('data.tender_currency', null)
            ->assertJsonPath('data.tender_amount', null)
            ->assertJsonPath('data.fx_rate', null);
    }

    public function test_sale_in_foreign_currency_stores_correct_rate(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $tenant = Tenant::find($owner->tenant_id);
        $tenant->update(['base_currency' => 'USD', 'fallback_exchange_rate' => 82.5]);

        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 100);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
            'tender_currency' => 'BDT',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', 200)
            ->assertJsonPath('data.tender_currency', 'BDT')
            ->assertJsonPath('data.fx_rate', 82.5);

        app(TenantContext::class)->set($owner->tenant_id);
        $sale = Sale::find($response->json('data.id'));
        $this->assertEquals('BDT', $sale->tender_currency);
        $this->assertEquals(82.5, (float) $sale->fx_rate);
        $this->assertEqualsWithDelta(16500.0, (float) $sale->tender_amount, 0.01);
    }

    public function test_fx_gain_loss_journal_entry_is_created(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $tenant = Tenant::find($owner->tenant_id);
        $tenant->update(['base_currency' => 'USD', 'fallback_exchange_rate' => 82.5]);

        $business = $owner->business;
        $business->update(['auto_post_journals' => true]);

        Account::firstOrCreate(
            ['name' => 'FX Gain', 'tenant_id' => $owner->tenant_id],
            ['type' => 'income', 'account_number' => '4100', 'normal_side' => 'credit', 'tenant_id' => $owner->tenant_id, 'is_active' => true]
        );
        Account::firstOrCreate(
            ['name' => 'FX Loss', 'tenant_id' => $owner->tenant_id],
            ['type' => 'expense', 'account_number' => '5100', 'normal_side' => 'debit', 'tenant_id' => $owner->tenant_id, 'is_active' => true]
        );
        Account::firstOrCreate(
            ['name' => 'Cash', 'tenant_id' => $owner->tenant_id],
            ['type' => 'asset', 'account_number' => '1000', 'normal_side' => 'debit', 'tenant_id' => $owner->tenant_id, 'is_active' => true]
        );

        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 100);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
            'tender_currency' => 'BDT',
            'tender_amount' => 16500,
            'fx_rate' => 82.5,
        ]);

        $response->assertCreated();

        app(TenantContext::class)->set($owner->tenant_id);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Sale::class,
            'source_id' => $response->json('data.id'),
            'reference' => 'FX-'.$response->json('data.invoice_number'),
        ]);
    }

    public function test_tenant_base_currency_is_updated(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/settings/business', [
            'base_currency' => 'EUR',
        ]);

        $response->assertOk();

        $tenant = Tenant::find($owner->tenant_id);
        $this->assertEquals('EUR', $tenant->base_currency);
    }
}
