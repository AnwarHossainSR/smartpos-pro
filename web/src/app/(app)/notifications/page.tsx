import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Bell, CheckCheck } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";
import { notifTitle, notifBody, type NotificationResponse } from "@/lib/notifications";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";

export default function NotificationsPage() {
  const qc = useQueryClient();

  const { data, isLoading } = useQuery<NotificationResponse>({
    queryKey: ["notifications"],
    queryFn: () => api<NotificationResponse>("/notifications?per_page=50"),
  });

  const markAllMutation = useMutation({
    mutationFn: () => api("/notifications/read-all", { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["notifications"] });
      toast("All notifications marked as read");
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const markOneMutation = useMutation({
    mutationFn: (id: string) => api(`/notifications/${id}/read`, { method: "POST" }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const notifications = data?.data ?? [];
  const unread = data?.unread_count ?? 0;

  return (
    <div className="max-w-4xl space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          {unread > 0 && <Badge tone="danger">{unread} unread</Badge>}
        </div>
        {unread > 0 && (
          <Button variant="outline" size="sm" onClick={() => markAllMutation.mutate()} disabled={markAllMutation.isPending}>
            <CheckCheck className="h-4 w-4" />
            Mark all read
          </Button>
        )}
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] divide-y divide-[var(--border)]">
        {isLoading ? (
          Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex gap-3 p-4">
              <Skeleton className="h-8 w-8 rounded-full shrink-0" />
              <div className="flex-1 space-y-1.5">
                <Skeleton className="h-4 w-48" />
                <Skeleton className="h-3 w-64" />
              </div>
            </div>
          ))
        ) : notifications.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-16 text-[var(--muted)]">
            <Bell className="h-10 w-10 opacity-30" />
            <p className="text-sm">No notifications yet</p>
          </div>
        ) : (
          notifications.map((n) => (
            <div
              key={n.id}
              className={`flex items-start gap-3 p-4 cursor-pointer transition-colors hover:bg-[var(--surface-2)] ${!n.read_at ? "bg-[var(--primary)]/5" : ""}`}
              onClick={() => !n.read_at && markOneMutation.mutate(n.id)}
            >
              <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm ${!n.read_at ? "bg-[var(--primary)] text-[var(--primary-fg)]" : "bg-[var(--surface-2)] text-[var(--muted)]"}`}>
                <Bell className="h-4 w-4" />
              </div>
              <div className="flex-1 min-w-0">
                <p className={`text-sm ${!n.read_at ? "font-semibold" : "font-medium"}`}>{notifTitle(n)}</p>
                {notifBody(n) && <p className="mt-0.5 text-xs text-[var(--muted)] truncate">{notifBody(n)}</p>}
                <p className="mt-1 text-xs text-[var(--muted)]">{formatDateTime(n.created_at)}</p>
              </div>
              {!n.read_at && <div className="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--primary)]" />}
            </div>
          ))
        )}
      </div>
    </div>
  );
}
