<?php

namespace Tests\Feature\Api;

use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function completedSale(): Sale
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $warehouse = $this->warehouseFor($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);

        $id = $this->postJson("/api/v1/sales", [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => null,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated()->json('data.id');

        return Sale::findOrFail($id);
    }

    private function createZone(): DeliveryZone
    {
        $owner = $this->createTenant()->fresh();
        return DeliveryZone::create([
            'tenant_id' => $owner->id,
            'name' => 'Zone A',
            'delivery_fee' => 5.99,
        ]);
    }

public function test_can_create_delivery_for_sale(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $sale = $this->completedSale();

        $zone = $this->createZone();

        $response = $this->postJson("/api/v1/sales/{$sale->id}/deliveries", [
            'zone_id' => $zone->id,
        ]);

        // Debug: capture 422 details
        if ($response->status() === 422) {
            $json = $response->json();
            file_put_contents('/tmp/delivery_422.json', json_encode($json, JSON_PRETTY_PRINT));
            // Also dump the sale creation response
            file_put_contents('/tmp/sale_creation.json', json_encode($sale->toArray(), JSON_PRETTY_PRINT));
        }

        $response->assertCreated()
            ->assertJsonPath('data.status', 'dispatched')
            ->assertJsonContains(['data.zone_name' => 'Zone A']);
    }

    public function test_driver_view_shows_correct_details(): void
    {
        $owner = $this->createTenant('test@test.test');
        Sanctum::actingAs($owner);

        $sale = $this->completedSale();

        $zone = $this->createZone();

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        $response = $this->getJson("/api/v1/deliveries/{$delivery->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'dispatched')
            ->assertJsonPath('data.zone_name', 'Zone A')
            ->assertJsonPath('data.delivery_fee', 5.99);
    }

    public function test_status_update_works(): void
    {
        $owner = $this->createTenant('test@test.test');
        Sanctum::actingAs($owner);

        $sale = $this->completedSale();

        $zone = $this->createZone();

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        $response = $this->putJson("/api/v1/deliveries/{$delivery->id}/status", [
            'status' => 'in-transit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in-transit');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'in-transit',
        ]);
    }

    public function test_cannot_assign_delivery_to_unknown_zone(): void
    {
        $owner = $this->createTenant('test@test.test');
        Sanctum::actingAs($owner);

        $sale = $this->completedSale();

        $response = $this->postJson("/api/v1/sales/{$sale->id}/deliveries", [
            'zone_id' => 9999,
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_create_delivery_without_zone(): void
    {
        $owner = $this->createTenant('test@test.test');
        Sanctum::actingAs($owner);

        $sale = $this->completedSale();

        $response = $this->postJson("/api/v1/sales/{$sale->id}/deliveries", []);

        $response->assertUnprocessable();
    }
}