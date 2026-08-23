import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { useAuth } from "@/stores/auth";
import { Pencil, Trash2 } from "lucide-react";
import { useState } from "react";
import { usePlanMutations, usePlans } from "../hooks";
import type { SuperAdminPlan } from "../types";
import { CreatePlanDialog } from "./create-plan-dialog";
import { EditPlanDialog } from "./edit-plan-dialog";

export function PlanList() {
  const [editing, setEditing] = useState<SuperAdminPlan | null>(null);
  const [deleting, setDeleting] = useState<SuperAdminPlan | null>(null);
  const [creating, setCreating] = useState(false);

  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- super-admin slugs not in permission union yet
  const canManage = useAuth((s) => s.can("super-admin.tenants.manage" as any));

  const list = usePlans();
  const mutations = usePlanMutations();

  const columns: DataTableColumn<SuperAdminPlan>[] = [
    {
      key: "name",
      header: "Name",
      className: "font-medium",
      render: (p) => (
        <>
          {p.name}
          <span className="block text-xs text-[var(--muted)]">{p.slug}</span>
        </>
      ),
    },
    {
      key: "price",
      header: "Price",
      align: "right",
      render: (p) => `${p.currency} ${p.price.toFixed(2)}`,
    },
    {
      key: "trial_days",
      header: "Trial Days",
      align: "right",
      render: (p) => p.trial_days ?? "—",
    },
    {
      key: "status",
      header: "Status",
      render: (p) => (
        <Badge tone={p.is_active ? "success" : "danger"}>
          {p.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
    {
      key: "created_at",
      header: "Created",
      render: (p) => new Date(p.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search plans…"
        {...(canManage ? { addLabel: "Create Plan", onAdd: () => setCreating(true) } : {})}
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(p) => p.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No plans found."
          actions={(p) =>
            canManage ? (
              <>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => setEditing(p)}
                >
                  <Pencil className="h-3.5 w-3.5" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-[var(--color-danger)]"
                  onClick={() => setDeleting(p)}
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </>
            ) : undefined
          }
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
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete plan"
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

      <CreatePlanDialog
        open={creating}
        onClose={() => setCreating(false)}
        onSubmit={(data) =>
          mutations.create.mutate(data, {
            onSuccess: () => setCreating(false),
          })
        }
        loading={mutations.create.isPending}
      />

      <EditPlanDialog
        plan={editing}
        onClose={() => setEditing(null)}
        onSubmit={(data) => {
          if (!editing) return;
          mutations.update.mutate(
            { id: editing.id, data },
            { onSuccess: () => setEditing(null) },
          );
        }}
        loading={mutations.update.isPending}
      />
    </div>
  );
}
