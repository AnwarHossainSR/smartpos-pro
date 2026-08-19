<?php

namespace Tests\Feature\Api;

use App\Domain\Pos\ApprovalPolicy;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\PosApproval;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pos\ApprovalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * T114 — supervisor approval PIN at the till.
 *
 * The acceptance criterion is end-to-end: a cashier over the discount limit is
 * blocked, a manager PIN unblocks the same request, and the approval lands in the
 * audit log with who approved what.
 */
class ManagerApprovalPinTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $stock = 50, float $price = 100): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'tax_rate' => 0,
            'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    private function member(User $owner, string $role, string $email): User
    {
        $user = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => ucfirst($role), 'email' => $email,
            'password' => 'password123', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user->fresh(['roles', 'business']);
    }

    /** A cashier who may discount, but only up to the tenant's limit. */
    private function cashier(User $owner, string $email = 'cashier@example.test'): User
    {
        $cashier = $this->member($owner, 'cashier', $email);
        $cashier->roles->first()->permissions()->syncWithoutDetaching(
            Permission::whereIn('slug', ['pos.discount', 'pos.price_override'])->pluck('id'),
        );

        return $cashier->fresh(['roles.permissions', 'business']);
    }

    private function managerWithPin(User $owner, string $pin = '4821', string $email = 'manager@example.test'): User
    {
        $manager = $this->member($owner, 'branch_manager', $email);
        app(ApprovalService::class)->setPin($manager, $pin);

        return $manager->fresh(['roles.permissions']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function setPolicy(User $owner, array $overrides): void
    {
        $business = $owner->business;
        $business->update([
            'settings' => array_merge($business->settings ?? [], [
                ApprovalPolicy::KEY => ApprovalPolicy::fromArray($overrides)->toArray(),
            ]),
        ]);
        $owner->unsetRelation('business');
    }

    /** @return array<string, mixed> */
    private function cart(User $owner, Product $product, float $lineDiscount): array
    {
        return [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $this->warehouseFor($owner)->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => $lineDiscount]],
            'payments' => [['method' => 'cash', 'amount' => 100 - $lineDiscount]],
        ];
    }

    public function test_cashier_over_the_discount_limit_is_blocked_with_the_numbers_to_challenge_on(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, [
            'max_discount_percent' => 10,
            'require_approval_for_price_override' => false,
            'require_approval_for_void' => false,
        ]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30))
            ->assertForbidden()
            ->assertJsonPath('code', 'approval_required')
            ->assertJsonPath('error.action', 'discount')
            ->assertJsonPath('error.requested_value', 30)
            ->assertJsonPath('error.limit_value', 10);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
    }

    public function test_a_discount_within_the_limit_needs_no_approval(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 10))->assertCreated();
    }

    public function test_manager_pin_unblocks_the_sale_and_the_approval_is_audited(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $manager = $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);

        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount',
            'pin' => '4821',
            'requested_value' => 30,
            'limit_value' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.approved_by.name', 'Branch_manager')
            ->json('data.token');

        $this->assertIsString($token);

        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30) + ['approval_token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.total', 70);

        app(TenantContext::class)->set($owner->tenant_id);

        $granted = AuditLog::where('action', 'pos.approval_granted')->firstOrFail();
        $this->assertSame($manager->id, $granted->user_id, 'the audit row records the approver, not the cashier');
        $this->assertSame($cashier->id, $granted->new_values['requested_by']);
        $this->assertSame(30.0, (float) $granted->new_values['requested_value']);
        $this->assertSame(10.0, (float) $granted->new_values['limit_value']);
        $this->assertSame('discount', $granted->new_values['action']);
    }

    public function test_a_wrong_pin_is_rejected_and_audited(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount', 'pin' => '9999', 'requested_value' => 30, 'limit_value' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('pin');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, PosApproval::count());

        $denied = AuditLog::where('action', 'pos.approval_denied')->firstOrFail();
        $this->assertSame($cashier->id, $denied->new_values['requested_by']);
        $this->assertSame('invalid_pin', $denied->new_values['reason']);
    }

    public function test_a_pin_belonging_to_someone_without_pos_approve_never_authorises_anything(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $cashier = $this->cashier($owner);

        // A second cashier sets a PIN. Having a PIN is not the same as being allowed
        // to approve — the permission is what makes the PIN mean anything.
        $other = $this->cashier($owner, 'other-cashier@example.test');
        app(ApprovalService::class)->setPin($other, '1357');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount', 'pin' => '1357', 'requested_value' => 30, 'limit_value' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    public function test_an_approval_cannot_be_replayed_on_a_second_sale(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);
        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount', 'pin' => '4821', 'requested_value' => 30, 'limit_value' => 10,
        ])->assertCreated()->json('data.token');

        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30) + ['approval_token' => $token])->assertCreated();

        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30) + ['approval_token' => $token])
            ->assertForbidden()
            ->assertJsonPath('code', 'approval_required');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
        $this->assertNotNull(PosApproval::firstOrFail()->consumed_at);
    }

    public function test_an_approval_cannot_be_stretched_beyond_the_value_it_was_granted_for(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);
        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount', 'pin' => '4821', 'requested_value' => 20, 'limit_value' => 10,
        ])->assertCreated()->json('data.token');

        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 60) + ['approval_token' => $token])
            ->assertForbidden()
            ->assertJsonPath('code', 'approval_required');
    }

    public function test_an_approval_for_one_action_does_not_authorise_another(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);
        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'void', 'pin' => '4821', 'requested_value' => 100,
        ])->assertCreated()->json('data.token');

        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30) + ['approval_token' => $token])
            ->assertForbidden();
    }

    public function test_a_manager_is_never_challenged_for_their_own_over_limit_discount(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $manager = $this->member($owner, 'branch_manager', 'boss@example.test');

        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 40))->assertCreated();
    }

    public function test_a_price_override_can_be_put_behind_the_challenge(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, [
            'max_discount_percent' => 100,
            'require_approval_for_price_override' => true,
            'require_approval_for_void' => false,
        ]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        $body = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $this->warehouseFor($owner)->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 60]],
            'payments' => [['method' => 'cash', 'amount' => 60]],
        ];

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', $body)
            ->assertForbidden()
            ->assertJsonPath('error.action', 'price_override')
            ->assertJsonPath('error.requested_value', 40);

        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'price_override', 'pin' => '4821', 'requested_value' => 40,
        ])->assertCreated()->json('data.token');

        $this->postJson('/api/v1/sales', $body + ['approval_token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.total', 60);
    }

    public function test_a_void_can_be_put_behind_the_challenge(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, [
            'max_discount_percent' => 100,
            'require_approval_for_price_override' => false,
            'require_approval_for_void' => true,
        ]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);
        $cashier->roles->first()->permissions()->syncWithoutDetaching(
            Permission::where('slug', 'sales.delete')->pluck('id'),
        );
        $cashier = $cashier->fresh(['roles.permissions', 'business']);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);
        $saleId = $this->postJson('/api/v1/sales', $this->cart($owner, $product, 0))
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'wrong item'])
            ->assertForbidden()
            ->assertJsonPath('error.action', 'void');

        $token = $this->postJson('/api/v1/pos/approvals', [
            'action' => 'void', 'pin' => '4821', 'requested_value' => 100, 'sale_id' => $saleId,
        ])->assertCreated()->json('data.token');

        $this->postJson("/api/v1/sales/{$saleId}/void", ['reason' => 'wrong item', 'approval_token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_pin_from_another_tenant_is_not_accepted(): void
    {
        $owner = $this->createTenant('tenant-a@example.test');
        $this->setPolicy($owner, ['max_discount_percent' => 10, 'require_approval_for_price_override' => false, 'require_approval_for_void' => false]);
        $cashier = $this->cashier($owner, 'cashier-a@example.test');

        // A manager in a completely separate business, with the very same PIN.
        $otherOwner = $this->createTenant('tenant-b@example.test');
        $this->managerWithPin($otherOwner, '4821', 'manager-b@example.test');

        app(TenantContext::class)->set($owner->tenant_id);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/pos/approvals', [
            'action' => 'discount', 'pin' => '4821', 'requested_value' => 30, 'limit_value' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    public function test_repeated_wrong_pins_lock_the_terminal_out(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->cashier($owner);
        $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($cashier);

        for ($i = 0; $i < ApprovalService::MAX_ATTEMPTS; $i++) {
            $this->postJson('/api/v1/pos/approvals', ['action' => 'discount', 'pin' => '0000'])
                ->assertStatus(422);
        }

        // Even the correct PIN is refused while the lockout stands.
        $this->postJson('/api/v1/pos/approvals', ['action' => 'discount', 'pin' => '4821'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, PosApproval::count());
        $this->assertSame(1, AuditLog::where('action', 'pos.approval_locked')->count());
    }

    public function test_a_user_sets_and_clears_their_own_pin_with_their_password(): void
    {
        $owner = $this->createTenant();
        $manager = $this->member($owner, 'branch_manager', 'manager@example.test');

        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/auth/approval-pin', [
            'current_password' => 'wrong', 'pin' => '1234', 'pin_confirmation' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/auth/approval-pin', [
            'current_password' => 'password123', 'pin' => '12', 'pin_confirmation' => '12',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->putJson('/api/v1/auth/approval-pin', [
            'current_password' => 'password123', 'pin' => '1234', 'pin_confirmation' => '1234',
        ])->assertOk()->assertJsonPath('data.has_approval_pin', true);

        app(TenantContext::class)->set($owner->tenant_id);
        $stored = User::findOrFail($manager->id)->approval_pin;
        $this->assertNotSame('1234', $stored, 'the PIN must never be stored in the clear');
        $this->assertTrue(Hash::check('1234', (string) $stored));

        $this->deleteJson('/api/v1/auth/approval-pin', ['current_password' => 'password123'])
            ->assertOk()->assertJsonPath('data.has_approval_pin', false);

        $this->assertNull(User::findOrFail($manager->id)->approval_pin);
    }

    public function test_the_pin_hash_is_never_returned_by_the_api(): void
    {
        $owner = $this->createTenant();
        $manager = $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($owner);
        $body = $this->getJson("/api/v1/users/{$manager->id}")->assertOk();

        $body->assertJsonPath('data.has_approval_pin', true);
        $this->assertStringNotContainsString('approval_pin"', str_replace('has_approval_pin"', '', $body->getContent()));
    }

    public function test_an_admin_can_revoke_someone_elses_pin_but_never_set_one(): void
    {
        $owner = $this->createTenant();
        $manager = $this->managerWithPin($owner, '4821');

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/users/{$manager->id}/approval-pin")
            ->assertOk()->assertJsonPath('data.has_approval_pin', false);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertNull(User::findOrFail($manager->id)->approval_pin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.approval_pin_cleared']);
    }

    public function test_a_cashier_cannot_revoke_a_managers_pin(): void
    {
        $owner = $this->createTenant();
        $manager = $this->managerWithPin($owner, '4821');
        $cashier = $this->cashier($owner);

        Sanctum::actingAs($cashier);
        $this->deleteJson("/api/v1/users/{$manager->id}/approval-pin")->assertForbidden();
    }

    public function test_the_tenant_limits_are_readable_and_writable_from_settings(): void
    {
        $owner = $this->createTenant();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/settings/approvals')
            ->assertOk()
            ->assertJsonPath('data.max_discount_percent', 100)
            ->assertJsonPath('data.require_approval_for_void', false);

        $this->putJson('/api/v1/settings/approvals', [
            'max_discount_percent' => 15,
            'max_discount_amount' => 500,
            'require_approval_for_price_override' => true,
            'require_approval_for_void' => true,
        ])->assertOk()->assertJsonPath('data.max_discount_percent', 15);

        $this->getJson('/api/v1/pos/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.approval_policy.max_discount_percent', 15)
            ->assertJsonPath('data.can_approve', true);
    }

    public function test_an_absolute_discount_ceiling_is_enforced_alongside_the_percentage(): void
    {
        $owner = $this->createTenant();
        $this->setPolicy($owner, [
            'max_discount_percent' => 100,
            'max_discount_amount' => 25,
            'require_approval_for_price_override' => false,
            'require_approval_for_void' => false,
        ]);
        $product = $this->seedProduct($this->warehouseFor($owner)->id);
        $cashier = $this->cashier($owner);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 20))->assertCreated();
        $this->postJson('/api/v1/sales', $this->cart($owner, $product, 30))
            ->assertForbidden()
            ->assertJsonPath('error.limit_value', 25);
    }
}
