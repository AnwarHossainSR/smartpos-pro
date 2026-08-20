<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Query\QueryPipeline;
use App\Support\Rbac\PermissionCatalog;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Roles are tenant-scoped; the global tenant scope on the model plus spatie's team id
 * (bound from TenantContext) keep every query and every route binding inside the
 * caller's business.
 *
 * Note the column split: `name` is the immutable machine slug the API and route guards
 * speak, `title` is the editable human label.
 */
class RoleController extends Controller
{
    public function __construct(protected RbacProvisioner $rbac) {}

    public function index(Request $request)
    {
        $roles = QueryPipeline::for(Role::query()->with('permissions')->withCount('users'), $request)
            ->search(['title', 'name'])
            ->sortable(['title', 'name', 'created_at'], default: 'title')
            ->paginate(50);

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $data = $request->validated();

        $role = Role::create([
            // The slug is generated, never client-supplied: it is what `hasRole()` and
            // the permission middleware match on, so it must stay stable and unique.
            'name' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'title' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return new RoleResource($role->load('permissions'));
    }

    public function update(StoreRoleRequest $request, Role $role): RoleResource
    {
        $actor = $request->user();

        if ($role->name === 'owner' && ! ($actor->hasRole('owner') || $actor->is_super_admin)) {
            throw new AuthorizationException('Only the business owner can modify the owner role.');
        }

        $data = $request->validated();
        $role->update(['title' => $data['name'], 'description' => $data['description'] ?? $role->description]);

        if (array_key_exists('permissions', $data)) {
            $this->syncPermissions($role, $data['permissions']);
        }

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');
        $role->delete();

        $this->rbac->forgetCachedPermissions();

        return response()->noContent();
    }

    /** The full permission catalog, grouped by module — for building role editors. */
    public function permissions(): JsonResponse
    {
        return response()->json(['data' => PermissionCatalog::MATRIX]);
    }

    /**
     * Writing the pivot directly fires no model events, so spatie's cache would keep
     * serving the old grants until it expired — flush it explicitly.
     *
     * @param  string[]  $slugs
     */
    protected function syncPermissions(Role $role, array $slugs): void
    {
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $slugs)->pluck('id')
        );

        $this->rbac->forgetCachedPermissions();
    }
}
