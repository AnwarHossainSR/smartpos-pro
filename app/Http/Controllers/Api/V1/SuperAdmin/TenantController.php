<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Domain\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TenantProvisioner;
use App\Support\Query\QueryPipeline;
use App\Support\Scopes\TenantScope;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Sanctum\PersonalAccessToken;

class TenantController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = QueryPipeline::for(
            Tenant::withCount([
                'users' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])->with(['businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]),
            $request,
        )
            ->search(['name', 'slug'])
            ->sortable(['name', 'created_at'], default: 'name')
            ->paginate();

        return TenantResource::collection($tenants);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $owner = app(TenantProvisioner::class)->register([
            'business_name' => $data['business_name'],
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => $data['owner_password'],
            'currency' => $data['base_currency'] ?? 'USD',
        ]);

        $tenant = $owner->tenant;
        $this->audit->log('tenant.created', $tenant, null, [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ], $request->user(), $tenant->id);

        return $this->created(new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validated();
        $oldValues = $tenant->only(array_keys($data));
        $tenant->update($data);

        $this->audit->log('tenant.updated', $tenant, $oldValues, $tenant->only(array_keys($data)), $request->user(), $tenant->id);

        $tenant->load(['businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        return $this->success(new TenantResource($tenant));
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount([
            'users' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $tenant->load(['businesses' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

        $users = User::withoutTenancy()->where('tenant_id', $tenant->id)
            ->with(['roles' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status?->value ?? $u->status,
                'roles' => $u->roles->pluck('name'),
                'created_at' => $u->created_at,
            ]);

        $businessIds = $tenant->businesses->pluck('id');

        $categoriesCount = Category::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $productsCount = Product::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $branchesCount = Branch::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $warehousesCount = Warehouse::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $recentSales = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->with(['business' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'total' => $s->total,
                'payment_status' => $s->payment_status,
                'status' => $s->status,
                'business_name' => $s->business?->name,
                'created_at' => $s->created_at,
            ]);

        $subscription = Subscription::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->with('plan')
            ->latest()
            ->first();

        $totalSales = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->count();

        $totalRevenue = Sale::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('payment_status', 'paid')
            ->sum('total');

        return $this->success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan' => $tenant->plan,
            'base_currency' => $tenant->base_currency,
            'trial_ends_at' => $tenant->trial_ends_at,
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
            'users_count' => $tenant->users_count,
            'businesses_count' => $tenant->businesses_count,
            'businesses' => $tenant->businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'currency' => $b->currency,
            ]),
            'users' => $users,
            'categories_count' => $categoriesCount,
            'products_count' => $productsCount,
            'branches_count' => $branchesCount,
            'warehouses_count' => $warehousesCount,
            'total_sales' => $totalSales,
            'total_revenue' => (float) $totalRevenue,
            'recent_sales' => $recentSales,
            'subscription' => $subscription,
        ]);
    }

    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => TenantStatus::Suspended]);

        $userIds = User::withoutTenancy()->where('tenant_id', $tenant->id)->pluck('id');
        PersonalAccessToken::where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();

        $this->audit->log('tenant.suspended', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return $this->success($tenant, 'Tenant suspended.');
    }

    public function activate(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => TenantStatus::Active]);

        $this->audit->log('tenant.activated', $tenant, null, ['tenant_id' => $tenant->id], $request->user(), $tenant->id);

        return $this->success($tenant, 'Tenant activated.');
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return $this->noContent();
    }

    public function metrics(): JsonResponse
    {
        return $this->success([
            'total_tenants' => Tenant::count(),
            'total_users' => User::withoutTenancy()->count(),
            'tenants_by_status' => Tenant::query()
                ->select('status', \DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
        ]);
    }
}
