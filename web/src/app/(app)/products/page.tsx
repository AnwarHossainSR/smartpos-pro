import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router";
import { Barcode, Pencil, Trash2 } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, apiUpload, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
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
import { Pagination } from "@/components/ui/pagination";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { useTranslation } from "@/i18n/provider";
import { useAllTaxGroups } from "@/features/tax/hooks";
import type { TaxGroup } from "@/features/tax/types";
import { ImageUpload } from "@/components/ui/image-upload";

/* ---- types ---- */
interface Category { id: string; name: string; }
interface Brand { id: string; name: string; }
interface Unit { id: string; name: string; }
interface Warehouse { id: string; name: string; is_default: boolean; }

interface Product {
  id: string;
  name: string;
  sku: string | null;
  description: string | null;
  selling_price: number;
  purchase_price: number;
  stock: number;
  is_active: boolean;
  category: Category | null;
  brand: Brand | null;
  image_url?: string | null;
  tax_group?: TaxGroup | null;
  tax_group_id?: string | null;
  is_weighed?: boolean;
  weight_unit?: string | null;
  price_per_unit?: number | null;
}

/* ---- form schema ---- */
const schema = z.object({
  name: z.string().min(1, "Name is required"),
  sku: z.string().optional(),
  description: z.string().optional(),
  category_id: z.string().optional(),
  brand_id: z.string().optional(),
  unit_id: z.string().optional(),
  tax_group_id: z.string().nullable().optional(),
  purchase_price: z.number().min(0, "Must be ≥ 0"),
  selling_price: z.number().min(0.01, "Must be > 0"),
  is_active: z.boolean(),
  is_weighed: z.boolean().optional(),
  weight_unit: z.string().optional(),
  price_per_unit: z.number().min(0).optional(),
  opening_stock_qty: z.number().min(0).optional(),
  opening_stock_warehouse_id: z.string().optional(),
});
type FormValues = z.infer<typeof schema>;

