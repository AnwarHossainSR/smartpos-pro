<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'impersonated_by', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Set only when the entry was written during a super-admin impersonation session. */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_by');
    }
}
