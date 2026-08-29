<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function cashierFor(User $owner, string $email = 'cashier@example.test'): User
    {
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => $email,
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        return $cashier->fresh('roles');
    }

    public function test_theme_is_null_until_the_user_picks_one(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.theme', null);
    }

    public function test_patching_preferences_persists_the_mode_and_returns_it_from_me(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark'],
        ])
            ->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark');

        $this->assertSame(
            ['mode' => 'dark'],
            $owner->fresh()->theme,
        );

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark');
    }

    public function test_a_later_patch_replaces_the_stored_mode(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark'],
        ])->assertOk();

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'light'],
        ])->assertOk();

        $this->assertSame(
            ['mode' => 'light'],
            $owner->fresh()->theme,
        );
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'midnight'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.mode');

        $this->assertNull($owner->fresh()->theme);
    }

    /**
     * The colour palette is a tenant-level setting, not a per-user preference: it is persisted
     * against the business via `PUT /settings/business`, so attempting to set it here must not
     * be accepted as a user preference.
     */
    public function test_a_palette_is_not_a_user_preference(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'emerald'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');

        $this->assertNull($owner->fresh()->theme);
    }

    public function test_unknown_keys_and_a_missing_theme_are_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'indigo', 'font' => 'comic-sans'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark'],
        ])->assertUnauthorized();
    }

    /**
     * The route carries no user id and no `permission:` guard on purpose: it is scoped to the
     * caller by construction. A non-admin (cashier) may therefore set their own mode, and
     * doing so must not touch anybody else's.
     */
    public function test_a_user_can_only_change_their_own_mode(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->cashierFor($owner);

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark'],
        ])->assertOk();

        // A cashier needs no admin permission to theme their own screen…
        Sanctum::actingAs($cashier);
        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'light'],
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $cashier->id)
            ->assertJsonPath('data.theme.mode', 'light');

        // …and the owner's mode is untouched by it.
        $this->assertSame(['mode' => 'dark'], $owner->fresh()->theme);
        $this->assertSame(['mode' => 'light'], $cashier->fresh()->theme);

        // There is no route that lets one user address another user's preferences.
        $this->patchJson("/api/v1/auth/preferences/{$owner->id}", [
            'theme' => ['mode' => 'system'],
        ])->assertNotFound();

        $this->assertSame(['mode' => 'dark'], $owner->fresh()->theme);
    }

    /** A cosmetic preference written on a debounce must not flood the audit trail. */
    public function test_a_mode_change_is_not_written_to_the_audit_trail(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $before = DB::table('audit_logs')->count();

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark'],
        ])->assertOk();

        $this->assertSame($before, DB::table('audit_logs')->count());
    }
}
