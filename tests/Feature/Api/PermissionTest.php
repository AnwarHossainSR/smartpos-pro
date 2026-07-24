<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_cashier_cannot_delete_products(): void
    {
        $owner = $this->createTenant();

        $cashier = User::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        // Owner creates a product.
        Sanctum::actingAs($owner);
        $productId = $this->postJson('/api/v1/products', [
            'name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2,
        ])->json('data.id');

        // Cashier may view but not delete.
        Sanctum::actingAs($cashier->fresh('roles'));
        $this->getJson('/api/v1/products')->assertOk();
        $this->deleteJson("/api/v1/products/{$productId}")->assertForbidden();
    }
}
