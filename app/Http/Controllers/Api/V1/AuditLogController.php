<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    /** Immutable audit trail. Filters: actor (user id), action, model, from/to (date). */
    public function index(Request $request)
    {
        $logs = QueryPipeline::for(AuditLog::query()->with('user:id,name,email'), $request)
            ->allow(
                AllowedFilter::exact('actor', 'user_id'),
                AllowedFilter::partial('action'),
                AllowedFilter::exact('model', 'auditable_type'),
                AllowedFilter::dateRange('created_at'),
            )
            ->sortable(['id', 'created_at'], default: '-id')
            ->paginate();

        return response()->json([
            'data' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'auditable_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
                'created_at' => $log->created_at,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
