<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Impersonation;
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
    /**
     * Memoised per request: `impersonatedBy()` is consulted on every single audit
     * write, and the answer cannot change within one request. `false` is the
     * "not looked up yet" marker, since `null` is a legitimate answer.
     */
    private string|null|false $impersonatorId = false;

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
            'impersonated_by' => $this->impersonatedBy(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * The super admin behind the current request, if this is an impersonated session.
     *
     * An impersonation token belongs to the tenant *owner* — that is what makes the
     * session work — so `user_id` alone records the customer as the author of changes
     * support made on their behalf. This is the column that tells them apart.
     */
    private function impersonatedBy(): ?string
    {
        if ($this->impersonatorId !== false) {
            return $this->impersonatorId;
        }

        $tokenId = RequestFacade::instance()->user()?->currentAccessToken()?->id;

        if (! $tokenId) {
            return $this->impersonatorId = null;
        }

        return $this->impersonatorId = Impersonation::query()
            ->where('personal_access_token_id', $tokenId)
            ->whereNull('ended_at')
            ->value('super_admin_id');
    }
}
