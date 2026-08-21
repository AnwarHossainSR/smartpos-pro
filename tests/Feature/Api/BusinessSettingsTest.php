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
}
