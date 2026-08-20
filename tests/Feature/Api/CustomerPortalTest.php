<?php

namespace Tests\Feature\Api;

use App\Mail\CustomerPortalMagicLinkMail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Billing\StripeService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function customerWithSale(string $email = 'buyer@example.test'): array
    {
        $owner = $this->createTenant();
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
            'payments' => [['method' => 'cash', 'amount' => 150]],
        ])->assertCreated()->json('data.id');

        return ['customer' => $customer, 'sale' => Sale::findOrFail($id)];
    }

    public function test_magic_link_sends_email(): void
    {
        Mail::fake();

        $this->createTenant();

        Customer::create(['name' => 'Mail Test', 'email' => 'mailtest@example.test']);

        $this->postJson('/api/v1/portal/magic-link', ['email' => 'mailtest@example.test'])
            ->assertOk();

        Mail::assertSent(CustomerPortalMagicLinkMail::class);
    }

    public function test_magic_link_does_not_leak_account_existence(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/portal/magic-link', ['email' => 'nonexistent@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a magic link has been sent.');

        Mail::assertNothingSent();
    }

    public function test_verify_valid_token_returns_portal_jwt(): void
    {
        ['customer' => $customer] = $this->customerWithSale();

        $customer->portal_token = 'valid-token-123';
        $customer->portal_token_expires_at = now()->addHours(1);
        $customer->save();

        $response = $this->getJson('/api/v1/portal/verify/valid-token-123')
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_verify_expired_token_returns_422(): void
    {
        ['customer' => $customer] = $this->customerWithSale();

        $customer->portal_token = 'expired-token';
        $customer->portal_token_expires_at = now()->subHour();
        $customer->save();

        $this->getJson('/api/v1/portal/verify/expired-token')->assertStatus(422);
    }

    public function test_verify_invalid_token_returns_422(): void
    {
        $this->getJson('/api/v1/portal/verify/nonexistent-token')->assertStatus(422);
    }

    public function test_portal_jwt_allows_viewing_invoices(): void
    {
        ['customer' => $customer] = $this->customerWithSale();

        $token = $customer->createToken('portal', ['portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/portal/invoices')
            ->assertOk()
            ->assertJsonStructure(['data' => [0 => ['id', 'invoice_number', 'total', 'paid_amount', 'due_amount', 'payment_status']]]);
    }

    public function test_portal_jwt_allows_viewing_customer_info(): void
    {
        ['customer' => $customer] = $this->customerWithSale();

        $token = $customer->createToken('portal', ['portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/portal/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'buyer@example.test');
    }

    public function test_portal_jwt_allows_viewing_invoice_detail(): void
    {
        ['customer' => $customer, 'sale' => $sale] = $this->customerWithSale();

        $token = $customer->createToken('portal', ['portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/portal/invoices/{$sale->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $sale->invoice_number)
            ->assertJsonStructure(['data' => ['items']]);
    }

    public function test_portal_rejects_other_customers_invoice(): void
    {
        ['customer' => $customer, 'sale' => $sale] = $this->customerWithSale();

        $otherCustomer = Customer::create(['name' => 'Other', 'email' => 'other@example.test']);
        $token = $otherCustomer->createToken('portal', ['portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/portal/invoices/{$sale->id}")
            ->assertForbidden();
    }

    public function test_pay_endpoint_creates_stripe_session(): void
    {
        ['customer' => $customer, 'sale' => $sale] = $this->customerWithSale();

        $token = $customer->createToken('portal', ['portal'])->plainTextToken;

        $this->mock(StripeService::class, function ($mock) use ($sale) {
            // Match on identity, not object equality: the controller re-loads the sale,
            // so it is an equivalent-but-distinct model instance.
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (Sale $arg) => $arg->id === $sale->id)
                ->andReturn('cs_test_123');
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/portal/pay/{$sale->id}")
            ->assertOk()
            ->assertJsonPath('session_id', 'cs_test_123');
    }

    public function test_pay_rejects_fully_paid_invoice(): void
    {
        ['customer' => $customer, 'sale' => $sale] = $this->customerWithSale();

        $sale->update(['due_amount' => 0, 'paid_amount' => $sale->total]);

        $token = $customer->createToken('portal', ['portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/portal/pay/{$sale->id}")
            ->assertStatus(422);
    }
}
