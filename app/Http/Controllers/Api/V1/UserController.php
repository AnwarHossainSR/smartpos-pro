<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return UserResource::collection($users);
    }

    /** Invite/create a team member and assign roles. */
    public function store(StoreUserRequest $request): UserResource
    {
        app(PlanLimitService::class)->ensureCanCreate('users');

        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $data['business_id'] = $request->user()->business_id;
        $data['password'] = $data['password'] ?? Str::random(16);
        $data['status'] = $request->filled('password') ? 'active' : 'invited';

        $user = User::create($data);
        $this->syncRoles($user, $roles);

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);

        if ($roles !== null) {
            $this->syncRoles($user, $roles);
        }

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');
        abort_if($user->hasRole('owner'), 422, 'The business owner cannot be deleted.');

        $user->delete();

        return response()->noContent();
    }

    /** @param  string[]  $roleSlugs */
    protected function syncRoles(User $user, array $roleSlugs): void
    {
        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id');
        $user->roles()->sync($roleIds);
    }
}