/* ---- product form ---- */
function ProductForm({
  defaultValues,
  productId,
  onSuccess,
  isEdit,
  currentImageUrl,
}: {
  defaultValues?: Partial<FormValues>;
  productId?: string;
  onSuccess: () => void;
  isEdit: boolean;
  currentImageUrl?: string | null;
}) {
  const qc = useQueryClient();
  const t = useTranslation();
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imageRemoved, setImageRemoved] = useState(false);
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
  const { data: taxGroups } = useAllTaxGroups();

  const { register, handleSubmit, watch, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { is_active: true, purchase_price: 0, selling_price: 0, is_weighed: false, ...defaultValues },
  });

  const isWeighed = watch("is_weighed");

  const mutation = useMutation({
    mutationFn: async (data: FormValues) => {
      const { opening_stock_qty, opening_stock_warehouse_id, ...rest } = data;
      const payload: Record<string, unknown> = { ...rest };
      if (!isEdit && opening_stock_qty && opening_stock_qty > 0) {
        payload.opening_stock = {
          quantity: opening_stock_qty,
          warehouse_id: opening_stock_warehouse_id,
        };
      }
      const result = isEdit
        ? await api<{ data: { id: string } }>(`/products/${productId}`, { method: "PUT", body: payload })
        : await api<{ data: { id: string } }>("/products", { method: "POST", body: payload });

      const pid = isEdit ? productId! : result.data.id;

      if (imageFile) {
        const fd = new FormData();
        fd.append("file", imageFile);
        await apiUpload(`/products/${pid}/image`, fd);
      } else if (isEdit && imageRemoved) {
        await api(`/products/${pid}/image`, { method: "DELETE" });
      }

      return result;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] });
      toast(isEdit ? t("products.updateSuccess") : t("products.createSuccess"));
      onSuccess();
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : t("products.somethingWentWrong"), "error");
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>{t("products.form.name")} *</Label>
        <Input {...register("name")} placeholder={t("products.form.namePlaceholder")} />
        {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>{t("products.form.sku")}</Label>
          <Input {...register("sku")} placeholder={t("products.form.skuPlaceholder")} />
        </div>
        <div>
          <Label>{t("products.form.status")}</Label>
          <Select {...register("is_active", { setValueAs: (v: string) => v === "true" })}>
            <option value="true">{t("products.form.active")}</option>
            <option value="false">{t("products.form.inactive")}</option>
          </Select>
        </div>
      </div>

      <div>
        <Label>{t("products.form.image")}</Label>
        <ImageUpload
          currentUrl={isEdit ? currentImageUrl : undefined}
          onUpload={(file) => { setImageFile(file); setImageRemoved(false); }}
          onRemove={() => { setImageFile(null); setImageRemoved(true); }}
        />
      </div>

      <div>
        <Label>{t("products.form.description")}</Label>
        <Textarea {...register("description")} placeholder={t("products.form.descriptionPlaceholder")} />
      </div>

      <div className="grid grid-cols-3 gap-3">
        <div>
          <Label>{t("products.form.category")}</Label>
          <Select {...register("category_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
            <option value="">{t("products.form.none")}</option>
            {cats?.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>
        <div>
          <Label>{t("products.form.brand")}</Label>
          <Select {...register("brand_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
            <option value="">{t("products.form.none")}</option>
            {brands?.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </Select>
        </div>
        <div>
          <Label>{t("products.form.unit")}</Label>
          <Select {...register("unit_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
            <option value="">{t("products.form.none")}</option>
            {units?.data?.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </Select>
        </div>
      </div>

      <div>
        <Label>{t("products.form.taxGroup")}</Label>
        <Select {...register("tax_group_id", { setValueAs: (v) => (v ? String(v) : null) })}>
          <option value="">{t("products.form.taxGroupNone")}</option>
          {taxGroups?.data?.map((g) => (
            <option key={g.id} value={g.id}>{g.name}{g.is_default ? ` (${t("products.form.default")})` : ""}</option>
          ))}
        </Select>
        <p className="mt-1 text-xs text-[var(--muted)]">{t("products.form.taxGroupHint")}</p>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>{t("products.form.costPrice")} *</Label>
          <Input type="number" step="0.01" min="0" {...register("purchase_price", { valueAsNumber: true })} />
          {errors.purchase_price && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.purchase_price.message}</p>}
        </div>
        <div>
          <Label>{t("products.form.sellPrice")} *</Label>
          <Input type="number" step="0.01" min="0" {...register("selling_price", { valueAsNumber: true })} />
          {errors.selling_price && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.selling_price.message}</p>}
        </div>
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] p-3 space-y-3">
        <div className="flex items-center gap-2">
          <input type="checkbox" id="is_weighed" {...register("is_weighed")} className="h-4 w-4" />
          <Label htmlFor="is_weighed">{t("products.weighed")}</Label>
        </div>
        {isWeighed && (
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>{t("products.weight_unit")}</Label>
              <Select {...register("weight_unit")}>
                <option value="kg">{t("products.per_kg")}</option>
                <option value="lb">{t("products.per_lb")}</option>
                <option value="g">per g</option>
              </Select>
            </div>
            <div>
              <Label>{t("products.price_per_unit")}</Label>
              <Input type="number" step="0.01" min="0" {...register("price_per_unit", { valueAsNumber: true })} />
            </div>
          </div>
        )}
      </div>

      {!isEdit && (
        <div className="rounded-[var(--radius)] border border-dashed border-[var(--border)] p-3 space-y-3">
          <p className="text-xs font-medium text-[var(--muted)] uppercase tracking-wide">{t("products.form.openingStock")}</p>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>{t("products.form.quantity")}</Label>
              <Input type="number" step="0.001" min="0" {...register("opening_stock_qty", { valueAsNumber: true })} placeholder="0" />
            </div>
            <div>
              <Label>{t("products.form.warehouse")}</Label>
              <Select {...register("opening_stock_warehouse_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
                <option value="">{t("products.form.selectWarehouse")}</option>
                {warehouses?.data?.map((w) => (
                  <option key={w.id} value={w.id}>{w.name}{w.is_default ? ` (${t("products.form.default")})` : ""}</option>
                ))}
              </Select>
            </div>
          </div>
        </div>
      )}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? t("products.form.saving") : isEdit ? t("products.form.update") : t("products.form.create")}
        </Button>
      </div>
    </form>
  );
}

/* ---- main page ---- */
export default function ProductsPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const t = useTranslation();
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [deleting, setDeleting] = useState<Product | null>(null);
  const [selected, setSelected] = useState<Set<string | number>>(new Set());

  const { data, isLoading } = useQuery<Paginated<Product>>({
    queryKey: ["products", debouncedSearch, page],
    queryFn: () =>
      api<Paginated<Product>>(
        `/products?search=${encodeURIComponent(debouncedSearch)}&page=${page}&per_page=20`,
      ),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/products/${id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] });
      toast(t("products.deleteSuccess"));
      setDeleting(null);
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : t("products.deleteFailed"), "error");
    },
  });

  const openCreate = () => { setEditing(null); setDrawerOpen(true); };
  const openEdit = (p: Product) => { setEditing(p); setDrawerOpen(true); };
  const closeDrawer = () => { setDrawerOpen(false); setEditing(null); };

  const lastPage = data?.meta?.last_page ?? 1;

  const columns: DataTableColumn<Product>[] = [
    {
      key: "image",
      header: "",
      render: (p) => p.image_url
        ? <img src={p.image_url} alt="" className="h-8 w-8 rounded object-cover" />
        : <div className="h-8 w-8 rounded bg-[var(--surface-2)]" />,
    },
    { key: "name", header: t("products.name"), render: (p) => <span className="font-medium">{p.name}</span> },
    {
      key: "sku",
      header: t("products.sku"),
      render: (p) => <span className="font-mono text-xs text-[var(--muted)]">{p.sku ?? "—"}</span>,
    },
    {
      key: "category",
      header: t("products.category"),
      render: (p) => p.category?.name ?? <span className="text-[var(--muted)]">—</span>,
    },
    { key: "cost", header: t("products.cost"), align: "right", render: (p) => formatCurrency(p.purchase_price) },
    {
      key: "price",
      header: t("products.price"),
      align: "right",
      className: "font-medium",
      render: (p) => formatCurrency(p.selling_price),
    },
    {
      key: "stock",
      header: t("products.stock"),
      align: "right",
      render: (p) => (
        <span className={(p.stock ?? 0) <= 0 ? "text-[var(--color-danger)]" : ""}>{p.stock ?? 0}</span>
      ),
    },
    {
      key: "status",
      header: t("products.status"),
      render: (p) => (
        <Badge tone={p.is_active ? "success" : "default"}>
          {p.is_active ? t("common.active") : t("common.inactive")}
        </Badge>
      ),
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
      <ListToolbar
        search={search}
        onSearchChange={(v) => { setSearch(v); setPage(1); }}
        searchPlaceholder={t("products.searchPlaceholder")}
        exportLabel={t("common.export")}
        onExport={handleExport}
        addLabel={can("products.create") ? t("products.addProduct") : undefined}
        onAdd={can("products.create") ? openCreate : undefined}
        filters={
          <Button variant="outline" size="sm" asChild>
            <Link to="/products/labels">
              <Barcode className="h-4 w-4" />
              Print labels
            </Link>
          </Button>
        }
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
          emptyMessage={search ? t("products.emptySearch") : t("products.emptyNoProducts")}
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

        {data?.meta && (
          <Pagination
            page={page}
            lastPage={lastPage}
            onPageChange={setPage}
            label={t("products.paginationSummary", {
              current: data.meta.current_page,
              last: data.meta.last_page,
              total: data.meta.total,
            })}
          />
        )}
      </div>

      <Drawer
        open={drawerOpen}
        onClose={closeDrawer}
        title={editing ? t("products.editProductTitle", { name: editing.name }) : t("products.addProduct")}
      >
        <ProductForm
          key={editing?.id ?? "new"}
          isEdit={!!editing}
          productId={editing?.id}
          currentImageUrl={editing?.image_url}
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
                  tax_group_id: editing.tax_group?.id ?? editing.tax_group_id ?? null,
                  is_weighed: editing.is_weighed ?? false,
                  weight_unit: editing.weight_unit ?? undefined,
                  price_per_unit: editing.price_per_unit ?? undefined,
                }
              : undefined
          }
          onSuccess={closeDrawer}
        />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title={t("products.deleteTitle")}
        description={t("products.deleteConfirm", { name: deleting?.name ?? "" })}
        confirmLabel={t("common.delete")}
        confirmVariant="danger"
        onConfirm={() => deleting && deleteMutation.mutate(deleting.id)}
        loading={deleteMutation.isPending}
      />
    </div>
  );
}
