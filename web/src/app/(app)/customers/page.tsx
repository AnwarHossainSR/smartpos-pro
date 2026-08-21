import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Pencil, Trash2, UserMinus } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { useTranslation } from "@/i18n/context";

interface Customer {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  customer_group: string | null;
  balance: number;
  loyalty_points: number;
  is_active: boolean;
}

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  email: z.string().email("Invalid email").optional().or(z.literal("")),
  phone: z.string().optional(),
  customer_group: z.string().optional(),
  address: z.string().optional(),
  credit_limit: z.number().min(0).optional(),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

function CustomerForm({ defaultValues, customerId, isEdit, onSuccess }: { defaultValues?: Partial<FormValues>; customerId?: string; isEdit: boolean; onSuccess: () => void; }) {
  const qc = useQueryClient();
  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { is_active: true, ...defaultValues },
  });
  const mutation = useMutation({
    mutationFn: (data: FormValues) =>
      isEdit ? api(`/customers/${customerId}`, { method: "PUT", body: data }) : api("/customers", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.customers.all }); toast(isEdit ? "Customer updated" : "Customer created"); onSuccess(); },
  });
  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div><Label>Name *</Label><Input {...register("name")} />{errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}</div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Email</Label><Input type="email" {...register("email")} /></div>
        <div><Label>Phone</Label><Input {...register("phone")} /></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Group</Label>
          <Select {...register("customer_group")}>
            <option value="">— None —</option>
            <option value="regular">Regular</option>
            <option value="vip">VIP</option>
            <option value="wholesale">Wholesale</option>
          </Select>
        </div>
        <div><Label>Credit limit</Label><Input type="number" step="0.01" min="0" {...register("credit_limit", { valueAsNumber: true })} /></div>
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

export default function CustomersPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const qc = useQueryClient();
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<Customer | null>(null);
  const [deleting, setDeleting] = useState<Customer | null>(null);
  const [anonymizing, setAnonymizing] = useState<Customer | null>(null);

  const list = useResourceList<Customer>({
    queryKey: queryKeys.customers.lists(),
    queryFn: (p) => api<Paginated<Customer>>(`/customers?${buildListQuery(p)}`),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/customers/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.customers.all }); toast("Customer deleted"); setDeleting(null); },
  });

  const anonymizeMutation = useMutation({
    mutationFn: (id: string) => api(`/customers/${id}/anonymize`, { method: "POST" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.customers.all }); toast("Customer anonymized"); setAnonymizing(null); },
  });

  const columns: DataTableColumn<Customer>[] = [
    {
      key: "name",
      header: "Name",
      className: "font-medium",
      render: (c) => <>{c.name}{c.phone && <span className="block text-xs text-[var(--muted)]">{c.phone}</span>}</>,
    },
    { key: "phone", header: "Phone", render: (c) => c.phone ?? "—" },
    { key: "group", header: "Group", render: (c) => (c.customer_group ? <Badge>{c.customer_group}</Badge> : "—") },
    { key: "balance", header: "Balance", align: "right", render: (c) => formatCurrency(c.balance) },
    { key: "points", header: "Points", align: "right", render: (c) => c.loyalty_points },
    { key: "status", header: "Status", render: (c) => <Badge tone={c.is_active ? "success" : "default"}>{c.is_active ? "Active" : "Inactive"}</Badge> },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        addLabel={can("customers.create") ? "Add customer" : undefined}
        onAdd={can("customers.create") ? () => { setEditing(null); setDrawerOpen(true); } : undefined}
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(c) => c.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No customers yet."
          actions={(c) => (
            <>
              {can("customers.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(c); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
              {can("customers.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(c)}><Trash2 className="h-3.5 w-3.5" /></Button>}
              {can("customers.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-primary)]" onClick={() => setAnonymizing(c)}><UserMinus className="h-3.5 w-3.5" /></Button>}
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
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : "Add customer"}>
        <CustomerForm key={editing?.id ?? "new"} isEdit={!!editing} customerId={editing?.id} defaultValues={editing ? { name: editing.name, is_active: editing.is_active, customer_group: editing.customer_group ?? undefined } : undefined} onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      <Dialog open={!!deleting} onClose={() => setDeleting(null)} title="Delete customer" description={`Delete "${deleting?.name}"? This cannot be undone.`} confirmLabel="Delete" confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />

      <Dialog
        open={!!anonymizing}
        onClose={() => setAnonymizing(null)}
        title={t("retention.anonymize")}
        description={t("retention.confirm")}
        confirmLabel={t("retention.anonymize")}
        confirmVariant="primary"
        loading={anonymizeMutation.isPending}
        onConfirm={() => anonymizing && anonymizeMutation.mutate(anonymizing.id)}
      />
    </div>
  );
}
