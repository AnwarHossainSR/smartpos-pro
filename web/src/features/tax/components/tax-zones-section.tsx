import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { Drawer } from "@/components/ui/drawer";
import { Input } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { TaxZoneForm } from "@/features/tax/components/tax-zone-form";
import { useTaxZoneMutations, useTaxZones } from "@/features/tax/hooks";
import type { TaxZone } from "@/features/tax/types";
import { Pencil, Trash2 } from "lucide-react";
import { useState } from "react";

function jurisdiction(z: TaxZone): string {
  return [z.country_code, z.state, z.postcode_pattern].filter(Boolean).join(" · ") || "Any";
}

export function TaxZonesSection({ canManage }: { canManage: boolean }) {
  const list = useTaxZones();
  const { remove } = useTaxZoneMutations();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<TaxZone | null>(null);
  const [deleting, setDeleting] = useState<TaxZone | null>(null);

  const openCreate = () => { setEditing(null); setDrawerOpen(true); };
  const openEdit = (z: TaxZone) => { setEditing(z); setDrawerOpen(true); };
  const closeDrawer = () => { setDrawerOpen(false); setEditing(null); };

  const columns: DataTableColumn<TaxZone>[] = [
    {
      key: "name",
      header: "Zone",
      render: (z) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{z.name}</span>
          {z.is_default && <Badge tone="info">default</Badge>}
        </div>
      ),
    },
    { key: "jurisdiction", header: "Jurisdiction", render: (z) => <span className="text-[var(--muted)]">{jurisdiction(z)}</span> },
    { key: "group", header: "Tax group", render: (z) => z.tax_group?.name ?? <span className="text-[var(--muted)]">—</span> },
    { key: "priority", header: "Priority", align: "right", render: (z) => z.priority },
    {
      key: "status",
      header: "Status",
      render: (z) => <Badge tone={z.is_active ? "success" : "default"}>{z.is_active ? "active" : "inactive"}</Badge>,
    },
  ];

  return (
    <div className="space-y-3">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search tax zones…"
        onRefresh={list.refetch}
        filters={
          <div className="flex gap-2">
            <Input
              className="h-10 w-28"
              placeholder="Country"
              value={list.filters.country_code ?? ""}
              onChange={(e) => list.setFilter("country_code", e.target.value.toUpperCase() || undefined)}
              maxLength={2}
            />
            <Select
              className="h-10 w-40"
              value={list.filters.is_active ?? ""}
              onChange={(e) => list.setFilter("is_active", e.target.value || undefined)}
            >
              <option value="">All statuses</option>
              <option value="true">Active</option>
              <option value="false">Inactive</option>
            </Select>
          </div>
        }
        addLabel={canManage ? "Add zone" : undefined}
        onAdd={canManage ? openCreate : undefined}
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(z) => z.id}
          loading={list.isLoading}
          emptyMessage={list.search ? "No tax zones match your search." : "No tax zones yet. Map jurisdictions to the tax group that applies there."}
          actions={
            canManage
              ? (z) => (
                  <>
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(z)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(z)}>
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
            label={`Page ${list.meta.current_page} of ${list.meta.last_page} · ${list.meta.total} zones`}
          />
        )}
      </div>

      <Drawer open={drawerOpen} onClose={closeDrawer} title={editing ? `Edit: ${editing.name}` : "Add tax zone"}>
        <TaxZoneForm editing={editing} onSuccess={closeDrawer} />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete tax zone"
        description={`Are you sure you want to delete "${deleting?.name}"?`}
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
      />
    </div>
  );
}
