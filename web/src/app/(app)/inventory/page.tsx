import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Combobox } from "@/components/ui/combobox";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "@/components/ui/toast";
import { api, type Paginated } from "@/lib/api";
import { useWarehouses } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatCurrency } from "@/lib/utils";
import { useAuth } from "@/stores/auth";

interface InventoryBalance {
  id: string;
  product: { id: string; name: string; sku: string | null; min_stock_level: number };
  warehouse: { id: string; name: string };
  quantity: number;
  available_quantity: number;
  average_cost: number;
}

/** Only what the product picker needs — the full product shape lives on the products screen. */
interface ProductOption {
  id: string;
  name: string;
  sku: string | null;
}

const adjustSchema = z.object({
  product_id: z.string().min(1),
  warehouse_id: z.string().min(1),
  direction: z.enum(["in", "out"]),
  quantity: z.number().min(0.001, "Must be > 0"),
  unit_cost: z.number().min(0).optional(),
  note: z.string().optional(),
});
type AdjustValues = z.infer<typeof adjustSchema>;

function AdjustForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { warehouses } = useWarehouses();
  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<AdjustValues>({
    resolver: zodResolver(adjustSchema),
    defaultValues: { direction: "in", quantity: 0 },
  });

  const selectedProductId = watch("product_id");

  const mutation = useMutation({
    mutationFn: (data: AdjustValues) => api("/inventory/adjust", { method: "POST", body: data }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.inventory.all });
      toast("Stock adjusted");
      onSuccess();
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Product *</Label>
        <Combobox<ProductOption>
          endpoint="/products"
          queryKey={queryKeys.products.options()}
          value={selectedProductId}
          onChange={(id) => setValue("product_id", id ?? "", { shouldValidate: true })}
          label={(p) => p.name}
          hint={(p) => p.sku}
          placeholder="Search product…"
          emptyLabel="No products match"
        />
        {errors.product_id && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">Select a product</p>
        )}
      </div>

      <div>
        <Label>Warehouse *</Label>
        <Select {...register("warehouse_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
          <option value="">— Select —</option>
          {warehouses.map((w) => (
            <option key={w.id} value={w.id}>
              {w.name}
            </option>
          ))}
        </Select>
        {errors.warehouse_id && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">Select a warehouse</p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Direction *</Label>
          <Select {...register("direction")}>
            <option value="in">In (add stock)</option>
            <option value="out">Out (remove stock)</option>
          </Select>
        </div>
        <div>
          <Label>Quantity *</Label>
          <Input
            type="number"
            step="0.001"
            min="0.001"
            {...register("quantity", { valueAsNumber: true })}
          />
          {errors.quantity && (
            <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.quantity.message}</p>
          )}
        </div>
      </div>

      <div>
        <Label>Unit cost</Label>
        <Input
          type="number"
          step="0.01"
          min="0"
          {...register("unit_cost", { valueAsNumber: true })}
          placeholder="0.00"
        />
      </div>

      <div>
        <Label>Note</Label>
        <Textarea {...register("note")} placeholder="Reason for adjustment…" />
      </div>

      <Button type="submit" disabled={mutation.isPending || !selectedProductId} className="w-full">
        {mutation.isPending ? "Saving…" : "Save adjustment"}
      </Button>
    </form>
  );
}

const columns: DataTableColumn<InventoryBalance>[] = [
  {
    key: "product",
    header: "Product",
    render: (b) => <span className="font-medium">{b.product.name}</span>,
  },
  {
    key: "sku",
    header: "SKU",
    render: (b) => (
      <span className="font-mono text-xs text-[var(--muted)]">{b.product.sku ?? "—"}</span>
    ),
  },
  { key: "warehouse", header: "Warehouse", render: (b) => b.warehouse.name },
  { key: "quantity", header: "Qty", align: "right", render: (b) => b.quantity },
  { key: "available", header: "Available", align: "right", render: (b) => b.available_quantity },
  {
    key: "cost",
    header: "Avg cost",
    align: "right",
    render: (b) => formatCurrency(b.average_cost),
  },
  {
    key: "value",
    header: "Value",
    align: "right",
    render: (b) => formatCurrency(b.quantity * b.average_cost),
  },
  {
    key: "status",
    header: "Status",
    render: (b) => {
      const isLow = b.quantity <= b.product.min_stock_level;
      return <Badge tone={isLow ? "danger" : "success"}>{isLow ? "Low" : "OK"}</Badge>;
    },
  },
];

export default function InventoryPage() {
  const { can } = useAuth();
  const [drawerOpen, setDrawerOpen] = useState(false);

  const list = useResourceList<InventoryBalance, { low_stock: string }>({
    queryKey: queryKeys.inventory.lists(),
    queryFn: (params) =>
      api<Paginated<InventoryBalance>>(`/inventory/balances?${buildListQuery(params)}`),
    perPage: 25,
    filterKeys: ["low_stock"] as const,
  });

  const lowStock = list.filters.low_stock === "1";

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search products…"
        addLabel={can("inventory.update") ? "Adjust stock" : undefined}
        onAdd={can("inventory.update") ? () => setDrawerOpen(true) : undefined}
        onRefresh={list.refetch}
        filters={
          <Button
            variant={lowStock ? "primary" : "outline"}
            size="sm"
            onClick={() => list.setFilter("low_stock", lowStock ? undefined : "1")}
          >
            <AlertTriangle className="h-4 w-4" />
            Low stock
          </Button>
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(b) => b.id}
          loading={list.isLoading}
          error={list.isError ? list.error : undefined}
          onRetry={() => list.refetch()}
          emptyMessage={
            list.search
              ? "No items match your search."
              : lowStock
                ? "No low stock items."
                : "No inventory data yet."
          }
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

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="Adjust stock">
        <AdjustForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
