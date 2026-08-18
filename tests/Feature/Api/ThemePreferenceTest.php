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

    public function test_patching_preferences_persists_the_theme_and_returns_it_from_me(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'emerald'],
        ])
            ->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark')
            ->assertJsonPath('data.theme.palette', 'emerald');

        $this->assertSame(
            ['mode' => 'dark', 'palette' => 'emerald'],
            $owner->fresh()->theme,
        );

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark')
            ->assertJsonPath('data.theme.palette', 'emerald');
    }

    public function test_a_later_patch_replaces_the_stored_theme(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'emerald'],
        ])->assertOk();

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'light', 'palette' => 'rose'],
        ])->assertOk();

        $this->assertSame(
            ['mode' => 'light', 'palette' => 'rose'],
            $owner->fresh()->theme,
        );
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'midnight', 'palette' => 'indigo'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.mode');

        $this->assertNull($owner->fresh()->theme);
    }

    public function test_an_unknown_palette_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'chartreuse'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.palette');

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
            'theme' => ['mode' => 'dark', 'palette' => 'indigo'],
        ])->assertUnauthorized();
    }

    /**
     * The route carries no user id and no `permission:` guard on purpose: it is scoped to the
     * caller by construction. A non-admin (cashier) may therefore set their own theme, and
     * doing so must not touch anybody else's.
     */
    public function test_a_user_can_only_change_their_own_theme(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->cashierFor($owner);

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'emerald'],
        ])->assertOk();

        // A cashier needs no admin permission to theme their own screen…
        Sanctum::actingAs($cashier);
        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'light', 'palette' => 'rose'],
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $cashier->id)
            ->assertJsonPath('data.theme.palette', 'rose');

        // …and the owner's theme is untouched by it.
        $this->assertSame(['mode' => 'dark', 'palette' => 'emerald'], $owner->fresh()->theme);
        $this->assertSame(['mode' => 'light', 'palette' => 'rose'], $cashier->fresh()->theme);

        // There is no route that lets one user address another user's preferences.
        $this->patchJson("/api/v1/auth/preferences/{$owner->id}", [
            'theme' => ['mode' => 'system', 'palette' => 'ocean'],
        ])->assertNotFound();

        $this->assertSame(['mode' => 'dark', 'palette' => 'emerald'], $owner->fresh()->theme);
    }

    /** A cosmetic preference written on a debounce must not flood the audit trail. */
    public function test_a_theme_change_is_not_written_to_the_audit_trail(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $before = DB::table('audit_logs')->count();

        $this->patchJson('/api/v1/auth/preferences', [
            'theme' => ['mode' => 'dark', 'palette' => 'ocean'],
        ])->assertOk();

        $this->assertSame($before, DB::table('audit_logs')->count());
    }
}
