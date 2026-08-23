<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Http\Resources\TicketReplyResource;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\Scopes\TenantScope;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    use ApiResponse;

    private function reloadTicket(string $id)
    {
        return SupportTicket::withoutTenancy()
            ->with([
                'user:id,name,email',
                'assignedTo' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
            ])
            ->withCount('replies')
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $query = SupportTicket::withoutTenancy()
            ->with([
                'user:id,name,email',
                'assignedTo' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
            ])
            ->withCount('replies');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($tenantId = $request->input('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return $this->success(SupportTicketResource::collection($tickets));
    }

    public function show(string $id)
    {
        return $this->success(new SupportTicketResource($this->reloadTicket($id)));
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', SupportTicket::STATUSES),
        ]);

        $ticket = SupportTicket::withoutTenancy()->findOrFail($id);
        $ticket->update(['status' => $request->input('status')]);

        return $this->success(new SupportTicketResource($this->reloadTicket($id)));
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'assigned_to' => 'required|string|exists:users,id',
        ]);

        $ticket = SupportTicket::withoutTenancy()->findOrFail($id);
        $ticket->update(['assigned_to' => $request->input('assigned_to')]);

        return $this->success(new SupportTicketResource($this->reloadTicket($id)));
    }

    public function unassign(string $id): JsonResponse
    {
        $ticket = SupportTicket::withoutTenancy()->findOrFail($id);
        $ticket->update(['assigned_to' => null]);

        return $this->success(new SupportTicketResource($this->reloadTicket($id)));
    }

    public function addInternalNote(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = SupportTicket::withoutTenancy()->findOrFail($id);
        $user = $request->user();

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'is_internal' => true,
        ]);

        $ticket->update(['last_reply_at' => now()]);

        return $this->success(new TicketReplyResource($reply->load('user:id,name')), 'Note added', 201);
    }

    public function agents()
    {
        $agents = User::withoutTenancy()
            ->select('id', 'name', 'email')
            ->where('role', 'admin')
            ->get();

        return $this->success($agents);
    }

    public function metrics(Request $request)
    {
        $query = SupportTicket::withoutTenancy();

        if ($tenantId = $request->input('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $metrics = [
            'total_open' => (clone $query)->where('status', 'open')->count(),
            'total_in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'total_waiting' => (clone $query)->where('status', 'waiting_on_customer')->count(),
            'total_resolved' => (clone $query)->where('status', 'resolved')->count(),
            'total_closed' => (clone $query)->where('status', 'closed')->count(),
            'sla_breached' => (clone $query)->where('status', '!=', 'resolved')
                ->where('status', '!=', 'closed')
                ->where('sla_due_at', '<', now())
                ->count(),
        ];

        return $this->success($metrics);
    }
}
