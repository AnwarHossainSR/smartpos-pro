<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /** Notifications addressed to the current user or broadcast to the whole tenant. */
    public function index(Request $request)
    {
        $notifications = QueryPipeline::for($this->scoped($request), $request)
            ->allow(AllowedFilter::flag('unread', fn (Builder $q) => $q->whereNull('read_at')))
            ->sortable(['id', 'created_at'], default: '-id')
            ->paginate(20);

        $items = method_exists($notifications, 'getCollection')
            ? $notifications->getCollection()
            : $notifications->items();

        return response()->json([
            'data' => collect($items)->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ])->all(),
            'meta' => ['current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage(), 'total' => $notifications->total()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread' => $this->scoped($request)->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        // The route-model binding is unscoped, so re-resolve through `scoped()`: a user
        // may only mark their own notifications (or tenant-wide broadcasts) as read.
        // Without this, POST /notifications/{any_id}/read was an IDOR that let any user
        // toggle read-state on another user's notification.
        $notification = $this->scoped($request)
            ->whereKey($notification->id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return $this->success(['id' => $notification->id, 'read_at' => $notification->read_at]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->scoped($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success(['marked' => $count]);
    }

    protected function scoped(Request $request)
    {
        $userId = $request->user()->id;

        return AppNotification::query()
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'));
    }
}
