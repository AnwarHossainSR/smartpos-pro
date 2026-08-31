import { ChevronDown, ChevronUp, Eye, Filter, X } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { useAuditLogs } from "../hooks";
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
  const [showFilters, setShowFilters] = useState(false);

  const list = useAuditLogs();
  const filters = (list.filters as Record<string, string>) ?? {};

  const hasActiveFilters = filters.tenant_id || filters.user_id || filters.from || filters.to;

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
          <div className="flex items-center gap-2">
            <Select
              className="w-40"
              value={filters.action ?? ""}
              onChange={(e) =>
                list.setFilter("action" as never, e.target.value || (undefined as never))
              }
            >
              <option value="">All actions</option>
              <option value="created">Created</option>
              <option value="updated">Updated</option>
              <option value="deleted">Deleted</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
            </Select>

            <Button
              variant={showFilters ? "primary" : "ghost"}
              size="sm"
              onClick={() => setShowFilters(!showFilters)}
            >
              <Filter className="mr-1 h-3.5 w-3.5" />
              More Filters
              {hasActiveFilters && (
                <Badge tone="info" className="ml-1">
                  {(filters.tenant_id ? 1 : 0) +
                    (filters.user_id ? 1 : 0) +
                    (filters.from ? 1 : 0) +
                    (filters.to ? 1 : 0)}
                </Badge>
              )}
            </Button>

            {hasActiveFilters && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  list.setFilter("tenant_id" as never, undefined as never);
                  list.setFilter("user_id" as never, undefined as never);
                  list.setFilter("from" as never, undefined as never);
                  list.setFilter("to" as never, undefined as never);
                }}
              >
                <X className="mr-1 h-3.5 w-3.5" />
                Clear
              </Button>
            )}
          </div>
        }
      />

      {showFilters && (
        <Card>
          <CardContent className="py-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">Tenant ID</label>
                <input
                  type="text"
                  value={filters.tenant_id ?? ""}
                  onChange={(e) =>
                    list.setFilter("tenant_id" as never, e.target.value || (undefined as never))
                  }
                  placeholder="Filter by tenant…"
                  className="mt-1 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">User ID</label>
                <input
                  type="text"
                  value={filters.user_id ?? ""}
                  onChange={(e) =>
                    list.setFilter("user_id" as never, e.target.value || (undefined as never))
                  }
                  placeholder="Filter by user…"
                  className="mt-1 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">From Date</label>
                <input
                  type="date"
                  value={filters.from ?? ""}
                  onChange={(e) =>
                    list.setFilter("from" as never, e.target.value || (undefined as never))
                  }
                  className="mt-1 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="text-sm font-medium text-[var(--muted)]">To Date</label>
                <input
                  type="date"
                  value={filters.to ?? ""}
                  onChange={(e) =>
                    list.setFilter("to" as never, e.target.value || (undefined as never))
                  }
                  className="mt-1 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
                />
              </div>
            </div>
          </CardContent>
        </Card>
      )}

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
