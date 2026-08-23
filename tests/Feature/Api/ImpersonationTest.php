<?php

namespace Tests\Feature\Api;

use App\Mail\ImpersonationStartedMail;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function superAdmin(): User
    {
        return User::forceCreate([
            'name' => 'Super Admin', 'email' => 'super@example.test',
            'password' => 'password123', 'status' => 'active', 'is_super_admin' => true,
        ]);
    }

    public function test_impersonating_without_a_reason_422s(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", ['reason' => 'too short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_impersonation_issues_a_scoped_short_lived_token_and_notifies_owner(): void
    {
        Mail::fake();
        $owner = $this->createTenant();
        Sanctum::actingAs($this->superAdmin());

        $response = $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", [
            'reason' => 'Investigating a billing discrepancy ticket #482',
        ])->assertCreated();

        $plainToken = $response->json('data.token');
        $tokenId = explode('|', $plainToken)[0];
        $accessToken = PersonalAccessToken::find($tokenId);

        $this->assertSame(['impersonate'], $accessToken->abilities);
        $this->assertTrue($accessToken->expires_at->diffInMinutes(now()) <= 30);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, AppNotification::where('user_id', $owner->id)->where('type', 'impersonation.started')->count());
        $this->assertSame(1, AuditLog::where('action', 'impersonation.started')->count());
        Mail::assertQueued(ImpersonationStartedMail::class, fn ($mail) => $mail->owner->is($owner));
    }

    public function test_super_admin_can_revoke_a_live_impersonation_from_their_own_session(): void
    {
        $owner = $this->createTenant();
        $superAdmin = $this->superAdmin();
        Sanctum::actingAs($superAdmin);

        $impersonationId = $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", [
            'reason' => 'Investigating a billing discrepancy ticket #482',
        ])->json('data.impersonation_id');

        $impersonatedToken = Impersonation::findOrFail($impersonationId)->personal_access_token_id;
        $this->assertNotNull(PersonalAccessToken::find($impersonatedToken));

        $this->postJson("/api/v1/super/impersonation/{$impersonationId}/revoke")->assertOk();

        $this->assertNull(PersonalAccessToken::find($impersonatedToken));
        $record = Impersonation::findOrFail($impersonationId);
        $this->assertNotNull($record->ended_at);
        $this->assertSame($superAdmin->id, $record->ended_by);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, AuditLog::where('action', 'impersonation.stopped')->count());
    }

    public function test_stopping_from_inside_the_impersonated_session_still_works(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($this->superAdmin());

        $plainToken = $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", [
            'reason' => 'Investigating a billing discrepancy ticket #482',
        ])->json('data.token');

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plainToken}")
            ->postJson('/api/v1/super/impersonation/stop')
            ->assertOk();

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Impersonation::whereNotNull('ended_at')->count());
    }

    public function test_active_impersonations_list_reflects_state(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/v1/super/impersonations')->assertOk()->assertJsonCount(0, 'data');

        $impersonationId = $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/impersonate", [
            'reason' => 'Investigating a billing discrepancy ticket #482',
        ])->json('data.impersonation_id');

        $this->getJson('/api/v1/super/impersonations')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/super/impersonation/{$impersonationId}/revoke")->assertOk();

        $this->getJson('/api/v1/super/impersonations')->assertOk()->assertJsonCount(0, 'data');
    }
}
