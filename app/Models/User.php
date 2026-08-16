<?php

namespace App\Models;

use App\Domain\Enums\UserStatus;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasRoles;
use App\Support\Concerns\HasShortUuids;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasShortUuids, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    /**
     * `tenant_id` and `is_super_admin` are deliberately excluded — tenant_id is filled
     * automatically from TenantContext (see BelongsToTenant), and is_super_admin must
     * only ever be set explicitly (forceFill), never through mass assignment.
     */
    protected $fillable = [
        'business_id', 'branch_id', 'name', 'email', 'phone',
        'password', 'avatar_path', 'status', 'last_login_at',
        'email_verification_code', 'email_verification_expires_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

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
        ];
    }

    public function hasEnabledTwoFactor(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
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
