import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Eye, Trash2 } from "lucide-react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, type Envelope, type Paginated } from "@/lib/api";
import { useWarehouses } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Named } from "@/types/domain";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Combobox } from "@/components/ui/combobox";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
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
  supplier: Named | null;
  items?: { name: string; quantity: number; unit_cost: number; line_total: number }[];
  payments?: { method: string; amount: number }[];
}

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

const statusColor: Record<string, "success" | "warning" | "default"> = {
  received: "success", ordered: "warning", draft: "default",
};

function PurchaseForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const [itemNames, setItemNames] = useState<Record<string, string>>({});

  const { warehouses } = useWarehouses();
  // Picker-only supplier list: `options()` keeps it out of the paginated suppliers list
  // cache, which holds a different (searched, 20-per-page) slice of the same endpoint.
  const { data: suppliers } = useQuery({
    queryKey: queryKeys.suppliers.options(),
    queryFn: () => api<Paginated<Named>>("/suppliers?per_page=200"),
    staleTime: 5 * 60_000,
  });

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
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.purchases.all }); toast("Purchase created"); onSuccess(); },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-5">
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Warehouse *</Label>
          <Select {...register("warehouse_id", { setValueAs: (v) => v ? String(v) : undefined })}>
            <option value="">— Select —</option>
            {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
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
            queryKey={queryKeys.products.options()}
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
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [detail, setDetail] = useState<Purchase | null>(null);

  const list = useResourceList<Purchase>({
    queryKey: queryKeys.purchases.lists(),
    queryFn: (p) => api<Paginated<Purchase>>(`/purchases?${buildListQuery(p)}`),
  });

  // The row carries only the list projection; the modal needs items and payments.
  const { data: purchaseDetail } = useQuery({
    queryKey: queryKeys.purchases.detail(detail?.id),
    queryFn: () => api<Envelope<Purchase>>(`/purchases/${detail?.id}`),
    enabled: !!detail?.id,
  });

  const columns: DataTableColumn<Purchase>[] = [
    { key: "reference", header: "Reference", className: "font-mono text-xs font-medium", render: (p) => p.reference_number },
    { key: "supplier", header: "Supplier", render: (p) => p.supplier?.name ?? "—" },
    { key: "date", header: "Date", className: "text-[var(--muted)]", render: (p) => formatDate(p.purchased_at) },
    { key: "total", header: "Total", align: "right", render: (p) => formatCurrency(p.total) },
    { key: "status", header: "Status", render: (p) => <Badge tone={statusColor[p.status] ?? "default"}>{p.status}</Badge> },
    { key: "payment", header: "Payment", render: (p) => <Badge tone={p.payment_status === "paid" ? "success" : "warning"}>{p.payment_status}</Badge> },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        addLabel={can("purchases.create") ? "New purchase" : undefined}
        onAdd={can("purchases.create") ? () => setDrawerOpen(true) : undefined}
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
          emptyMessage="No purchases yet."
          actions={(p) => (
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setDetail(p)}><Eye className="h-3.5 w-3.5" /></Button>
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
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="New purchase" width="max-w-2xl">
        <PurchaseForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      {detail && purchaseDetail && <PurchaseDetailModal purchase={purchaseDetail.data} onClose={() => setDetail(null)} />}
    </div>
  );
}
