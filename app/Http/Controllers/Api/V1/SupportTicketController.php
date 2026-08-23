<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Http\Resources\TicketReplyResource;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Scopes\TenantScope;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::where('user_id', $user->id)
            ->with('assignedTo:id,name')
            ->withCount('replies');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return SupportTicketResource::collection($tickets);
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $ticket = SupportTicket::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'medium',
        ]);

        return $this->success(new SupportTicketResource($ticket->load('assignedTo:id,name')), 'Ticket created', 201);
    }

    public function show(Request $request, string $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->with([
                'user:id,name,email',
                'replies' => fn ($q) => $q->orderBy('created_at', 'asc')->with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name')]),
            ])
            ->withCount('replies')
            ->findOrFail($id);

        return $this->success(new SupportTicketResource($ticket));
    }

    public function replies(Request $request, string $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        $replies = TicketReply::where('ticket_id', $ticket->id)
            ->where('is_internal', false)
            ->with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name')])
            ->orderBy('created_at', 'asc')
            ->paginate($request->input('per_page', 50));

        return TicketReplyResource::collection($replies);
    }

    public function reply(ReplyTicketRequest $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->validated('message'),
            'is_internal' => false,
        ]);

        $ticket->update(['last_reply_at' => now()]);

        return $this->success(new TicketReplyResource($reply->load('user:id,name')), 'Reply sent', 201);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return $this->success(message: 'Ticket closed');
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $ticket->update(['status' => 'open']);

        return $this->success(message: 'Ticket reopened');
    }
}
