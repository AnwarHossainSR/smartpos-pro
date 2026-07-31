<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class QueryConfigHardeningTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_products_sort_whitelist_rejects_unknown_column(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/products?sort=password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->getJson('/api/v1/products?sort=name')->assertOk();
        $this->getJson('/api/v1/products?sort=-name')->assertOk();
    }

    public function test_customers_sort_whitelist_rejects_unknown_column(): void
    {
        $owner = $this->createTenant();
        Customer::create(['name' => 'Alice']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/customers?'.http_build_query(['sort' => 'password OR 1=1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    /**
     * Descending order is a `-` prefix on the column, not a separate `direction`
     * parameter — so a bogus direction can only arrive as a bogus column name and
     * is caught by the same allow-list.
     */
    public function test_suppliers_sort_whitelist_rejects_a_prefixed_unknown_column(): void
    {
        $owner = $this->createTenant();
        Supplier::create(['name' => 'Acme Supplies']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/suppliers?sort=-password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->getJson('/api/v1/suppliers?sort=-name')->assertOk();
    }

    /**
     * A query key nothing declares is rejected: an unfiltered list is
     * indistinguishable from a correctly filtered one, so a typo'd filter would
     * otherwise look like a legitimate result.
     */
    public function test_an_undeclared_query_parameter_is_rejected(): void
    {
        $owner = $this->createTenant();
        Supplier::create(['name' => 'Acme Supplies']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/suppliers?has_dew=1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('has_dew');

        $this->getJson('/api/v1/suppliers?has_due=1')->assertOk();
    }

    /**
     * `direction` is a real column on stock_movements. It must reach the filter
     * rather than being swallowed by the sort protocol.
     */
    public function test_direction_is_a_filter_not_a_sort_parameter(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/inventory/movements?direction=in')->assertOk();

        $this->getJson('/api/v1/inventory/movements?direction=sideways')
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    }

    public function test_is_super_admin_and_tenant_id_are_not_mass_assignable(): void
    {
        $owner = $this->createTenant();

        // Neither attribute is fillable. With Model::shouldBeStrict() on outside
        // production the attempt is rejected outright instead of quietly dropped,
        // so a payload trying to plant a super-admin can't pass unnoticed.
        try {
            User::create([
                'tenant_id' => 999999,
                'business_id' => $owner->business_id,
                'branch_id' => $owner->branch_id,
                'name' => 'Attacker', 'email' => 'attacker@example.test',
                'password' => 'password123', 'status' => 'active',
                'is_super_admin' => true,
            ]);

            $this->fail('Expected the non-fillable attributes to be rejected.');
        } catch (MassAssignmentException $e) {
            $this->assertStringContainsString('tenant_id', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);

        // Production keeps the discard silent, so assert the resulting state too: a user
        // created without those keys still lands in the actor's tenant, never elevated.
        $user = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Member', 'email' => 'member@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);

        $this->assertFalse((bool) $user->is_super_admin);
        $this->assertSame($owner->tenant_id, $user->tenant_id);
        $this->assertNotSame(999999, $user->tenant_id);
    }

    public function test_idempotency_key_does_not_replay_across_different_routes(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Mug', 'slug' => 'mug-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 3, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 20, 3);

        $key = 'shared-key-'.uniqid();

        $sale = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ])->assertCreated()->json('data');

        $saleItemId = $sale['items'][0]['id'];

        // Same key, different route (returns) — must not replay the sale's cached response.
        $returnResponse = $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/sales/{$sale['id']}/returns", [
                'items' => [['sale_item_id' => $saleItemId, 'quantity' => 1]],
            ]);

        $returnResponse->assertCreated();
        $this->assertNull($returnResponse->headers->get('Idempotent-Replayed'));
        $this->assertArrayHasKey('refund_method', $returnResponse->json('data'));
        $this->assertArrayNotHasKey('invoice_number', $returnResponse->json('data'));
    }

    public function test_idempotency_key_reused_with_a_different_payload_is_rejected(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Mug', 'slug' => 'mug-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 3, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 20, 3);

        $key = 'reused-key-'.uniqid();
        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ];

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/sales', $payload)->assertCreated();

        $payload['items'][0]['quantity'] = 2;
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/sales', $payload)
            ->assertStatus(409);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(19, $product->fresh()->totalStock(), 0.001);
    }
}
