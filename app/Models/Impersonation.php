<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records who impersonated whom, why, and for how long — not tenant-scoped since it's a super-admin resource. */
class Impersonation extends Model
{
    use HasUuids;

    /**
     * How long an impersonation token stays valid. The `Impersonation` row is the
     * audit record; the Sanctum token is the credential, and it is minted with this
     * same expiry — so a row past this age describes a session that can no longer
     * act, whether or not anyone remembered to press "stop".
     */
    public const EXPIRES_AFTER_MINUTES = 30;

    protected $fillable = [
        'tenant_id', 'super_admin_id', 'target_user_id', 'personal_access_token_id',
        'reason', 'started_at', 'ended_at', 'ended_by',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * Sessions that are genuinely still live. `ended_at IS NULL` alone is not enough:
     * a session that simply timed out never gets stopped explicitly, so filtering on
     * that column alone leaves the "who is in a customer account right now" screen
     * full of sessions that ended weeks ago.
     */
    public function scopeStillActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at')
            ->where('started_at', '>', now()->subMinutes(self::EXPIRES_AFTER_MINUTES));
    }

    /** Rows whose token has expired but which were never closed out. */
    public function scopeLapsed(Builder $query): Builder
    {
        return $query->whereNull('ended_at')
            ->where('started_at', '<=', now()->subMinutes(self::EXPIRES_AFTER_MINUTES));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
