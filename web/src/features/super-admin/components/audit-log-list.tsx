import { useState } from "react";
import { Eye, ChevronDown, ChevronUp } from "lucide-react";
import { useAuditLogs } from "../hooks";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { AuditLog } from "../types";

function formatAction(action: string): string {
  return action
    .split(".")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function getActionBadgeTone(action: string): "success" | "warning" | "danger" | "info" {
  if (action.includes("create")) return "success";
  if (action.includes("update") || action.includes("edit")) return "warning";
  if (action.includes("delete") || action.includes("remove")) return "danger";
  return "info";
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

export function AuditLogList() {
  const [viewing, setViewing] = useState<AuditLog | null>(null);
  const [expanded, setExpanded] = useState<string | null>(null);

  const list = useAuditLogs();

  const columns: DataTableColumn<AuditLog>[] = [
    {
      key: "created_at",
      header: "Timestamp",
      render: (log) => (
        <span className="text-sm text-[var(--muted)]">{formatDate(log.created_at)}</span>
      ),
    },
    {
      key: "action",
      header: "Action",
      render: (log) => (
        <Badge tone={getActionBadgeTone(log.action)}>{formatAction(log.action)}</Badge>
      ),
    },
    {
      key: "user",
      header: "User",
      render: (log) => (
        <div className="text-sm">
          {log.user ? (
            <>
              <div className="font-medium">{log.user.name}</div>
              <div className="text-[var(--muted)]">{log.user.email}</div>
            </>
          ) : (
            <span className="text-[var(--muted)]">System</span>
          )}
        </div>
      ),
    },
    {
      key: "tenant",
      header: "Tenant",
      render: (log) => log.tenant?.name ?? "—",
    },
    {
      key: "ip_address",
      header: "IP",
      render: (log) => log.ip_address ?? "—",
    },
    {
      key: "details",
      header: "Details",
      render: (log) => (
        <div className="flex items-center gap-1">
          <span className="text-sm text-[var(--muted)]">
            {log.auditable_type.split("\\").pop()}
          </span>
          <span className="text-xs text-[var(--muted)]">#{log.auditable_id}</span>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search audit logs…"
        filters={
          <Select
            value={(list.filters as Record<string, string>).action ?? ""}
            onValueChange={(v) =>
              list.setFilter(
                "action" as never,
                v || (undefined as never),
              )
            }
          >
            <SelectTrigger className="w-40">
              <SelectValue placeholder="All actions" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All actions</SelectItem>
              <SelectItem value="created">Created</SelectItem>
              <SelectItem value="updated">Updated</SelectItem>
              <SelectItem value="deleted">Deleted</SelectItem>
              <SelectItem value="login">Login</SelectItem>
              <SelectItem value="logout">Logout</SelectItem>
            </SelectContent>
          </Select>
        }
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(log) => log.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No audit logs found."
          actions={(log) => (
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={() => setViewing(log)}
              title="View details"
            >
              <Eye className="h-3.5 w-3.5" />
            </Button>
          )}
        />
        {list.meta && (
          <Pagination
            page={list.page}
            lastPage={list.lastPage}
            onPageChange={list.setPage}
            label={`Page ${list.meta.current_page} of ${list.meta.last_page}`}
          />
        )}
      </div>

      <Dialog
        open={!!viewing}
        onClose={() => setViewing(null)}
        title="Audit Log Details"
        description={`Action performed on ${viewing?.auditable_type.split("\\").pop()} #${viewing?.auditable_id}`}
      >
        {viewing && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Timestamp</label>
                <div className="mt-1">{formatDate(viewing.created_at)}</div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Action</label>
                <div className="mt-1">
                  <Badge tone={getActionBadgeTone(viewing.action)}>
                    {formatAction(viewing.action)}
                  </Badge>
                </div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">User</label>
                <div className="mt-1">
                  {viewing.user ? `${viewing.user.name} (${viewing.user.email})` : "System"}
                </div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Tenant</label>
                <div className="mt-1">{viewing.tenant?.name ?? "—"}</div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">IP Address</label>
                <div className="mt-1">{viewing.ip_address ?? "—"}</div>
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Resource</label>
                <div className="mt-1">
                  {viewing.auditable_type.split("\\").pop()} #{viewing.auditable_id}
                </div>
              </div>
            </div>

            {viewing.old_values && Object.keys(viewing.old_values).length > 0 && (
              <div>
                <button
                  className="flex items-center gap-1 text-sm font-medium"
                  onClick={() => setExpanded(expanded === "old" ? null : "old")}
                >
                  Old Values
                  {expanded === "old" ? (
                    <ChevronUp className="h-4 w-4" />
                  ) : (
                    <ChevronDown className="h-4 w-4" />
                  )}
                </button>
                {expanded === "old" && (
                  <pre className="mt-2 max-h-48 overflow-auto rounded-[var(--radius)] bg-[var(--background)] p-3 text-xs">
                    {JSON.stringify(viewing.old_values, null, 2)}
                  </pre>
                )}
              </div>
            )}

            {viewing.new_values && Object.keys(viewing.new_values).length > 0 && (
              <div>
                <button
                  className="flex items-center gap-1 text-sm font-medium"
                  onClick={() => setExpanded(expanded === "new" ? null : "new")}
                >
                  New Values
                  {expanded === "new" ? (
                    <ChevronUp className="h-4 w-4" />
                  ) : (
                    <ChevronDown className="h-4 w-4" />
                  )}
                </button>
                {expanded === "new" && (
                  <pre className="mt-2 max-h-48 overflow-auto rounded-[var(--radius)] bg-[var(--background)] p-3 text-xs">
                    {JSON.stringify(viewing.new_values, null, 2)}
                  </pre>
                )}
              </div>
            )}
          </div>
        )}
      </Dialog>
    </div>
  );
}
