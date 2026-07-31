<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Notifications addressed to the current user or broadcast to the whole tenant. */
    public function index(Request $request)
    {
        $notifications = $this->scoped($request)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate($this->perPage($request, 20));

        return response()->json([
            'data' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'meta' => ['current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage(), 'total' => $notifications->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->scoped($request)->whereNull('read_at')->count()]]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $notification->update(['read_at' => now()]);

        return response()->json(['data' => ['id' => $notification->id, 'read_at' => $notification->read_at]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->scoped($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $count]]);
    }

    protected function scoped(Request $request)
    {
        $userId = $request->user()->id;

        return AppNotification::query()
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'));
    }
}
