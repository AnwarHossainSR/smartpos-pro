<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_business_settings_includes_onboarding_state(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/settings/business')
            ->assertOk()
            ->assertJsonPath('data.onboarding_state', null);
    }

    public function test_record_onboarding_step_stores_progress(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_state.business_profile', true)
            ->assertJsonPath('data.onboarding_state.current_step', 1);

        $business = Business::findOrFail($owner->business_id);
        $this->assertTrue($business->onboarding_state['business_profile']);
        $this->assertSame(1, $business->onboarding_state['current_step']);
    }

    public function test_onboarding_steps_advance_current_step(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])->assertOk();

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'first_product',
            'completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_state.current_step', 2);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'tax_setup',
            'completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_state.current_step', 3);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'register_sale',
            'completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.onboarding_state.current_step', 4);
    }

    public function test_complete_onboarding_sets_dismissed_flag(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])->assertOk();

        $this->postJson('/api/v1/settings/onboarding/complete')
            ->assertOk()
            ->assertJsonPath('data.onboarding_completed', true);

        $business = Business::findOrFail($owner->business_id);
        $this->assertTrue($business->onboarding_completed);
        $this->assertTrue($business->onboarding_state['dismissed']);
        $this->assertTrue($business->onboarding_state['business_profile']);
    }

    public function test_invalid_step_name_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'invalid_step',
            'completed' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('step');
    }

    public function test_missing_completed_field_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('completed');
    }

    public function test_onboarding_step_requires_authentication(): void
    {
        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])->assertUnauthorized();
    }

    public function test_non_admin_cannot_record_onboarding_step(): void
    {
        $owner = $this->createTenant();

        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier',
            'email' => 'cashier-onboarding@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])->assertForbidden();
    }

    public function test_onboarding_state_persists_across_steps(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'business_profile',
            'completed' => true,
        ])->assertOk();

        $this->postJson('/api/v1/settings/onboarding/step', [
            'step' => 'first_product',
            'completed' => true,
        ])->assertOk();

        $this->getJson('/api/v1/settings/business')
            ->assertOk()
            ->assertJsonPath('data.onboarding_state.business_profile', true)
            ->assertJsonPath('data.onboarding_state.first_product', true)
            ->assertJsonPath('data.onboarding_state.current_step', 2);
    }
}
