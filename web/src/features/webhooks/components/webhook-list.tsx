import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api, errorMessage, type Envelope } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, Eye, EyeOff, Plus, Trash2, Webhook } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

const AVAILABLE_EVENTS = [
  "sale.completed",
  "sale.returned",
  "customer.created",
  "inventory.low",
] as const;

interface WebhookItem {
  id: string;
  url: string;
  events: string[];
  is_active: boolean;
  created_at: string;
}

const webhookSchema = z.object({
  url: z.string().url("Must be a valid URL"),
  events: z.array(z.string()).min(1, "Select at least one event"),
});
type WebhookForm = z.infer<typeof webhookSchema>;

export function WebhookList() {
  const { can } = useAuth();
  const t = useTranslation();
  const qc = useQueryClient();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [showSecret, setShowSecret] = useState<string | null>(null);
  const [createdSecret, setCreatedSecret] = useState<string | null>(null);

  const canManage = can("settings.update");

  const { data, isLoading, error, refetch } = useQuery<Envelope<WebhookItem[]>>({
    queryKey: queryKeys.webhooks.lists(),
    queryFn: () => api<Envelope<WebhookItem[]>>("/webhooks"),
  });

  const createMutation = useMutation({
    mutationFn: (d: WebhookForm) =>
      api<{ data: WebhookItem; secret: string }>("/webhooks", { method: "POST", body: d }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: queryKeys.webhooks.all });
      toast(t("webhooks.createSuccess"));
      setDrawerOpen(false);
      setCreatedSecret(res.secret);
      setShowSecret(res.data.id);
    },
    onError: (e: unknown) => toast(errorMessage(e, t("webhooks.createFailed")), "error"),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/webhooks/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.webhooks.all });
      toast(t("webhooks.deleteSuccess"));
      setDeletingId(null);
    },
    // Also drops the confirm drawer, which would otherwise stay open over the failure.
    onError: (e: unknown) => { setDeletingId(null); toast(errorMessage(e, t("webhooks.deleteFailed")), "error"); },
  });

  const copySecret = (secret: string) => {
    navigator.clipboard.writeText(secret);
    toast(t("webhooks.secretCopied"));
  };

  const columns: DataTableColumn<WebhookItem>[] = [
    {
      key: "url",
      header: t("webhooks.url"),
      render: (row) => (
        <span className="font-mono text-sm truncate max-w-[300px] block">{row.url}</span>
      ),
    },
    {
      key: "events",
      header: t("webhooks.events"),
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.events.map((ev) => (
            <Badge key={ev} tone="info" className="text-[10px]">
              {ev}
            </Badge>
          ))}
        </div>
      ),
    },
    {
      key: "status",
      header: t("common.status"),
      render: (row) => (
        <Badge tone={row.is_active ? "success" : "default"}>
          {row.is_active ? t("common.active") : t("common.inactive")}
        </Badge>
      ),
    },
  ];

  function Actions({ row }: { row: WebhookItem }) {
    return (
      <div className="flex items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7"
          onClick={() => {
            if (showSecret === row.id) {
              setShowSecret(null);
              setCreatedSecret(null);
            } else {
              setShowSecret(row.id);
            }
          }}
          title={t("webhooks.viewSecret")}
        >
          {showSecret === row.id ? (
            <EyeOff className="h-3.5 w-3.5" />
          ) : (
            <Eye className="h-3.5 w-3.5" />
          )}
        </Button>
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7 text-[var(--color-danger)]"
          onClick={() => setDeletingId(row.id)}
          title={t("common.delete")}
        >
          <Trash2 className="h-3.5 w-3.5" />
        </Button>
      </div>
    );
  }

  const webhooks = data?.data ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium flex items-center gap-2">
          <Webhook className="h-4 w-4" />
          {t("webhooks.title")}
        </h3>
        {canManage && (
          <Button size="sm" onClick={() => setDrawerOpen(true)}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            {t("webhooks.add")}
          </Button>
        )}
      </div>

      {/* Loading skeleton, empty state and error-with-retry all come from DataTable. */}
      <DataTable
        columns={columns}
        data={webhooks}
        rowKey={(row) => row.id}
        loading={isLoading}
        emptyMessage={t("webhooks.empty")}
        error={error}
        onRetry={() => refetch()}
        actions={(row) => <Actions row={row} />}
      />

      {showSecret && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] p-3">
          <p className="text-xs font-medium text-[var(--muted)] mb-1">{t("webhooks.secret")}</p>
          <div className="flex items-center gap-2">
            <code className="text-xs font-mono break-all flex-1">
              {createdSecret ?? "••••••••••••••••"}
            </code>
            {createdSecret && (
              <Button
                variant="ghost"
                size="icon"
                className="h-7 w-7 shrink-0"
                onClick={() => copySecret(createdSecret)}
              >
                <Copy className="h-3.5 w-3.5" />
              </Button>
            )}
          </div>
          <p className="text-[10px] text-[var(--muted)] mt-1">{t("webhooks.secretHint")}</p>
        </div>
      )}

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={t("webhooks.createTitle")}>
        <WebhookCreateForm
          onSubmit={(d) => createMutation.mutate(d)}
          loading={createMutation.isPending}
          onCancel={() => setDrawerOpen(false)}
        />
      </Drawer>

      <Drawer
        open={!!deletingId}
        onClose={() => setDeletingId(null)}
        title={t("webhooks.deleteTitle")}
      >
        <p className="text-sm text-[var(--muted)] mb-4">{t("webhooks.deleteConfirm")}</p>
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => setDeletingId(null)}>
            {t("common.cancel")}
          </Button>
          <Button
            variant="danger"
            onClick={() => deletingId && deleteMutation.mutate(deletingId)}
            disabled={deleteMutation.isPending}
          >
            {deleteMutation.isPending ? "…" : t("common.delete")}
          </Button>
        </div>
      </Drawer>
    </div>
  );
}

function WebhookCreateForm({
  onSubmit,
  loading,
  onCancel,
}: {
  onSubmit: (d: WebhookForm) => void;
  loading: boolean;
  onCancel: () => void;
}) {
  const t = useTranslation();
  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    getValues,
  } = useForm<WebhookForm>({
    resolver: zodResolver(webhookSchema),
    defaultValues: { url: "", events: [] },
  });

  function toggleEvent(event: string) {
    const current = getValues("events") ?? [];
    const next = current.includes(event)
      ? current.filter((e) => e !== event)
      : [...current, event];
    setValue("events", next, { shouldValidate: true });
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <Label>{t("webhooks.form.url")} *</Label>
        <Input {...register("url")} placeholder="https://example.com/webhook" />
        {errors.url && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.url.message}</p>
        )}
      </div>

      <div>
        <Label>{t("webhooks.form.events")} *</Label>
        <div className="mt-2 space-y-2">
          {AVAILABLE_EVENTS.map((event) => (
            <label
              key={event}
              className="flex items-center gap-2 cursor-pointer text-sm"
            >
              <input
                type="checkbox"
                className="h-4 w-4 accent-[var(--primary)]"
                checked={getValues("events")?.includes(event) ?? false}
                onChange={() => toggleEvent(event)}
              />
              <span className="font-mono text-xs">{event}</span>
            </label>
          ))}
        </div>
        {errors.events && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.events.message}</p>
        )}
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t("common.cancel")}
        </Button>
        <Button type="submit" disabled={loading}>
          {loading ? "…" : t("common.save")}
        </Button>
      </div>
    </form>
  );
}
