<?php

namespace Tests\Feature\Api;

use App\Models\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function createSupplier(User $owner, string $name = 'Test Supplier'): Supplier
    {
        return Supplier::create([
            'tenant_id' => $owner->tenant_id,
            'name' => $name,
            'company' => 'Test Company',
            'email' => 'test@supplier.test',
            'phone' => '1234567890',
            'tax_number' => 'TAX-12345',
            'address' => '123 Test St',
            'opening_balance' => 0,
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function createWarehouse(User $owner): \App\Models\Warehouse
    {
        return \App\Models\Warehouse::create([
            'business_id' => $owner->business_id,
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
        ]);
    }

    public function test_payment_reduces_supplier_balance(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $supplier = $this->createSupplier($owner);
        $warehouse = $this->createWarehouse($owner);

        $this->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => 50,
            'method' => 'cash',
        ])->assertCreated();

        $this->assertEquals(0 - 50, (float) $supplier->fresh()->balance);
    }

    public function test_statement_running_balance_correct(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $supplier = $this->createSupplier($owner);
        $warehouse = $this->createWarehouse($owner);

        $purchase = \App\Models\Purchase::create([
            'business_id' => $owner->business_id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'user_id' => $owner->id,
            'reference_number' => 'PUR-TEST001',
            'status' => 'approved',
            'payment_status' => 'partial',
            'subtotal' => 100,
            'tax_amount' => 10,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total' => 110,
            'due_amount' => 110,
            'purchased_at' => now()->subDays(5)->toDateString(),
        ]);

        $supplier->refresh();

        $this->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => 30,
            'method' => 'cash',
            'reference' => 'PYM-001',
        ])->assertCreated();

        $statement = $this->getJson("/api/v1/suppliers/{$supplier->id}/statement")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $statement);
        // Entries are sorted chronologically (oldest first): purchase first, then payment
        $this->assertEquals(110, $statement[0]['amount']);  // purchase due_amount
        $this->assertEquals(80, $statement[1]['running_balance']);  // 110 - 30 payment
    }

    public function test_payment_opt_out_tenant_still_records_payment(): void
    {
        $owner = $this->createTenant();
        $owner->business->update(['auto_post_journals' => false]);

        Sanctum::actingAs($owner);
        $supplier = $this->createSupplier($owner);
        $warehouse = $this->createWarehouse($owner);

        $this->postJson("/api/v1/suppliers/{$supplier->id}/payments", [
            'amount' => 25,
            'method' => 'cash',
        ])->assertCreated();

        $this->assertEquals(0 - 25, (float) $supplier->fresh()->balance);
    }
}