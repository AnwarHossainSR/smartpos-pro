import { useState } from "react";
import { Pencil, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { useTaxRateMutations, useTaxRates } from "@/features/tax/hooks";
import { TaxRateForm } from "@/features/tax/components/tax-rate-form";
import type { TaxRate } from "@/features/tax/types";

export function TaxRatesSection({ canManage }: { canManage: boolean }) {
  const list = useTaxRates();
  const { remove } = useTaxRateMutations();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<TaxRate | null>(null);
  const [deleting, setDeleting] = useState<TaxRate | null>(null);

  const openCreate = () => { setEditing(null); setDrawerOpen(true); };
  const openEdit = (r: TaxRate) => { setEditing(r); setDrawerOpen(true); };
  const closeDrawer = () => { setDrawerOpen(false); setEditing(null); };

  const columns: DataTableColumn<TaxRate>[] = [
    { key: "name", header: "Name", render: (r) => <span className="font-medium">{r.name}</span> },
    { key: "code", header: "Code", render: (r) => <span className="font-mono text-xs text-[var(--muted)]">{r.code ?? "—"}</span> },
    {
      key: "rate",
      header: "Rate",
      align: "right",
      render: (r) => <span className="font-medium">{r.type === "percentage" ? `${r.rate}%` : r.rate.toFixed(2)}</span>,
    },
    {
      key: "compound",
      header: "Compound",
      render: (r) => (r.is_compound ? <Badge tone="info">Compound</Badge> : <span className="text-[var(--muted)]">—</span>),
    },
    {
      key: "status",
      header: "Status",
      render: (r) => <Badge tone={r.is_active ? "success" : "default"}>{r.is_active ? "active" : "inactive"}</Badge>,
    },
  ];

  return (
    <div className="space-y-3">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search tax rates…"
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
        addLabel={canManage ? "Add rate" : undefined}
        onAdd={canManage ? openCreate : undefined}
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(r) => r.id}
          loading={list.isLoading}
          emptyMessage={list.search ? "No tax rates match your search." : "No tax rates yet. Components like CGST, SGST, or VAT live here."}
          actions={
            canManage
              ? (r) => (
                  <>
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(r)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(r)}>
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
            label={`Page ${list.meta.current_page} of ${list.meta.last_page} · ${list.meta.total} rates`}
          />
        )}
      </div>

      <Drawer open={drawerOpen} onClose={closeDrawer} title={editing ? `Edit: ${editing.name}` : "Add tax rate"}>
        <TaxRateForm editing={editing} onSuccess={closeDrawer} />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete tax rate"
        description={`Are you sure you want to delete "${deleting?.name}"? Any group using it will lose this component.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
      />
    </div>
  );
}
