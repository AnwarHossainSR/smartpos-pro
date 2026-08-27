<?php

namespace App\Services\Billing;

use App\Domain\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Enforces per-plan resource limits for the current tenant. The active plan is
 * resolved from the tenant's subscription; when none exists a generous "free"
 * default applies. A null limit means unlimited.
 */
class PlanLimitService
{
    private const FREE_LIMITS = [
        'branches' => 2,
        'warehouses' => 3,
        'users' => 5,
        'products' => 200,
    ];

    public function __construct(protected TenantContext $context) {}

    /**
     * @return array<string, int|null>
     *
     * Two things this must not do, both of which it used to:
     *   - order by `id`. Subscriptions are UUID-keyed, so `latest('id')` sorts a random
     *     string and "the newest subscription" was whichever UUID happened to sort high.
     *   - ignore status. A cancelled subscription kept handing out its plan's limits.
     */
    public function limits(): array
    {
        $subscription = Subscription::with('plan')
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->latest()
            ->first();

        return $subscription?->plan?->limits ?? self::FREE_LIMITS;
    }

    /** Throws a 422 if creating one more of $resource would exceed the plan limit. */
    public function ensureCanCreate(string $resource): void
    {
        $limit = $this->limits()[$resource] ?? null;

        if ($limit === null) {
            return; // unlimited
        }

        if ($this->currentCount($resource) >= $limit) {
            throw ValidationException::withMessages([
                'plan' => ["Your plan's limit of {$limit} {$resource} has been reached. Upgrade to add more."],
            ]);
        }
    }

    public function currentCount(string $resource): int
    {
        return match ($resource) {
            'branches' => Branch::count(),
            'warehouses' => Warehouse::count(),
            'users' => User::count(),
            'products' => Product::count(),
            default => 0,
        };
    }

    /** Snapshot of usage vs limits for the billing/usage screen. */
    public function usage(): array
    {
        $limits = $this->limits();
        $usage = [];

        foreach (['branches', 'warehouses', 'users', 'products'] as $resource) {
            $usage[$resource] = [
                'used' => $this->currentCount($resource),
                'limit' => $limits[$resource] ?? null,
            ];
        }

        return $usage;
    }
}
