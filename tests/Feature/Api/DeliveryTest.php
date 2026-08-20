<?php

namespace Tests\Feature\Api;

use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // One tenant per test. createTenant() defaults to owner@example.test, so calling
        // it more than once in a test hits the users.email unique index.
        $this->owner = $this->createTenant();
        Sanctum::actingAs($this->owner);
    }

    private function completedSale(): Sale
    {
        $warehouse = $this->warehouseFor($this->owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);

        // Stock through the ledger, same as the rest of the suite — a sale of an
        // unstocked product is rejected with 422 before it ever reaches delivery.
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 100, 40);

        $id = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => null,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated()->json('data.id');

        return Sale::findOrFail($id);
    }

    private function createZone(): DeliveryZone
    {
        return DeliveryZone::create([
            'name' => 'Zone A',
            'delivery_fee' => 5.99,
        ]);
    }

    public function test_can_create_delivery_for_sale(): void
    {
        $sale = $this->completedSale();
        $zone = $this->createZone();

        $this->postJson("/api/v1/sales/{$sale->id}/deliveries", ['zone_id' => $zone->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'dispatched')
            ->assertJsonPath('data.zone_name', 'Zone A');
    }

    public function test_driver_view_shows_correct_details(): void
    {
        $sale = $this->completedSale();
        $zone = $this->createZone();

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        $this->getJson("/api/v1/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched')
            ->assertJsonPath('data.zone_name', 'Zone A')
            ->assertJsonPath('data.delivery_fee', 5.99);
    }

    public function test_status_update_works(): void
    {
        $sale = $this->completedSale();
        $zone = $this->createZone();

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        $this->putJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'in-transit'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in-transit');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'in-transit',
        ]);
    }

    public function test_delivery_is_scoped_to_its_tenant(): void
    {
        $sale = $this->completedSale();
        $zone = $this->createZone();

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        Sanctum::actingAs($this->createTenant('other-owner@example.test'));

        $this->getJson("/api/v1/deliveries/{$delivery->id}")->assertNotFound();
    }

    public function test_cannot_assign_delivery_to_unknown_zone(): void
    {
        $sale = $this->completedSale();

        $this->postJson("/api/v1/sales/{$sale->id}/deliveries", ['zone_id' => (string) Str::uuid()])
            ->assertNotFound();
    }

    public function test_cannot_assign_a_zone_belonging_to_another_tenant(): void
    {
        $sale = $this->completedSale();

        $otherOwner = $this->createTenant('zone-thief@example.test');
        app(TenantContext::class)->set($otherOwner->tenant_id);
        $foreignZone = DeliveryZone::create(['name' => 'Their Zone', 'delivery_fee' => 1.00]);
        app(TenantContext::class)->set($this->owner->tenant_id);

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/sales/{$sale->id}/deliveries", ['zone_id' => $foreignZone->id])
            ->assertNotFound();
    }

    public function test_cannot_create_delivery_without_zone(): void
    {
        $sale = $this->completedSale();

        $this->postJson("/api/v1/sales/{$sale->id}/deliveries", [])
            ->assertUnprocessable();
    }
}
