<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_update_and_list_branches(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/branches', [
            'name' => 'Downtown Branch', 'code' => 'DT',
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Downtown Branch');
        $branchId = $response->json('data.id');

        $this->putJson("/api/v1/branches/{$branchId}", ['name' => 'Downtown Branch Updated', 'code' => 'DT'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Downtown Branch Updated');

        $this->getJson('/api/v1/branches')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_default_branch_cannot_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $default = Branch::first();

        $this->deleteJson("/api/v1/branches/{$default->id}")
            ->assertStatus(422);
    }

    public function test_branch_with_sales_history_cannot_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $branch = Branch::create([
            'business_id' => $owner->business_id,
            'name' => 'Second Branch',
        ]);

        Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $branch->id,
            'warehouse_id' => $this->warehouseFor($owner)->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-TEST-1',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 10, 'discount_amount' => 0, 'tax_amount' => 0,
            'shipping_amount' => 0, 'total' => 10, 'paid_amount' => 10, 'change_amount' => 0,
        ]);

        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertStatus(422);
    }

    public function test_empty_non_default_branch_can_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $branch = Branch::create(['business_id' => $owner->business_id, 'name' => 'Temp Branch']);

        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertNoContent();
        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    public function test_cashier_cannot_create_branches(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'tenant_id' => $owner->tenant_id, 'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson('/api/v1/branches', ['name' => 'Nope'])->assertForbidden();
    }
}
