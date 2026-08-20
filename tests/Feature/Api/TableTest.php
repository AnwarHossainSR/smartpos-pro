<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * Dining tables & floor plan (T125). The model maps to `dining_tables`, not the
 * `tables` name Eloquent would infer, so these also guard that mapping.
 */
class TableTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createTenant();
        Sanctum::actingAs($this->owner);
    }

    public function test_can_create_a_table(): void
    {
        $this->postJson('/api/v1/tables', [
            'branch_id' => $this->owner->branch_id,
            'name' => 'T1',
            'area' => 'Patio',
            'seats' => 4,
            'x' => 10.5,
            'y' => 20.25,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'T1')
            ->assertJsonPath('data.seats', 4)
            ->assertJsonPath('data.has_active_sale', false);

        $this->assertDatabaseHas('dining_tables', [
            'tenant_id' => $this->owner->tenant_id,
            'name' => 'T1',
            'area' => 'Patio',
        ]);
    }

    public function test_status_defaults_to_available_when_omitted(): void
    {
        $id = $this->postJson('/api/v1/tables', ['name' => 'T2', 'seats' => 2])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('available', Table::findOrFail($id)->status);
    }

    public function test_validation_rejects_a_bad_payload(): void
    {
        $this->postJson('/api/v1/tables', ['name' => '', 'seats' => 0, 'status' => 'nonsense'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'seats', 'status']);
    }

    public function test_index_lists_and_filters_by_branch_and_status(): void
    {
        Table::create(['branch_id' => $this->owner->branch_id, 'name' => 'A', 'seats' => 2, 'status' => 'available']);
        Table::create(['branch_id' => $this->owner->branch_id, 'name' => 'B', 'seats' => 4, 'status' => 'occupied']);

        $this->getJson('/api/v1/tables')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/tables?status=occupied')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'B');

        $this->getJson('/api/v1/tables?branch_id='.$this->owner->branch_id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_update_and_delete_a_table(): void
    {
        $table = Table::create(['name' => 'C', 'seats' => 2]);

        $this->getJson("/api/v1/tables/{$table->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'C');

        $this->putJson("/api/v1/tables/{$table->id}", ['status' => 'reserved', 'seats' => 6])
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved')
            ->assertJsonPath('data.seats', 6);

        $this->deleteJson("/api/v1/tables/{$table->id}")->assertNoContent();
        $this->assertSoftDeleted('dining_tables', ['id' => $table->id]);
    }

    public function test_tables_are_scoped_to_their_tenant(): void
    {
        $table = Table::create(['name' => 'Mine', 'seats' => 2]);

        Sanctum::actingAs($this->createTenant('other@example.test'));

        $this->getJson('/api/v1/tables')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/tables/{$table->id}")->assertNotFound();
        $this->putJson("/api/v1/tables/{$table->id}", ['seats' => 99])->assertNotFound();
        $this->deleteJson("/api/v1/tables/{$table->id}")->assertNotFound();
    }

    public function test_branch_from_another_tenant_is_rejected(): void
    {
        $otherOwner = $this->createTenant('branch-thief@example.test');
        app(TenantContext::class)->set($this->owner->tenant_id);
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/tables', [
            'branch_id' => $otherOwner->branch_id,
            'name' => 'Sneaky',
            'seats' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);
    }

    public function test_a_sale_can_be_rung_up_on_a_table(): void
    {
        $table = Table::create(['name' => 'T7', 'seats' => 4]);
        $warehouse = $this->warehouseFor($this->owner);
        $product = $this->stockedProduct($warehouse->id);

        $id = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.table_id', $table->id)
            ->json('data.id');

        $this->assertDatabaseHas('sales', ['id' => $id, 'table_id' => $table->id]);
        $this->assertTrue($table->sales()->whereKey($id)->exists());
    }

    public function test_a_parked_sale_keeps_its_table(): void
    {
        $table = Table::create(['name' => 'T8', 'seats' => 2]);
        $warehouse = $this->warehouseFor($this->owner);
        $product = $this->stockedProduct($warehouse->id);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $this->owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'table_id' => $table->id,
            'status' => 'held',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'held')
            ->assertJsonPath('data.table_id', $table->id);
    }

    public function test_a_sale_without_a_table_is_still_valid(): void
    {
        $warehouse = $this->warehouseFor($this->owner);
        $product = $this->stockedProduct($warehouse->id);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $this->owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.table_id', null);
    }

    public function test_a_table_from_another_tenant_cannot_be_attached_to_a_sale(): void
    {
        $otherOwner = $this->createTenant('table-thief@example.test');
        app(TenantContext::class)->set($otherOwner->tenant_id);
        $foreignTable = Table::create(['name' => 'Theirs', 'seats' => 2]);

        app(TenantContext::class)->set($this->owner->tenant_id);
        Sanctum::actingAs($this->owner);

        $warehouse = $this->warehouseFor($this->owner);
        $product = $this->stockedProduct($warehouse->id);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $this->owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'table_id' => $foreignTable->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['table_id']);
    }

    public function test_cashier_may_work_the_floor_plan_but_not_administer_it(): void
    {
        // `tables.*` was referenced by the routes long before it existed in
        // PermissionCatalog, which silently denied every role except owner. The
        // cashier role deliberately holds view+update (a waiter seats and clears
        // tables) but not create/delete, which are floor-plan administration.
        $cashier = User::create([
            'business_id' => $this->owner->business_id, 'branch_id' => $this->owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        $table = Table::create(['name' => 'Guarded', 'seats' => 2]);

        Sanctum::actingAs($cashier->fresh('roles'));

        $this->getJson('/api/v1/tables')->assertOk();
        $this->putJson("/api/v1/tables/{$table->id}", ['status' => 'occupied'])->assertOk();

        $this->postJson('/api/v1/tables', ['name' => 'Nope', 'seats' => 2])->assertForbidden();
        $this->deleteJson("/api/v1/tables/{$table->id}")->assertForbidden();
    }

    /** A product with stock on hand, so a checkout is not rejected for the wrong reason. */
    private function stockedProduct(string $warehouseId): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 40, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, 50, 40);

        return $product;
    }
}
