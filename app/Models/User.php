<?php

namespace App\Models;

use App\Domain\Enums\UserStatus;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Rbac\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasApiTokens, HasFactory, HasUuids, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    // Aliased rather than plain `use HasRoles` so `roles()` below can extend the
    // package's relation: `parent::` cannot reach a trait method.
    use HasRoles {
        roles as protected spatieRoles;
    }

    /**
     * `tenant_id` and `is_super_admin` are deliberately excluded — tenant_id is filled
     * automatically from TenantContext (see BelongsToTenant), and is_super_admin must
     * only ever be set explicitly (forceFill), never through mass assignment.
     */
    protected $fillable = [
        'business_id', 'branch_id', 'name', 'email', 'phone',
        'password', 'avatar_path', 'status', 'last_login_at',
        'email_verification_code', 'email_verification_expires_at', 'theme',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'approval_pin'];

    /**
     * The guard roles and permissions are keyed by. Pinned rather than derived from
     * `auth.defaults.guard`, which `auth:sanctum` rewrites per request — see
     * {@see Role::$guard_name}.
     */
    protected $guard_name = 'web';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'approval_pin_set_at' => 'datetime',
            'theme' => 'array',
        ];
    }

    /**
     * spatie's `roles` relation, plus the `branch_id` pivot column this app carries on
     * `model_has_roles` — a role grant may be pinned to one branch rather than the
     * whole tenant (see UserController::guardBranchScope).
     */
    public function roles(): BelongsToMany
    {
        return $this->spatieRoles()->withPivot('branch_id');
    }

    /**
     * Does this user hold `<module>.<action>`?
     *
     * Thin, forgiving wrapper over spatie's permission check, kept because it is the
     * vocabulary the whole app speaks (`CheckPermission` middleware, `ApprovalService`,
     * policies). Two short circuits survive from the hand-rolled implementation:
     * platform super admins bypass everything, and a business owner implicitly holds
     * every permission inside their own tenant.
     *
     * Unlike `hasPermissionTo()` this never throws for an unknown slug — callers such
     * as the middleware treat the answer as a plain boolean, and a typo in a route
     * guard must fail closed (403), not 500.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->is_super_admin ?? false) {
            return true;
        }

        if ($this->hasRole('owner')) {
            return true;
        }

        return $this->checkPermissionTo($slug);
    }

    /**
     * Every permission slug this user holds — the `permissions[]` array the frontend
     * builds its `can()` checks from, and the ability list minted onto API tokens.
     *
     * Super admins and owners hold everything implicitly (`hasPermission()` above), so
     * they get the whole catalogue rather than whatever happens to be in their pivot
     * rows; anything less would have the UI hide features the API would in fact allow.
     *
     * @return string[]
     */
    public function permissionSlugs(): array
    {
        if (($this->is_super_admin ?? false) || $this->hasRole('owner')) {
            return array_column(PermissionCatalog::all(), 'slug');
        }

        $this->loadMissing('permissions', 'roles.permissions');

        return $this->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function hasEnabledTwoFactor(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /** Whether this user has a supervisor PIN they can authorise till actions with. */
    public function hasApprovalPin(): bool
    {
        return $this->approval_pin !== null;
    }

    /** A PIN only authorises anything if its holder actually carries the approval permission. */
    public function canApproveAtPos(): bool
    {
        return $this->hasApprovalPin() && $this->hasPermission('pos.approve');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Overrides MustVerifyEmailTrait to print the link to the terminal in local dev instead of emailing it. */
    public function sendEmailVerificationNotification(): void
    {
        $this->generateEmailVerificationOtp();
        $this->notify(new VerifyEmail($this->email_verification_code));
    }

    public function generateEmailVerificationOtp(): string
    {
        $code = str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
        ])->save();

        return $code;
    }

    public function verifyEmailWithOtp(string $code): bool
    {
        if (
            $this->email_verification_code === null ||
            $this->email_verification_expires_at === null ||
            $this->email_verification_expires_at->isPast()
        ) {
            return false;
        }

        if (! hash_equals($this->email_verification_code, $code)) {
            return false;
        }

        $this->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return true;
    }

    /** Overrides Authenticatable to print the link to the terminal in local dev instead of emailing it. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
