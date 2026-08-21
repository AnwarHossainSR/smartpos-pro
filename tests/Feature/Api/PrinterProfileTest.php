<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PrinterProfileTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_usb_printer_is_created_without_the_client_sending_a_business_id(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/printer-profiles', [
            'name' => 'Counter USB',
            'connection_type' => 'usb',
            'paper_width' => 80,
        ])
            ->assertCreated()
            ->assertJsonPath('data.connection_type', 'usb')
            ->assertJsonPath('data.business_id', $owner->business_id);

        $this->getJson('/api/v1/printer-profiles')->assertOk()->assertJsonCount(1, 'data');
    }
}
