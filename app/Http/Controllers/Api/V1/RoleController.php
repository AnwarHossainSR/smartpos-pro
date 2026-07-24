<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync(Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id'));

        return new RoleResource($role->load('permissions'));
    }

    public function update(StoreRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? $role->description]);

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync(Permission::whereIn('slug', $data['permissions'])->pluck('id'));
        }

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');
        $role->delete();

        return response()->noContent();
    }

    /** The full permission catalog, grouped by module — for building role editors. */
    public function permissions(): JsonResponse
    {
        return response()->json(['data' => PermissionCatalog::MATRIX]);
    }
}
