<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Billing\PlanLimitService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(protected AuditLogService $audit) {}

    public function index(Request $request)
    {
        // `permissions` + `roles.permissions` are what UserResource derives `can_approve`
        // and the `permissions[]` payload from; without them that is an N+1 per row.
        $users = QueryPipeline::for(User::query()->with(['permissions', 'roles.permissions']), $request)
            ->search(['name', 'email'])
            ->allow(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::enum('status', UserStatus::class),
            )
            ->sortable(['name', 'email', 'created_at'], default: 'name')
            ->paginate();

        return UserResource::collection($users);
    }

    /** Invite/create a team member and assign roles. */
    public function store(StoreUserRequest $request): UserResource
    {
        app(PlanLimitService::class)->ensureCanCreate('users');

        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        if (in_array('owner', $roles, true) && ! $this->canGrantOwner($request->user())) {
            throw new AuthorizationException('Only the business owner can grant the owner role.');
        }

        $invited = ! $request->filled('password');

        $data['business_id'] = $request->user()->business_id;
        // A random, unguessable placeholder — invited members never receive this; they
        // set their own password via the emailed link (same as a password reset).
        $data['password'] = $data['password'] ?? Str::random(40);
        $data['status'] = $invited ? UserStatus::Invited : UserStatus::Active;

        $user = User::create($data);
        $this->syncRoles($user, $roles, $request->user());

        if ($invited) {
            $token = Password::createToken($user);
            $user->notify(new SetPasswordNotification($token));
        }

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $actor = $request->user();
        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if ($roles !== null) {
            $this->guardRoleChange($actor, $user, $roles);
        }

        $this->guardBranchScope($actor, $data['branch_id'] ?? $user->branch_id);

        $user->update($data);

        if ($roles !== null) {
            $this->syncRoles($user, $roles, $actor);
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

    /** A caller may never change their own role assignment; only an owner/super-admin may grant or revoke `owner`. */
    protected function guardRoleChange(User $actor, User $user, array $roleSlugs): void
    {
        if ($actor->id === $user->id) {
            throw new AuthorizationException('You cannot change your own roles.');
        }

        $hadOwner = $user->hasRole('owner');
        $willHaveOwner = in_array('owner', $roleSlugs, true);

        if ($hadOwner !== $willHaveOwner && ! $this->canGrantOwner($actor)) {
            throw new AuthorizationException('Only the business owner can grant or revoke the owner role.');
        }
    }

    protected function canGrantOwner(User $actor): bool
    {
        return $actor->hasRole('owner') || (bool) $actor->is_super_admin;
    }

    /**
     * A caller whose role assignments are all pinned to specific branches
     * (rather than tenant-wide) may only manage users within those branches.
     */
    protected function guardBranchScope(User $actor, ?string $targetBranchId): void
    {
        if ($this->canGrantOwner($actor)) {
            return;
        }

        // `model_has_roles` carries a nullable `branch_id`: null means the grant is
        // tenant-wide, a value pins it to one branch.
        $roles = $actor->roles()->get();
        $isTenantWide = $roles->isEmpty() || $roles->contains(fn (Role $role) => $role->pivot->branch_id === null);

        if ($isTenantWide) {
            return;
        }

        $scopedBranchIds = $roles->pluck('pivot.branch_id')->filter()->all();

        if (! in_array($targetBranchId, $scopedBranchIds, true)) {
            throw new AuthorizationException('You cannot manage users outside your assigned branch.');
        }
    }

    /**
     * Role slugs live in `roles.name`. Going through spatie's `syncRoles()` rather than
     * a raw pivot sync is what stamps the tenant (team) id onto `model_has_roles` and
     * invalidates the permission cache.
     *
     * @param  string[]  $roleSlugs
     */
    protected function syncRoles(User $user, array $roleSlugs, ?User $actor = null): void
    {
        $before = $user->roles()->pluck('name')->sort()->values()->all();

        $user->syncRoles($roleSlugs);

        $after = Role::query()->whereIn('name', $roleSlugs)->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->log('user.roles_changed', $user, ['roles' => $before], ['roles' => $after], $actor);
        }
    }
}
