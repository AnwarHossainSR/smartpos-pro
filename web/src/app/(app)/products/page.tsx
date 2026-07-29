"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Pencil, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { formatCurrency, exportToCsv } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

/* ---- types ---- */
interface Category { id: number; name: string; }
interface Brand { id: number; name: string; }
interface Unit { id: number; name: string; }
interface Warehouse { id: number; name: string; is_default: boolean; }

interface Product {
  id: number;
  name: string;
  sku: string | null;
  description: string | null;
  selling_price: number;
  purchase_price: number;
  stock: number;
  is_active: boolean;
  category: Category | null;
  brand: Brand | null;
}

/* ---- form schema ---- */
const schema = z.object({
  name: z.string().min(1, "Name is required"),
  sku: z.string().optional(),
  description: z.string().optional(),
  category_id: z.number().optional(),
  brand_id: z.number().optional(),
  unit_id: z.number().optional(),
  purchase_price: z.number().min(0, "Must be ≥ 0"),
  selling_price: z.number().min(0.01, "Must be > 0"),
  is_active: z.boolean(),
  opening_stock_qty: z.number().min(0).optional(),
  opening_stock_warehouse_id: z.number().optional(),
});
type FormValues = z.infer<typeof schema>;

/* ---- product form ---- */
function ProductForm({
  defaultValues,
  productId,
  onSuccess,
  isEdit,
}: {
  defaultValues?: Partial<FormValues>;
  productId?: number;
  onSuccess: () => void;
  isEdit: boolean;
}) {
  const qc = useQueryClient();
  const { data: cats } = useQuery({
    queryKey: ["categories-all"],
    queryFn: () => api<{ data: Category[] }>("/categories?per_page=200"),
  });
  const { data: brands } = useQuery({
    queryKey: ["brands-all"],
    queryFn: () => api<{ data: Brand[] }>("/brands?per_page=200"),
  });
  const { data: units } = useQuery({
    queryKey: ["units-all"],
    queryFn: () => api<{ data: Unit[] }>("/units?per_page=200"),
  });
  const { data: warehouses } = useQuery({
    queryKey: ["warehouses-all"],
    queryFn: () => api<{ data: Warehouse[] }>("/warehouses"),
    enabled: !isEdit,
  });

  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { is_active: true, purchase_price: 0, selling_price: 0, ...defaultValues },
  });

  const mutation = useMutation({
    mutationFn: (data: FormValues) => {
      const { opening_stock_qty, opening_stock_warehouse_id, ...rest } = data;
      const payload: Record<string, unknown> = { ...rest };
      if (!isEdit && opening_stock_qty && opening_stock_qty > 0) {
        payload.opening_stock = {
          quantity: opening_stock_qty,
          warehouse_id: opening_stock_warehouse_id,
        };
      }
      return isEdit
        ? api(`/products/${productId}`, { method: "PUT", body: payload })
        : api("/products", { method: "POST", body: payload });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] });
      toast(isEdit ? "Product updated" : "Product created");
      onSuccess();
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Something went wrong", "error");
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Name *</Label>
        <Input {...register("name")} placeholder="Product name" />
        {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>SKU / Code</Label>
          <Input {...register("sku")} placeholder="Auto-generated" />
        </div>
        <div>
          <Label>Status</Label>
          <Select {...register("is_active", { setValueAs: (v: string) => v === "true" })}>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </Select>
        </div>
      </div>

      <div>
        <Label>Description</Label>
        <Textarea {...register("description")} placeholder="Optional description" />
      </div>

      <div className="grid grid-cols-3 gap-3">
        <div>
          <Label>Category</Label>
          <Select {...register("category_id", { setValueAs: (v) => (v ? Number(v) : undefined) })}>
            <option value="">— None —</option>
            {cats?.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>
        <div>
          <Label>Brand</Label>
          <Select {...register("brand_id", { setValueAs: (v) => (v ? Number(v) : undefined) })}>
            <option value="">— None —</option>
            {brands?.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </Select>
        </div>
        <div>
          <Label>Unit</Label>
          <Select {...register("unit_id", { setValueAs: (v) => (v ? Number(v) : undefined) })}>
            <option value="">— None —</option>
            {units?.data?.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Cost price *</Label>
          <Input type="number" step="0.01" min="0" {...register("purchase_price", { valueAsNumber: true })} />
          {errors.purchase_price && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.purchase_price.message}</p>}
        </div>
        <div>
          <Label>Sell price *</Label>
          <Input type="number" step="0.01" min="0" {...register("selling_price", { valueAsNumber: true })} />
          {errors.selling_price && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.selling_price.message}</p>}
        </div>
      </div>

      {!isEdit && (
        <div className="rounded-[var(--radius)] border border-dashed border-[var(--border)] p-3 space-y-3">
          <p className="text-xs font-medium text-[var(--muted)] uppercase tracking-wide">Opening stock (optional)</p>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>Quantity</Label>
              <Input type="number" step="0.001" min="0" {...register("opening_stock_qty", { valueAsNumber: true })} placeholder="0" />
            </div>
            <div>
              <Label>Warehouse</Label>
              <Select {...register("opening_stock_warehouse_id", { setValueAs: (v) => (v ? Number(v) : undefined) })}>
                <option value="">— Select —</option>
                {warehouses?.data?.map((w) => (
                  <option key={w.id} value={w.id}>{w.name}{w.is_default ? " (default)" : ""}</option>
                ))}
              </Select>
            </div>
          </div>
        </div>
      )}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? "Saving…" : isEdit ? "Update product" : "Create product"}
        </Button>
      </div>
    </form>
  );
}

