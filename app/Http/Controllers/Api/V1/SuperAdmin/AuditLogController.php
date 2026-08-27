<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Scopes\TenantScope;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function auditLogs(IndexAuditLogRequest $request): JsonResponse
    {
        $unscopedUser = fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'email');

        $query = AuditLog::withoutTenancy()
            ->with(['user' => $unscopedUser, 'impersonator' => $unscopedUser])
            ->with('tenant:id,name');

        $filters = $request->validated();

        foreach (['action', 'user_id', 'tenant_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        // Only impersonated entries — the question "what did support touch?" is the
        // one this view exists to answer and it had no way to ask it.
        if ($request->boolean('impersonated_only')) {
            $query->whereNotNull('impersonated_by');
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 30);

        return $this->success(AuditLogResource::collection($logs));
    }
}
