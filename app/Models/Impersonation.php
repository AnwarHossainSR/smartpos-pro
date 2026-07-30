<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records who impersonated whom, why, and for how long — not tenant-scoped since it's a super-admin resource. */
class Impersonation extends Model
{
    protected $fillable = [
        'tenant_id', 'super_admin_id', 'target_user_id', 'personal_access_token_id',
        'reason', 'started_at', 'ended_at', 'ended_by',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
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
