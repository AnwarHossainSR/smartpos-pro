"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Pencil, Trash2 } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Customer {
  id: number;
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

function CustomerForm({ defaultValues, customerId, isEdit, onSuccess }: { defaultValues?: Partial<FormValues>; customerId?: number; isEdit: boolean; onSuccess: () => void; }) {
  const qc = useQueryClient();
  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { is_active: true, ...defaultValues },
  });
  const mutation = useMutation({
    mutationFn: (data: FormValues) =>
      isEdit ? api(`/customers/${customerId}`, { method: "PUT", body: data }) : api("/customers", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["customers"] }); toast(isEdit ? "Customer updated" : "Customer created"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
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
  const qc = useQueryClient();
  const [search, setSearch] = useState(""); const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<Customer | null>(null);
  const [deleting, setDeleting] = useState<Customer | null>(null);

  const { data, isLoading } = useQuery<Paginated<Customer>>({
    queryKey: ["customers", search, page],
    queryFn: () => api<Paginated<Customer>>(`/customers?search=${encodeURIComponent(search)}&page=${page}&per_page=20`),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api(`/customers/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["customers"] }); toast("Customer deleted"); setDeleting(null); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Customers</h1>
        {can("customers.create") && <Button size="sm" onClick={() => { setEditing(null); setDrawerOpen(true); }}><Plus className="h-4 w-4" />Add customer</Button>}
      </div>
      <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder="Search…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} /></div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Phone</TableHead><TableHead>Group</TableHead><TableHead className="text-end">Balance</TableHead><TableHead className="text-end">Points</TableHead><TableHead>Status</TableHead><TableHead className="w-20" /></TableRow></TableHeader>
          <TableBody>
            {isLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 7 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
              : data?.data?.length === 0 ? <TableRow><TableCell colSpan={7} className="py-12 text-center text-[var(--muted)]">No customers yet.</TableCell></TableRow>
              : data?.data?.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-medium">{c.name}{c.email && <span className="block text-xs text-[var(--muted)]">{c.email}</span>}</TableCell>
                  <TableCell>{c.phone ?? "—"}</TableCell>
                  <TableCell>{c.customer_group ? <Badge>{c.customer_group}</Badge> : "—"}</TableCell>
                  <TableCell className="text-end">{formatCurrency(c.balance)}</TableCell>
                  <TableCell className="text-end">{c.loyalty_points}</TableCell>
                  <TableCell><Badge tone={c.is_active ? "success" : "default"}>{c.is_active ? "Active" : "Inactive"}</Badge></TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {can("customers.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(c); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
                      {can("customers.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(c)}><Trash2 className="h-3.5 w-3.5" /></Button>}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
        {data?.meta && (
          <Pagination
            page={page}
            lastPage={data.meta.last_page}
            onPageChange={setPage}
            label={`Page ${data.meta.current_page} of ${data.meta.last_page}`}
          />
        )}
      </div>
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : "Add customer"}>
        <CustomerForm key={editing?.id ?? "new"} isEdit={!!editing} customerId={editing?.id} defaultValues={editing ? { name: editing.name, is_active: editing.is_active, customer_group: editing.customer_group ?? undefined } : undefined} onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      <Dialog open={!!deleting} onClose={() => setDeleting(null)} title="Delete customer" description={`Delete "${deleting?.name}"? This cannot be undone.`} confirmLabel="Delete" confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />
    </div>
  );
}
