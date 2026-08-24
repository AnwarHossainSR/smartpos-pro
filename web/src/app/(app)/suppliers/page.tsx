import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { toast } from "@/components/ui/toast";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatCurrency } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Pencil, Trash2 } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

interface Supplier {
  id: string;
  name: string;
  company: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  tax_number: string | null;
  opening_balance: number;
  balance: number;
  is_active: boolean;
}

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  company: z.string().optional(),
  email: z.string().email().optional().or(z.literal("")),
  phone: z.string().optional(),
  address: z.string().optional(),
  tax_number: z.string().optional(),
  opening_balance: z.number().min(0).optional(),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

function SupplierForm({ defaultValues, supplierId, isEdit, onSuccess }: { defaultValues?: Partial<FormValues>; supplierId?: string; isEdit: boolean; onSuccess: () => void; }) {
  const qc = useQueryClient();
  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { is_active: true, opening_balance: 0, ...defaultValues },
  });
  const mutation = useMutation({
    mutationFn: (data: FormValues) =>
      isEdit ? api(`/suppliers/${supplierId}`, { method: "PUT", body: data }) : api("/suppliers", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.suppliers.all }); toast(isEdit ? "Supplier updated" : "Supplier created"); onSuccess(); },
  });
  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div><Label>Name *</Label><Input {...register("name")} />{errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}</div>
      <div><Label>Company</Label><Input {...register("company")} /></div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Email</Label><Input type="email" {...register("email")} /></div>
        <div><Label>Phone</Label><Input {...register("phone")} /></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Tax number</Label><Input {...register("tax_number")} /></div>
        {!isEdit && <div><Label>Opening balance</Label><Input type="number" step="0.01" min="0" {...register("opening_balance", { valueAsNumber: true })} /></div>}
      </div>
      <div><Label>Address</Label><Input {...register("address")} /></div>
      <div><Label>Status</Label>
        <Select {...register("is_active", { setValueAs: (v: string) => v === "true" })}>
          <option value="true">Active</option><option value="false">Inactive</option>
        </Select>
      </div>
      <Button type="submit" disabled={mutation.isPending} className="w-full">{mutation.isPending ? "Saving…" : isEdit ? "Update" : "Create"}</Button>
    </form>
  );
}

export default function SuppliersPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<Supplier | null>(null);
  const [deleting, setDeleting] = useState<Supplier | null>(null);

  const list = useResourceList<Supplier>({
    queryKey: queryKeys.suppliers.lists(),
    queryFn: (p) => api<Paginated<Supplier>>(`/suppliers?${buildListQuery(p)}`),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/suppliers/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.suppliers.all }); toast("Supplier deleted"); setDeleting(null); },
  });

  const columns: DataTableColumn<Supplier>[] = [
    {
      key: "name",
      header: "Name",
      className: "font-medium",
      render: (s) => <>{s.name}{s.email && <span className="block text-xs text-[var(--muted)]">{s.email}</span>}</>,
    },
    { key: "company", header: "Company", render: (s) => s.company ?? "—" },
    { key: "phone", header: "Phone", render: (s) => s.phone ?? "—" },
    { key: "balance", header: "Balance", align: "right", render: (s) => formatCurrency(s.balance) },
    { key: "status", header: "Status", render: (s) => <Badge tone={s.is_active ? "success" : "default"}>{s.is_active ? "Active" : "Inactive"}</Badge> },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        addLabel={can("suppliers.create") ? "Add supplier" : undefined}
        onAdd={can("suppliers.create") ? () => { setEditing(null); setDrawerOpen(true); } : undefined}
        onRefresh={list.refetch}
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(s) => s.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No suppliers yet."
          actions={(s) => (
            <>
              {can("suppliers.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(s); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
              {can("suppliers.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(s)}><Trash2 className="h-3.5 w-3.5" /></Button>}
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
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : "Add supplier"}>
        <SupplierForm key={editing?.id ?? "new"} isEdit={!!editing} supplierId={editing?.id} defaultValues={editing ? { name: editing.name, company: editing.company ?? undefined, email: editing.email ?? undefined, phone: editing.phone ?? undefined, address: editing.address ?? undefined, tax_number: editing.tax_number ?? undefined, opening_balance: editing.opening_balance, is_active: editing.is_active } : undefined} onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      <Dialog open={!!deleting} onClose={() => setDeleting(null)} title="Delete supplier" description={`Delete "${deleting?.name}"?`} confirmLabel="Delete" confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />
    </div>
  );
}
