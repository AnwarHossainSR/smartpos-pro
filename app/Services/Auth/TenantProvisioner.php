<?php

namespace App\Services\Auth;

use App\Domain\Enums\UserStatus;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\Rbac\RbacProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bootstraps a brand-new tenant on registration: the tenant record, its first
 * business, a default branch + warehouse, the default role set, and the owner
 * user — all in one transaction.
 */
class TenantProvisioner
{
    public function __construct(protected TenantContext $context) {}

    /**
     * @param  array{name:string, email:string, password:string, business_name:string, business_type?:string, currency?:string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'plan' => 'free',
                'status' => 'active',
                'trial_ends_at' => null,
            ]);

            // Bind context so all subsequent tenant-scoped creates are isolated.
            $this->context->set($tenant->id);

            $business = Business::create([
                'tenant_id' => $tenant->id,
                'name' => $data['business_name'],
                'type' => $data['business_type'] ?? 'retail',
                'email' => $data['email'],
                'currency' => $data['currency'] ?? 'USD',
            ]);

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'is_default' => true,
            ]);

            Warehouse::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN',
                'is_default' => true,
            ]);

            $this->provisionRoles($tenant);
            $this->provisionChartOfAccounts();

            // No `tenant_id` here on purpose: it is not fillable on User (a deliberate
            // hardening choice — see the $fillable docblock) so passing it would be
            // silently dropped. BelongsToTenant fills it from the context bound above.
            $owner = User::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            $owner->assignRole('owner');

            $owner->sendEmailVerificationNotification();

            return $owner->load('roles', 'business', 'branch');
        });
    }

    /**
     * The default chart of accounts. Automatic bookkeeping is on by default and
     * posts against accounts looked up by name, so a tenant without these would
     * record nothing at all and never be told.
     */
    public function provisionChartOfAccounts(): void
    {
        foreach (ChartOfAccounts::defaults() as $account) {
            Account::updateOrCreate(
                ['account_number' => $account['account_number']],
                ['name' => $account['name'], 'type' => $account['type'], 'balance' => 0, 'is_active' => true],
            );
        }
    }

    /** Ensure the global permission rows exist, then create this tenant's roles. */
    public function provisionRoles(Tenant $tenant): void
    {
        app(RbacProvisioner::class)->provisionDefaultRoles($tenant->id);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
