<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\ImpersonationStartedMail;
use App\Models\AppNotification;
use App\Models\Impersonation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Support\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

class ImpersonationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function impersonate($tenant, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $admin = $request->user();
        $tenantId = is_string($tenant) ? $tenant : $tenant->id;
        $tenantModel = is_string($tenant) ? Tenant::findOrFail($tenant) : $tenant;

        $owner = app(TenantContext::class)->runAs($tenantId, fn () => User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('name', 'owner'))
            ->firstOrFail());

        $expiresAt = now()->addMinutes(Impersonation::EXPIRES_AFTER_MINUTES);
        $token = $owner->createToken(
            name: 'impersonation:super-admin:'.$admin->id,
            abilities: ['impersonate'],
            expiresAt: $expiresAt,
        );

        $impersonation = Impersonation::create([
            'tenant_id' => $tenantId,
            'super_admin_id' => $admin->id,
            'target_user_id' => $owner->id,
            'personal_access_token_id' => $token->accessToken->id,
            'reason' => $data['reason'],
            'started_at' => now(),
        ]);

        $this->audit->log('impersonation.started', $impersonation, null, [
            'tenant_id' => $tenantId,
            'target_user_id' => $owner->id,
            'reason' => $data['reason'],
        ], $admin, $tenantId);

        AppNotification::create([
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'type' => 'impersonation.started',
            'title' => 'Support accessed your account',
            'body' => "{$admin->name} started a support session on your account. Reason: {$data['reason']}",
            'data' => ['admin_name' => $admin->name, 'reason' => $data['reason']],
        ]);

        Mail::to($owner)->queue(new ImpersonationStartedMail($owner, $data['reason'], $admin->name));

        return $this->created([
            'impersonation_id' => $impersonation->id,
            'token' => $token->plainTextToken,
            'tenant' => $tenantModel->only('id', 'name', 'slug'),
            'user' => $owner->only('id', 'name', 'email'),
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }

    /** Called from inside the impersonated session, with the impersonation token itself. */
    public function stopImpersonation(Request $request): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()?->id;

        $this->endImpersonation($tokenId, $request->user());

        return $this->success(null, 'Impersonation ended.');
    }

    public function revokeImpersonation(Request $request, string $impersonation): JsonResponse
    {
        $record = Impersonation::whereKey($impersonation)->whereNull('ended_at')->firstOrFail();

        $this->endImpersonation($record->personal_access_token_id, $request->user(), $record);

        return $this->success(null, 'Impersonation revoked.');
    }

    public function activeImpersonations(): JsonResponse
    {
        $records = Impersonation::stillActive()
            ->with([
                'tenant:id,name',
                'superAdmin' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'email'),
                'targetUser' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'email'),
            ])
            ->latest('started_at')
            ->get();

        return $this->success($records);
    }

    protected function endImpersonation(?string $tokenId, User $endedBy, ?Impersonation $record = null): void
    {
        $record ??= $tokenId
            ? Impersonation::where('personal_access_token_id', $tokenId)->whereNull('ended_at')->first()
            : null;

        if ($tokenId) {
            PersonalAccessToken::whereKey($tokenId)->delete();
        }

        if ($record) {
            $record->update(['ended_at' => now(), 'ended_by' => $endedBy->id]);

            $this->audit->log('impersonation.stopped', $record, null, [
                'tenant_id' => $record->tenant_id,
                'target_user_id' => $record->target_user_id,
            ], $endedBy, $record->tenant_id);
        }
    }
}
