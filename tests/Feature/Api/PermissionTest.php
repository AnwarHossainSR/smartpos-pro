<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    /**
     * A route guarded by `permission:<slug>` whose slug is not in the catalogue is
     * never seeded, so `hasPermission()` correctly answers false for it and the
     * endpoint quietly becomes owner/super-admin-only — no error, no failing test,
     * just a feature nobody else can be granted. That is precisely how `tables.*`
     * and `deliveries.*` shipped broken; this closes the gap for the next module.
     */
    public function test_every_route_permission_guard_exists_in_the_catalogue(): void
    {
        // `super_admin.view` is deliberately unbackable: it marks a route as
        // super-admin-only rather than naming a tenant capability, and is reached
        // through the is_super_admin short circuit, not a permission row.
        $intentionallyUncatalogued = ['super_admin.view'];

        $catalogue = array_column(PermissionCatalog::all(), 'slug');
        $guarded = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    $guarded[] = substr($middleware, strlen('permission:'));
                }
            }
        }

        $guarded = array_values(array_unique($guarded));

        $this->assertNotEmpty($guarded, 'Found no permission-guarded routes — the scan is broken, not the catalogue.');

        $missing = array_values(array_diff($guarded, $catalogue, $intentionallyUncatalogued));

        $this->assertSame([], $missing, sprintf(
            'These route guards name permissions PermissionCatalog never seeds, so only owners '.
            'and super admins can ever pass them: %s',
            implode(', ', $missing),
        ));
    }

    public function test_a_role_granted_tables_view_passes_the_permission_middleware(): void
    {
        $owner = $this->createTenant();

        $waiter = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Waiter', 'email' => 'waiter@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);

        // A bespoke role holding exactly one permission, so no owner/admin implicit
        // grant is in play: this passes only if `tables.view` is genuinely seeded and
        // genuinely resolves through the middleware.
        $role = Role::create(['name' => 'waiter-'.uniqid(), 'title' => 'Waiter']);
        $role->syncPermissions(['tables.view']);
        $waiter->assignRole($role);

        Sanctum::actingAs($waiter->fresh('roles'));

        $this->getJson('/api/v1/tables')->assertOk();
        // ...while the neighbouring action it was not granted stays shut.
        $this->postJson('/api/v1/tables', ['name' => 'T1', 'capacity' => 4])->assertForbidden();
    }

    public function test_the_stock_cashier_role_can_work_the_floor_and_take_deliveries(): void
    {
        $owner = $this->createTenant();

        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'floorcashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));

        $this->getJson('/api/v1/tables')->assertOk();
        $this->getJson('/api/v1/deliveries')->assertOk();
        // Redrawing the floor plan is still a manager's job.
        $this->postJson('/api/v1/tables', ['name' => 'T9', 'capacity' => 2])->assertForbidden();
    }
}
