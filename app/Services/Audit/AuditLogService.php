<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Single write path for the audit trail. Called explicitly for
 * non-CRUD/security events (price override, discount, impersonation, login,
 * ...) and by the `Auditable` model observer for CRUD events.
 */
class AuditLogService
{
    public function __construct(protected TenantContext $context) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        ?string $tenantId = null,
    ): AuditLog {
        $request = RequestFacade::instance();
        $actor ??= $request->user();

        return AuditLog::create([
            'tenant_id' => $tenantId ?? $this->context->id(),
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
