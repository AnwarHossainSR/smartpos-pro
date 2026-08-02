<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\TaxType;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_tax_rate_can_be_created_listed_and_updated(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/v1/tax-rates', [
            'name' => 'VAT 15%', 'rate' => 15, 'type' => 'percentage',
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/tax-rates')->assertOk()->assertJsonFragment(['name' => 'VAT 15%']);

        $this->putJson("/api/v1/tax-rates/{$created['id']}", [
            'name' => 'VAT 15%', 'rate' => 17.5, 'type' => 'percentage', 'is_active' => true,
        ])->assertOk()->assertJsonPath('data.rate', 17.5);
    }

    public function test_a_duplicate_tax_rate_name_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $this->taxGroup(15, 'VAT');

        $this->postJson('/api/v1/tax-rates', ['name' => 'VAT 15%', 'rate' => 15, 'type' => 'percentage'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    /** A group is created with an ordered set of components in one request. */
    public function test_a_multi_component_group_can_be_created_and_the_priority_order_is_preserved(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $cgst = TaxRate::create(['name' => 'CGST', 'rate' => 9, 'type' => TaxType::Percentage]);
        $sgst = TaxRate::create(['name' => 'SGST', 'rate' => 9, 'type' => TaxType::Percentage]);

        $group = $this->postJson('/api/v1/tax-groups', [
            'name' => 'GST 18%',
            'rates' => [
                ['tax_rate_id' => $cgst->id, 'priority' => 0],
                ['tax_rate_id' => $sgst->id, 'priority' => 1],
            ],
        ])->assertCreated()->json('data');

        $this->assertCount(2, $group['rates']);
        $this->assertSame('CGST', $group['rates'][0]['name']);

        // Re-syncing with a different order updates the pivot, not just adds rows.
        $this->putJson("/api/v1/tax-groups/{$group['id']}", [
            'name' => 'GST 18%',
            'rates' => [
                ['tax_rate_id' => $sgst->id, 'priority' => 0],
                ['tax_rate_id' => $cgst->id, 'priority' => 1],
            ],
        ])->assertOk()->assertJsonPath('data.rates.0.name', 'SGST');
    }

    public function test_a_tax_zone_can_be_created_and_pointed_at_a_group(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $group = $this->taxGroup(15);

        $this->postJson('/api/v1/tax-zones', [
            'name' => 'Bangladesh', 'country_code' => 'BD', 'tax_group_id' => $group->id, 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.tax_group.id', $group->id);
    }

    public function test_the_seeded_country_profiles_are_readable(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        TaxProfile::updateOrCreate(['code' => 'GENERIC'], ['name' => 'Generic', 'is_active' => true]);

        $this->getJson('/api/v1/tax-profiles')->assertOk()->assertJsonFragment(['code' => 'GENERIC']);
    }

    public function test_a_user_without_the_taxes_permission_is_forbidden(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson('/api/v1/tax-rates', ['name' => 'VAT', 'rate' => 10, 'type' => 'percentage'])
            ->assertForbidden();
    }

    public function test_tax_summary_reconciles_output_tax_from_a_real_sale(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $group = $this->taxGroup(15);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 50, 'selling_price' => 100, 'tax_group_id' => $group->id, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 50);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->getJson('/api/v1/reports/tax-summary')
            ->assertOk()
            ->assertJsonPath('data.output_tax.total', 30);
    }
}
