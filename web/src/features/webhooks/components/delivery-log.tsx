import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { formatDateTime } from "@/lib/utils";
import { RotateCcw } from "lucide-react";
import { useState } from "react";

interface Delivery {
  id: string;
  webhook_id: string;
  event: string;
  payload: Record<string, unknown>;
  response_code: number | null;
  response_body: string | null;
  delivered_at: string | null;
  attempts: number;
  created_at: string;
}

export function DeliveryLog() {
  const t = useTranslation();
  const qc = useQueryClient();
  const [expandedId, setExpandedId] = useState<string | null>(null);

  const { data, isLoading } = useQuery<{ data: Delivery[] }>({
    queryKey: queryKeys.webhookDeliveries.all,
    queryFn: () => api<{ data: Delivery[] }>("/webhook-deliveries?per_page=50"),
  });

  const retryMutation = useMutation({
    mutationFn: (id: string) =>
      api<{ data: Delivery }>(`/webhook-deliveries/${id}/retry`, { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.webhookDeliveries.all });
      toast(t("webhooks.retryQueued"));
    },
    onError: (e: unknown) =>
      toast(e instanceof ApiError ? e.message : t("webhooks.retryFailed"), "error"),
  });

  const columns: DataTableColumn<Delivery>[] = [
    {
      key: "event",
      header: t("webhooks.event"),
      render: (row) => (
        <Badge tone="info" className="text-[10px]">
          {row.event}
        </Badge>
      ),
    },
    {
      key: "status",
      header: t("webhooks.status"),
      render: (row) => {
        if (!row.delivered_at) return <Badge tone="warning">{t("webhooks.pending")}</Badge>;
        const code = row.response_code ?? 0;
        const ok = code >= 200 && code < 300;
        return (
          <Badge tone={ok ? "success" : "danger"}>
            {code || t("webhooks.error")}
          </Badge>
        );
      },
    },
    {
      key: "attempts",
      header: t("webhooks.attempts"),
      render: (row) => <span className="text-sm">{row.attempts}</span>,
    },
    {
      key: "delivered_at",
      header: t("webhooks.deliveredAt"),
      render: (row) => (
        <span className="text-sm text-[var(--muted)]">
          {row.delivered_at ? formatDateTime(row.delivered_at) : "—"}
        </span>
      ),
    },
    {
      key: "created_at",
      header: t("webhooks.createdAt"),
      render: (row) => (
        <span className="text-sm text-[var(--muted)]">{formatDateTime(row.created_at)}</span>
      ),
    },
  ];

  function Actions({ row }: { row: Delivery }) {
    return (
      <div className="flex items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7"
          onClick={() => setExpandedId(expandedId === row.id ? null : row.id)}
          title={t("webhooks.viewPayload")}
        >
          {"{ }"}
        </Button>
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7"
          onClick={() => retryMutation.mutate(row.id)}
          disabled={retryMutation.isPending}
          title={t("webhooks.retry")}
        >
          <RotateCcw className="h-3.5 w-3.5" />
        </Button>
      </div>
    );
  }

  const deliveries = data?.data ?? [];

  return (
    <div className="space-y-4">
      <h3 className="text-sm font-medium">{t("webhooks.deliveryLog")}</h3>

      {isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : deliveries.length === 0 ? (
        <p className="text-sm text-[var(--muted)] py-4 text-center">{t("webhooks.deliveriesEmpty")}</p>
      ) : (
        <>
          <DataTable
            columns={columns}
            data={deliveries}
            rowKey={(row) => row.id}
            emptyMessage={t("webhooks.deliveriesEmpty")}
            actions={(row) => <Actions row={row} />}
          />

          {expandedId && (
            <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] p-3">
              <p className="text-xs font-medium text-[var(--muted)] mb-2">{t("webhooks.payload")}</p>
              <pre className="text-xs font-mono whitespace-pre-wrap break-all max-h-60 overflow-y-auto">
                {JSON.stringify(
                  deliveries.find((d) => d.id === expandedId)?.payload,
                  null,
                  2,
                )}
              </pre>
              {deliveries.find((d) => d.id === expandedId)?.response_body && (
                <>
                  <p className="text-xs font-medium text-[var(--muted)] mt-3 mb-1">
                    {t("webhooks.responseBody")}
                  </p>
                  <pre className="text-xs font-mono whitespace-pre-wrap break-all max-h-40 overflow-y-auto">
                    {deliveries.find((d) => d.id === expandedId)?.response_body}
                  </pre>
                </>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
