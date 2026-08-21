import { useState } from "react";
import { Eye } from "lucide-react";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatDateTime } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { useAuth } from "@/stores/auth";

interface AuditLogRow {
  id: string;
  action: string;
  auditable_type: string | null;
  auditable_id: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  user: { id: string; name: string; email: string } | null;
  created_at: string;
}

function actionTone(action: string): "success" | "warning" | "danger" | "default" {
  if (action.includes("deleted") || action.includes("failed") || action.includes("revoke")) return "danger";
  if (action.includes("override") || action.includes("discount") || action.includes("updated")) return "warning";
  if (action.includes("created") || action.includes("login")) return "success";
  return "default";
}

/** Action text and the date range all round-trip through the URL, so a filtered view is shareable. */
type AuditFilters = { action: string; from: string; to: string };

export default function AuditLogPage() {
  const { can } = useAuth();
  const [viewing, setViewing] = useState<AuditLogRow | null>(null);

  const allowed = can("audit.view");

  const list = useResourceList<AuditLogRow, AuditFilters>({
    queryKey: queryKeys.auditLogs.lists(),
    queryFn: (p) => api<Paginated<AuditLogRow>>(`/audit-logs?${buildListQuery(p)}`),
    filterKeys: ["action", "from", "to"] as const,
    enabled: allowed,
  });

  const { action = "", from = "", to = "" } = list.filters;

  if (!allowed) {
    return (
      <div className="space-y-4">
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-8 text-center text-sm text-[var(--muted)]">
          You don&apos;t have permission to view the audit log.
        </div>
      </div>
    );
  }

  const columns: DataTableColumn<AuditLogRow>[] = [
    {
      key: "action",
      header: "Action",
      render: (r) => <Badge tone={actionTone(r.action)}>{r.action}</Badge>,
    },
    {
      key: "model",
      header: "Record",
      render: (r) =>
        r.auditable_type ? (
          <span className="text-[var(--muted)]">
            {r.auditable_type} #{r.auditable_id}
          </span>
        ) : (
          <span className="text-[var(--muted)]">—</span>
        ),
    },
    {
      key: "user",
      header: "Actor",
      render: (r) => (r.user ? r.user.name : <span className="text-[var(--muted)]">System</span>),
    },
    { key: "ip", header: "IP", render: (r) => <span className="font-mono text-xs">{r.ip_address ?? "—"}</span> },
    { key: "when", header: "When", render: (r) => formatDateTime(r.created_at) },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={action}
        onSearchChange={(v) => list.setFilter("action", v)}
        searchPlaceholder="Filter by action (e.g. sale.price_override)…"
        filters={
          <div className="flex items-center gap-2">
            <Input type="date" value={from} onChange={(e) => list.setFilter("from", e.target.value)} className="w-auto" />
            <span className="text-xs text-[var(--muted)]">to</span>
            <Input type="date" value={to} onChange={(e) => list.setFilter("to", e.target.value)} className="w-auto" />
          </div>
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(r) => r.id}
          loading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage={list.isFiltered ? "No matching audit entries." : "No audit activity yet."}
          actions={(r) => (
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setViewing(r)}>
              <Eye className="h-3.5 w-3.5" />
            </Button>
          )}
        />

        {list.meta && (
          <Pagination
            page={list.page}
            lastPage={list.lastPage}
            onPageChange={list.setPage}
            label={`Page ${list.meta.current_page} of ${list.meta.last_page} · ${list.meta.total} entries`}
          />
        )}
      </div>

      <Dialog open={!!viewing} onClose={() => setViewing(null)} title={viewing?.action ?? ""}>
        {viewing && (
          <div className="space-y-3 text-sm">
            <div className="grid grid-cols-2 gap-2 text-xs text-[var(--muted)]">
              <div>
                <Label>Actor</Label>
                <p className="text-[var(--fg)]">{viewing.user?.name ?? "System"}</p>
              </div>
              <div>
                <Label>When</Label>
                <p className="text-[var(--fg)]">{formatDateTime(viewing.created_at)}</p>
              </div>
              <div>
                <Label>IP address</Label>
                <p className="text-[var(--fg)]">{viewing.ip_address ?? "—"}</p>
              </div>
              <div>
                <Label>Record</Label>
                <p className="text-[var(--fg)]">
                  {viewing.auditable_type ? `${viewing.auditable_type} #${viewing.auditable_id}` : "—"}
                </p>
              </div>
            </div>
            {viewing.old_values && (
              <div>
                <Label>Before</Label>
                <pre className="mt-1 max-h-40 overflow-auto rounded-[var(--radius)] bg-[var(--surface-2)] p-2 text-xs">
                  {JSON.stringify(viewing.old_values, null, 2)}
                </pre>
              </div>
            )}
            {viewing.new_values && (
              <div>
                <Label>After</Label>
                <pre className="mt-1 max-h-40 overflow-auto rounded-[var(--radius)] bg-[var(--surface-2)] p-2 text-xs">
                  {JSON.stringify(viewing.new_values, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </Dialog>
    </div>
  );
}
