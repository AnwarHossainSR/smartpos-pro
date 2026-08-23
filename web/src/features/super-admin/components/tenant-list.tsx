import { useState } from "react";
import { Eye, Ban, Play, Trash2, LogIn } from "lucide-react";
import { useTenants, useTenantMutations } from "../hooks";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog } from "@/components/ui/dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { TenantDetailModal } from "./tenant-detail-modal";
import type { Tenant } from "../types";

export function TenantList() {
  const [viewing, setViewing] = useState<Tenant | null>(null);
  const [deleting, setDeleting] = useState<Tenant | null>(null);
  const [impersonating, setImpersonating] = useState<Tenant | null>(null);
  const [suspending, setSuspending] = useState<Tenant | null>(null);
  const [activating, setActivating] = useState<Tenant | null>(null);
  const [reason, setReason] = useState("");

  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- super-admin slugs not in permission union yet
  const canManage = useAuth((s) => s.can("super-admin.tenants.manage" as any));

  const list = useTenants();
  const mutations = useTenantMutations();

  const columns: DataTableColumn<Tenant>[] = [
    {
      key: "name",
      header: "Name",
      className: "font-medium",
      render: (t) => (
        <>
          {t.name}
          <span className="block text-xs text-[var(--muted)]">{t.slug}</span>
        </>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (t) => (
        <Badge tone={t.status === "active" ? "success" : "danger"}>
          {t.status}
        </Badge>
      ),
    },
    {
      key: "users",
      header: "Users",
      align: "right",
      render: (t) => t.users_count ?? "—",
    },
    {
      key: "businesses",
      header: "Businesses",
      align: "right",
      render: (t) => t.businesses_count ?? "—",
    },
    {
      key: "created_at",
      header: "Created",
      render: (t) => new Date(t.created_at).toLocaleDateString(),
    },
  ];

  function handleImpersonate() {
    if (!impersonating || reason.length < 10) return;
    mutations.impersonate.mutate(
      { id: impersonating.id, reason },
      {
        onSuccess: (res) => {
          const data = res.data;
          const appBase = window.location.origin;
          window.open(`${appBase}/impersonate?token=${encodeURIComponent(data.token)}`, "_blank");
          setImpersonating(null);
          setReason("");
        },
      },
    );
  }

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search tenants…"
        filters={
          <Select
            className="w-40"
            value={(list.filters as Record<string, string>).status ?? ""}
            onChange={(e) =>
              list.setFilter(
                "status" as never,
                e.target.value || (undefined as never),
              )
            }
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </Select>
        }
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(t) => t.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No tenants found."
          actions={(t) => (
            <>
              <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8"
                onClick={() => setViewing(t)}
              >
                <Eye className="h-3.5 w-3.5" />
              </Button>
              {canManage && (
                <>
                  {t.status === "active" ? (
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-[var(--color-warning)]"
                      onClick={() => setSuspending(t)}
                    >
                      <Ban className="h-3.5 w-3.5" />
                    </Button>
                  ) : (
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-[var(--color-success)]"
                      onClick={() => setActivating(t)}
                    >
                      <Play className="h-3.5 w-3.5" />
                    </Button>
                  )}
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-[var(--color-primary)]"
                    onClick={() => {
                      setImpersonating(t);
                      setReason("");
                    }}
                  >
                    <LogIn className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-[var(--color-danger)]"
                    onClick={() => setDeleting(t)}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </>
              )}
            </>
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

      <TenantDetailModal
        tenantId={viewing?.id ?? null}
        onClose={() => setViewing(null)}
      />

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete tenant"
        description={`Delete "${deleting?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        onConfirm={() => {
          if (!deleting) return;
          mutations.remove.mutate(deleting.id, {
            onSuccess: () => setDeleting(null),
          });
        }}
        loading={mutations.remove.isPending}
      />

      <Dialog
        open={!!suspending}
        onClose={() => setSuspending(null)}
        title="Suspend tenant"
        description={`Suspend "${suspending?.name}"? Users will lose access.`}
        confirmLabel="Suspend"
        confirmVariant="danger"
        onConfirm={() => {
          if (!suspending) return;
          mutations.suspend.mutate(suspending.id, {
            onSuccess: () => setSuspending(null),
          });
        }}
        loading={mutations.suspend.isPending}
      />

      <Dialog
        open={!!activating}
        onClose={() => setActivating(null)}
        title="Activate tenant"
        description={`Activate "${activating?.name}"?`}
        confirmLabel="Activate"
        confirmVariant="primary"
        onConfirm={() => {
          if (!activating) return;
          mutations.activate.mutate(activating.id, {
            onSuccess: () => setActivating(null),
          });
        }}
        loading={mutations.activate.isPending}
      />

      <Dialog
        open={!!impersonating}
        onClose={() => {
          setImpersonating(null);
          setReason("");
        }}
        title="Impersonate tenant"
        description={`Start an impersonation session for "${impersonating?.name}".`}
        confirmLabel="Start session"
        confirmVariant="primary"
        onConfirm={handleImpersonate}
        loading={mutations.impersonate.isPending}
      >
        <div>
          <label className="text-sm font-medium">Reason (min 10 characters)</label>
          <textarea
            className="mt-1 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm outline-none focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20"
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Explain why you need to impersonate this tenant…"
          />
          {reason.length > 0 && reason.length < 10 && (
            <p className="mt-1 text-xs text-[var(--color-danger)]">
              Must be at least 10 characters.
            </p>
          )}
        </div>
      </Dialog>
    </div>
  );
}
