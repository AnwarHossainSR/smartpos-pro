<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_automatic_bookkeeping_can_be_toggled_and_reads_back(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/settings/business', ['auto_post_journals' => false])
            ->assertOk()
            ->assertJsonPath('data.auto_post_journals', false);

        $this->assertFalse((bool) Business::find($owner->business_id)->auto_post_journals);

        $this->putJson('/api/v1/settings/business', ['auto_post_journals' => true])
            ->assertOk()
            ->assertJsonPath('data.auto_post_journals', true);

         $this->getJson('/api/v1/settings/business')
            ->assertOk()
            ->assertJsonPath('data.auto_post_journals', true);
    }

    public function test_palette_is_amber_by_default(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/settings/business')
            ->assertOk()
            ->assertJsonPath('data.theme.palette', 'amber');

        $this->assertNull(Business::find($owner->business_id)->settings['theme']['palette'] ?? null);
    }

    public function test_palette_can_be_changed_and_reads_back(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/settings/business', ['theme' => ['palette' => 'rose']])
            ->assertOk()
            ->assertJsonPath('data.theme.palette', 'rose');

        $this->assertSame('rose', Business::find($owner->business_id)->settings['theme']['palette']);

        $this->getJson('/api/v1/settings/business')
            ->assertOk()
            ->assertJsonPath('data.theme.palette', 'rose');
    }

    public function test_an_unknown_palette_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/settings/business', ['theme' => ['palette' => 'chartreuse']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.palette');

        $business = Business::find($owner->business_id);
        $this->assertArrayNotHasKey('theme', $business->settings ?? []);
    }

    public function test_a_non_admin_cannot_change_the_palette(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->userWithRole($owner, 'cashier');
        Sanctum::actingAs($cashier);

        $this->putJson('/api/v1/settings/business', ['theme' => ['palette' => 'rose']])
            ->assertForbidden();

        $this->assertArrayNotHasKey('theme', Business::find($owner->business_id)->settings ?? []);
    }
}
