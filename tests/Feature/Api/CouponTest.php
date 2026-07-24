<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_coupon_validation_computes_discount_and_enforces_rules(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/coupons', [
            'code' => 'save10', 'type' => 'percentage', 'value' => 10, 'min_purchase' => 50,
        ])->assertCreated();

        // 10% of 100 = 10.
        $this->postJson('/api/v1/coupons/validate', ['code' => 'SAVE10', 'cart_total' => 100])
            ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('discount', 10);

        // Below minimum purchase.
        $this->postJson('/api/v1/coupons/validate', ['code' => 'SAVE10', 'cart_total' => 20])
            ->assertStatus(422)->assertJsonPath('valid', false);

        // Unknown code.
        $this->postJson('/api/v1/coupons/validate', ['code' => 'NOPE', 'cart_total' => 100])
            ->assertStatus(404);
    }
}
