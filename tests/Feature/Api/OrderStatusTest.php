<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\SaleStatus;
use App\Mail\OrderStatusLinkMail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * The order-status page is the only unauthenticated read in the API, so the
 * tests here are mostly about what it *doesn't* return: no ids, no costs, no
 * neighbouring tenant, and nothing at all for a cart that was never completed.
 */
class OrderStatusTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function completedSale(string $email = 'buyer@example.test', string $ownerEmail = 'owner@example.test'): Sale
    {
        $owner = $this->createTenant($ownerEmail);
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 40);

        $customer = Customer::create(['name' => 'Buyer', 'email' => $email]);

        $id = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ])->assertCreated()->json('data.id');

        return Sale::findOrFail($id);
    }

    public function test_anyone_holding_the_token_sees_the_order(): void
    {
        $sale = $this->completedSale();

        // No Sanctum actor and no tenant header — the token is the whole credential.
        app('auth')->forgetGuards();

        $response = $this->getJson("/api/v1/orders/{$sale->public_token}")->assertOk();

        $response->assertJsonPath('data.invoice_number', $sale->invoice_number)
            ->assertJsonPath('data.total', 200)
            ->assertJsonPath('data.due_amount', 0)
            ->assertJsonPath('data.items.0.name', 'Widget')
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_the_payload_carries_no_internal_identifiers_or_costs(): void
    {
        $sale = $this->completedSale();
        app('auth')->forgetGuards();

        $data = $this->getJson("/api/v1/orders/{$sale->public_token}")->assertOk()->json('data');

        $encoded = json_encode($data);
        $this->assertStringNotContainsString($sale->id, $encoded);
        $this->assertStringNotContainsString($sale->tenant_id, $encoded);
        $this->assertArrayNotHasKey('customer', $data);
        $this->assertArrayNotHasKey('unit_cost', $data['items'][0]);
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->completedSale();
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/orders/'.str_repeat('a', 48))->assertNotFound();
    }

    public function test_a_held_cart_is_not_exposed(): void
    {
        $sale = $this->completedSale();
        Sale::whereKey($sale->id)->update(['status' => SaleStatus::Held->value]);

        app('auth')->forgetGuards();

        $this->getJson("/api/v1/orders/{$sale->public_token}")->assertNotFound();
    }

    public function test_every_sale_gets_a_unique_token(): void
    {
        $first = $this->completedSale();
        $second = $this->completedSale('second@example.test', 'owner2@example.test');

        $this->assertNotSame($first->public_token, $second->public_token);
        $this->assertSame(48, strlen($first->public_token));
    }

    public function test_the_status_link_is_emailed_to_the_customer(): void
    {
        Mail::fake();

        $sale = $this->completedSale('buyer@example.test');

        $this->postJson("/api/v1/sales/{$sale->id}/email-status-link")
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'buyer@example.test');

        Mail::assertQueued(
            OrderStatusLinkMail::class,
            fn (OrderStatusLinkMail $mail) => str_contains($mail->url, $sale->public_token)
                && $mail->hasTo('buyer@example.test'),
        );
    }

    public function test_a_sale_without_an_email_is_rejected(): void
    {
        Mail::fake();

        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 40);

        $id = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/{$id}/email-status-link")
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        Mail::assertNothingQueued();
    }
}
