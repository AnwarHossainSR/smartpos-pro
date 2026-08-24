import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { Drawer } from "@/components/ui/drawer";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { TaxGroupForm } from "@/features/tax/components/tax-group-form";
import { useTaxGroupMutations, useTaxGroups } from "@/features/tax/hooks";
import type { TaxGroup } from "@/features/tax/types";
import { Pencil, Trash2 } from "lucide-react";
import { useState } from "react";

export function TaxGroupsSection({ canManage }: { canManage: boolean }) {
  const list = useTaxGroups();
  const { remove } = useTaxGroupMutations();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<TaxGroup | null>(null);
  const [deleting, setDeleting] = useState<TaxGroup | null>(null);

  const openCreate = () => { setEditing(null); setDrawerOpen(true); };
  const openEdit = (g: TaxGroup) => { setEditing(g); setDrawerOpen(true); };
  const closeDrawer = () => { setDrawerOpen(false); setEditing(null); };

  const columns: DataTableColumn<TaxGroup>[] = [
    {
      key: "name",
      header: "Name",
      render: (g) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{g.name}</span>
          {g.is_default && <Badge tone="info">default</Badge>}
        </div>
      ),
    },
    {
      key: "components",
      header: "Components",
      render: (g) =>
        g.rates.length === 0 ? (
          <span className="text-[var(--muted)]">—</span>
        ) : (
          <div className="flex flex-wrap gap-1">
            {g.rates
              .slice()
              .sort((a, b) => (a.priority ?? 0) - (b.priority ?? 0))
              .map((r) => (
                <Badge key={r.id} tone="default">
                  {r.name} {r.type === "percentage" ? `${r.rate}%` : r.rate.toFixed(2)}
                </Badge>
              ))}
          </div>
        ),
    },
    { key: "products", header: "Products", align: "right", render: (g) => g.products_count ?? 0 },
    {
      key: "status",
      header: "Status",
      render: (g) => <Badge tone={g.is_active ? "success" : "default"}>{g.is_active ? "active" : "inactive"}</Badge>,
    },
  ];

  return (
    <div className="space-y-3">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search tax groups…"
        onRefresh={list.refetch}
        filters={
          <Select
            className="h-10 w-40"
            value={list.filters.is_active ?? ""}
            onChange={(e) => list.setFilter("is_active", e.target.value || undefined)}
          >
            <option value="">All statuses</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </Select>
        }
        addLabel={canManage ? "Add group" : undefined}
        onAdd={canManage ? openCreate : undefined}
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(g) => g.id}
          loading={list.isLoading}
          emptyMessage={
            list.search ? "No tax groups match your search." : "No tax groups yet. Group components (e.g. CGST + SGST) here to attach them to products."
          }
          actions={
            canManage
              ? (g) => (
                  <>
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(g)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(g)}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </>
                )
              : undefined
          }
        />
        {list.meta && (
          <Pagination
            page={list.page}
            lastPage={list.lastPage}
            onPageChange={list.setPage}
            label={`Page ${list.meta.current_page} of ${list.meta.last_page} · ${list.meta.total} groups`}
          />
        )}
      </div>

      <Drawer open={drawerOpen} onClose={closeDrawer} title={editing ? `Edit: ${editing.name}` : "Add tax group"} width="max-w-xl">
        <TaxGroupForm editing={editing} onSuccess={closeDrawer} />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete tax group"
        description={`Are you sure you want to delete "${deleting?.name}"? Products or zones using it will need a new group.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
      />
    </div>
  );
}
