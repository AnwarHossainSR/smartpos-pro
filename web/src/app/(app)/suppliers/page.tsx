"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Pencil, Trash2 } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
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
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["suppliers"] }); toast(isEdit ? "Supplier updated" : "Supplier created"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
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
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search); const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<Supplier | null>(null);
  const [deleting, setDeleting] = useState<Supplier | null>(null);

  const { data, isLoading } = useQuery<Paginated<Supplier>>({
    queryKey: ["suppliers", debouncedSearch, page],
    queryFn: () => api<Paginated<Supplier>>(`/suppliers?search=${encodeURIComponent(debouncedSearch)}&page=${page}&per_page=20`),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/suppliers/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["suppliers"] }); toast("Supplier deleted"); setDeleting(null); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        {can("suppliers.create") && <Button size="sm" onClick={() => { setEditing(null); setDrawerOpen(true); }}><Plus className="h-4 w-4" />Add supplier</Button>}
      </div>
      <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder="Search…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} /></div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Company</TableHead><TableHead>Phone</TableHead><TableHead className="text-end">Balance</TableHead><TableHead>Status</TableHead><TableHead className="w-20" /></TableRow></TableHeader>
          <TableBody>
            {isLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 6 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
              : data?.data?.length === 0 ? <TableRow><TableCell colSpan={6} className="py-12 text-center text-[var(--muted)]">No suppliers yet.</TableCell></TableRow>
              : data?.data?.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="font-medium">{s.name}{s.email && <span className="block text-xs text-[var(--muted)]">{s.email}</span>}</TableCell>
                  <TableCell>{s.company ?? "—"}</TableCell>
                  <TableCell>{s.phone ?? "—"}</TableCell>
                  <TableCell className="text-end">{formatCurrency(s.balance)}</TableCell>
                  <TableCell><Badge tone={s.is_active ? "success" : "default"}>{s.is_active ? "Active" : "Inactive"}</Badge></TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {can("suppliers.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(s); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
                      {can("suppliers.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(s)}><Trash2 className="h-3.5 w-3.5" /></Button>}
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
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : "Add supplier"}>
        <SupplierForm key={editing?.id ?? "new"} isEdit={!!editing} supplierId={editing?.id} defaultValues={editing ? { name: editing.name, company: editing.company ?? undefined, email: editing.email ?? undefined, phone: editing.phone ?? undefined, address: editing.address ?? undefined, tax_number: editing.tax_number ?? undefined, opening_balance: editing.opening_balance, is_active: editing.is_active } : undefined} onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      <Dialog open={!!deleting} onClose={() => setDeleting(null)} title="Delete supplier" description={`Delete "${deleting?.name}"?`} confirmLabel="Delete" confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />
    </div>
  );
}
