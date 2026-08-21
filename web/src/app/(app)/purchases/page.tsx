import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Eye, Trash2 } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { Combobox } from "@/components/ui/combobox";
import { formatCurrency, formatDate } from "@/lib/utils";
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

interface Purchase {
  id: string;
  reference_number: string;
  status: string;
  payment_status: string;
  total: number;
  paid_amount: number;
  due_amount: number;
  purchased_at: string;
  supplier: { id: string; name: string } | null;
  items?: { name: string; quantity: number; unit_cost: number; line_total: number }[];
  payments?: { method: string; amount: number }[];
}

interface Supplier { id: string; name: string; }
interface Warehouse { id: string; name: string; is_default: boolean; }
interface Product { id: string; name: string; sku: string | null; purchase_price: number; }

const purchaseSchema = z.object({
  warehouse_id: z.string(),
  supplier_id: z.string().optional(),
  status: z.enum(["draft", "ordered", "received"]),
  discount_amount: z.number().min(0).optional(),
  shipping_amount: z.number().min(0).optional(),
  note: z.string().optional(),
  items: z.array(z.object({
    product_id: z.string(),
    quantity: z.number().min(0.001),
    unit_cost: z.number().min(0),
  })).min(1, "Add at least one item"),
  payments: z.array(z.object({
    method: z.string(),
    amount: z.number().min(0),
  })).optional(),
});
type PurchaseValues = z.infer<typeof purchaseSchema>;

function PurchaseForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const [itemNames, setItemNames] = useState<Record<string, string>>({});

  const { data: warehouses } = useQuery({ queryKey: ["warehouses-all"], queryFn: () => api<{ data: Warehouse[] }>("/warehouses") });
  const { data: suppliers } = useQuery<Paginated<Supplier>>({ queryKey: ["suppliers-all"], queryFn: () => api<Paginated<Supplier>>("/suppliers?per_page=200") });

  const { register, handleSubmit, control, formState: { errors }, watch } = useForm<PurchaseValues>({
    resolver: zodResolver(purchaseSchema),
    defaultValues: { status: "received", items: [], payments: [] },
  });

  const { fields: itemFields, append: appendItem, remove: removeItem } = useFieldArray({ control, name: "items" });
  const { fields: payFields, append: appendPay, remove: removePay } = useFieldArray({ control, name: "payments" });

  const items = watch("items");
  const subtotal = items.reduce((s, i) => s + (i.quantity || 0) * (i.unit_cost || 0), 0);

  const mutation = useMutation({
    mutationFn: (data: PurchaseValues) => api("/purchases", { method: "POST", idempotencyKey: crypto.randomUUID(), body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["purchases"] }); toast("Purchase created"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-5">
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Warehouse *</Label>
          <Select {...register("warehouse_id", { setValueAs: (v) => v ? String(v) : undefined })}>
            <option value="">— Select —</option>
            {warehouses?.data?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
          </Select>
        </div>
        <div>
          <Label>Supplier</Label>
          <Select {...register("supplier_id", { setValueAs: (v) => v ? String(v) : undefined })}>
            <option value="">— None —</option>
            {suppliers?.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </Select>
        </div>
      </div>

      <div>
        <Label>Status</Label>
        <Select {...register("status")}>
          <option value="received">Received (stock added)</option>
          <option value="ordered">Ordered</option>
          <option value="draft">Draft</option>
        </Select>
      </div>

      {/* Items */}
      <div>
        <div className="mb-2 flex items-center justify-between">
          <Label>Items *</Label>
        </div>
        <div className="mb-2">
          <Combobox<Product>
            endpoint="/products"
            queryKey="products"
            value={undefined}
            clearOnSelect
            onChange={(_id, product) => {
              if (!product) return;
              appendItem({ product_id: product.id, quantity: 1, unit_cost: product.purchase_price });
              setItemNames((names) => ({ ...names, [product.id]: product.name }));
            }}
            label={(p) => p.name}
            hint={(p) => `${p.sku ? p.sku + " · " : ""}${formatCurrency(p.purchase_price)}`}
            placeholder="Search product to add…"
            emptyLabel="No products match"
          />
        </div>
        <div className="space-y-2">
          {itemFields.map((field, idx) => (
            <div key={field.id} className="flex items-center gap-2">
              <Input className="flex-1" disabled value={itemNames[items[idx]?.product_id] ?? items[idx]?.product_id ?? ""} />
              <Input type="number" className="w-24" step="0.001" min="0.001" {...register(`items.${idx}.quantity`, { valueAsNumber: true })} placeholder="Qty" />
              <Input type="number" className="w-28" step="0.01" min="0" {...register(`items.${idx}.unit_cost`, { valueAsNumber: true })} placeholder="Cost" />
              <Button type="button" variant="ghost" size="icon" className="h-8 w-8 shrink-0 text-[var(--color-danger)]" onClick={() => removeItem(idx)}><Trash2 className="h-3.5 w-3.5" /></Button>
            </div>
          ))}
          {errors.items && <p className="text-xs text-[var(--color-danger)]">Add at least one item</p>}
        </div>
        <p className="mt-2 text-end text-sm font-medium">Subtotal: {formatCurrency(subtotal)}</p>
      </div>

      {/* Payments */}
      <div>
        <div className="mb-2 flex items-center justify-between">
          <Label>Payments</Label>
          <Button type="button" variant="outline" size="sm" onClick={() => appendPay({ method: "cash", amount: subtotal })}>+ Add payment</Button>
        </div>
        {payFields.map((field, idx) => (
          <div key={field.id} className="flex items-center gap-2 mb-2">
            <Select className="w-36" {...register(`payments.${idx}.method`)}>
              <option value="cash">Cash</option><option value="card">Card</option><option value="bank">Bank</option>
            </Select>
            <Input type="number" step="0.01" min="0" {...register(`payments.${idx}.amount`, { valueAsNumber: true })} />
            <Button type="button" variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={() => removePay(idx)}><Trash2 className="h-3.5 w-3.5" /></Button>
          </div>
        ))}
      </div>

      <Button type="submit" disabled={mutation.isPending} className="w-full">{mutation.isPending ? "Saving…" : "Create purchase"}</Button>
    </form>
  );
}

function PurchaseDetailModal({ purchase, onClose }: { purchase: Purchase; onClose: () => void }) {
  const statusColor: Record<string, "success" | "warning" | "default"> = { received: "success", ordered: "warning", draft: "default" };
  return (
    <Dialog open title={`Purchase #${purchase.reference_number}`} onClose={onClose}>
      <div className="space-y-4 text-sm">
        <div className="grid grid-cols-2 gap-2">
          <div><span className="text-[var(--muted)]">Status</span><br /><Badge tone={statusColor[purchase.status] ?? "default"}>{purchase.status}</Badge></div>
          <div><span className="text-[var(--muted)]">Payment</span><br /><Badge tone={purchase.payment_status === "paid" ? "success" : "warning"}>{purchase.payment_status}</Badge></div>
          <div><span className="text-[var(--muted)]">Supplier</span><br />{purchase.supplier?.name ?? "—"}</div>
          <div><span className="text-[var(--muted)]">Date</span><br />{formatDate(purchase.purchased_at)}</div>
        </div>
        <hr />
        {purchase.items?.map((item, i) => (
          <div key={i} className="flex justify-between">
            <span>{item.name} × {item.quantity}</span><span>{formatCurrency(item.line_total)}</span>
          </div>
        ))}
        <hr />
        <div className="flex justify-between font-semibold"><span>Total</span><span>{formatCurrency(purchase.total)}</span></div>
        {purchase.due_amount > 0 && <div className="flex justify-between text-amber-600"><span>Due</span><span>{formatCurrency(purchase.due_amount)}</span></div>}
        <Button variant="outline" onClick={onClose} className="w-full">Close</Button>
      </div>
    </Dialog>
  );
}

export default function PurchasesPage() {
  const { can } = useAuth();
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search); const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [detail, setDetail] = useState<Purchase | null>(null);

  const { data, isLoading } = useQuery<Paginated<Purchase>>({
    queryKey: ["purchases", debouncedSearch, page],
    queryFn: () => api<Paginated<Purchase>>(`/purchases?search=${encodeURIComponent(debouncedSearch)}&page=${page}&per_page=20`),
  });

  const { data: purchaseDetail } = useQuery<{ data: Purchase }>({
    queryKey: ["purchase-detail", detail?.id],
    queryFn: () => api<{ data: Purchase }>(`/purchases/${detail?.id}`),
    enabled: !!detail?.id,
  });

  const statusColor: Record<string, "success" | "warning" | "default"> = { received: "success", ordered: "warning", draft: "default" };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        {can("purchases.create") && <Button size="sm" onClick={() => setDrawerOpen(true)}><Plus className="h-4 w-4" />New purchase</Button>}
      </div>
      <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder="Search…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} /></div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Reference</TableHead><TableHead>Supplier</TableHead><TableHead>Date</TableHead><TableHead className="text-end">Total</TableHead><TableHead>Status</TableHead><TableHead>Payment</TableHead><TableHead className="w-16" /></TableRow></TableHeader>
          <TableBody>
            {isLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 7 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
              : data?.data?.length === 0 ? <TableRow><TableCell colSpan={7} className="py-12 text-center text-[var(--muted)]">No purchases yet.</TableCell></TableRow>
              : data?.data?.map((p) => (
                <TableRow key={p.id}>
                  <TableCell className="font-mono text-xs font-medium">{p.reference_number}</TableCell>
                  <TableCell>{p.supplier?.name ?? "—"}</TableCell>
                  <TableCell className="text-[var(--muted)]">{formatDate(p.purchased_at)}</TableCell>
                  <TableCell className="text-end">{formatCurrency(p.total)}</TableCell>
                  <TableCell><Badge tone={statusColor[p.status] ?? "default"}>{p.status}</Badge></TableCell>
                  <TableCell><Badge tone={p.payment_status === "paid" ? "success" : "warning"}>{p.payment_status}</Badge></TableCell>
                  <TableCell><Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setDetail(p)}><Eye className="h-3.5 w-3.5" /></Button></TableCell>
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
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="New purchase" width="max-w-2xl">
        <PurchaseForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      {detail && purchaseDetail && <PurchaseDetailModal purchase={purchaseDetail.data} onClose={() => setDetail(null)} />}
    </div>
  );
}