/* ---- main page ---- */
export default function ProductsPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [deleting, setDeleting] = useState<Product | null>(null);
  const [selected, setSelected] = useState<Set<string | number>>(new Set());

  const { data, isLoading } = useQuery<Paginated<Product>>({
    queryKey: ["products", search, page],
    queryFn: () =>
      api<Paginated<Product>>(
        `/products?search=${encodeURIComponent(search)}&page=${page}&per_page=20`,
      ),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api(`/products/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] });
      toast("Product deleted");
      setDeleting(null);
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Delete failed", "error");
    },
  });

  const openCreate = () => { setEditing(null); setDrawerOpen(true); };
  const openEdit = (p: Product) => { setEditing(p); setDrawerOpen(true); };
  const closeDrawer = () => { setDrawerOpen(false); setEditing(null); };

  const lastPage = data?.meta?.last_page ?? 1;

  const columns: DataTableColumn<Product>[] = [
    { key: "name", header: "Name", render: (p) => <span className="font-medium">{p.name}</span> },
    {
      key: "sku",
      header: "SKU",
      render: (p) => <span className="font-mono text-xs text-[var(--muted)]">{p.sku ?? "—"}</span>,
    },
    {
      key: "category",
      header: "Category",
      render: (p) => p.category?.name ?? <span className="text-[var(--muted)]">—</span>,
    },
    { key: "cost", header: "Cost", align: "right", render: (p) => formatCurrency(p.purchase_price) },
    {
      key: "price",
      header: "Price",
      align: "right",
      className: "font-medium",
      render: (p) => formatCurrency(p.selling_price),
    },
    {
      key: "stock",
      header: "Stock",
      align: "right",
      render: (p) => (
        <span className={(p.stock ?? 0) <= 0 ? "text-[var(--color-danger)]" : ""}>{p.stock ?? 0}</span>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (p) => <Badge tone={p.is_active ? "success" : "default"}>{p.is_active ? "active" : "inactive"}</Badge>,
    },
  ];

  function handleExport() {
    const rows = (data?.data ?? []).map((p) => ({
      name: p.name,
      sku: p.sku ?? "",
      category: p.category?.name ?? "",
      brand: p.brand?.name ?? "",
      cost_price: p.purchase_price,
      selling_price: p.selling_price,
      stock: p.stock ?? 0,
      status: p.is_active ? "active" : "inactive",
    }));
    exportToCsv("products.csv", rows);
  }

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Products</h1>

      <ListToolbar
        search={search}
        onSearchChange={(v) => { setSearch(v); setPage(1); }}
        searchPlaceholder="Search products…"
        onExport={handleExport}
        addLabel={can("products.create") ? "Add product" : undefined}
        onAdd={can("products.create") ? openCreate : undefined}
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(p) => p.id}
          loading={isLoading}
          selectable
          selectedKeys={selected}
          onSelectionChange={setSelected}
          emptyMessage={search ? "No products match your search." : "No products yet. Add your first product."}
          actions={(p) => (
            <>
              {can("products.update") && (
                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(p)}>
                  <Pencil className="h-3.5 w-3.5" />
                </Button>
              )}
              {can("products.delete") && (
                <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(p)}>
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              )}
            </>
          )}
        />

        {data?.meta && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
            <p className="text-sm text-[var(--muted)]">
              Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} products
            </p>
            <div className="flex gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}
      </div>

      <Drawer
        open={drawerOpen}
        onClose={closeDrawer}
        title={editing ? `Edit: ${editing.name}` : "Add product"}
      >
        <ProductForm
          key={editing?.id ?? "new"}
          isEdit={!!editing}
          productId={editing?.id}
          defaultValues={
            editing
              ? {
                  name: editing.name,
                  sku: editing.sku ?? undefined,
                  description: editing.description ?? undefined,
                  purchase_price: editing.purchase_price,
                  selling_price: editing.selling_price,
                  is_active: editing.is_active,
                  category_id: editing.category?.id,
                  brand_id: editing.brand?.id,
                }
              : undefined
          }
          onSuccess={closeDrawer}
        />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete product"
        description={`Are you sure you want to delete "${deleting?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        onConfirm={() => deleting && deleteMutation.mutate(deleting.id)}
        loading={deleteMutation.isPending}
      />
    </div>
  );
}
