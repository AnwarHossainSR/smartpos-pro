"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search, AlertTriangle } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { Combobox } from "@/components/ui/combobox";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface InventoryBalance {
  id: string;
  product: { id: string; name: string; sku: string | null; min_stock_level: number };
  warehouse: { id: string; name: string };
  quantity: number;
  available_quantity: number;
  average_cost: number;
}

interface Warehouse { id: string; name: string; is_default: boolean; }
interface Product { id: string; name: string; sku: string | null; }

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
  const { data: warehouses } = useQuery({
    queryKey: ["warehouses-all"],
    queryFn: () => api<{ data: Warehouse[] }>("/warehouses"),
  });
  const { register, handleSubmit, formState: { errors }, setValue, watch } = useForm<AdjustValues>({
    resolver: zodResolver(adjustSchema),
    defaultValues: { direction: "in", quantity: 0 },
  });

  const selectedProductId = watch("product_id");

  const mutation = useMutation({
    mutationFn: (data: AdjustValues) => api("/inventory/adjust", { method: "POST", body: data }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["inventory"] });
      toast("Stock adjusted");
      onSuccess();
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Adjustment failed", "error");
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Product *</Label>
        <Combobox<Product>
          endpoint="/products"
          queryKey="products"
          value={selectedProductId}
          onChange={(id) => setValue("product_id", id ?? "", { shouldValidate: true })}
          label={(p) => p.name}
          hint={(p) => p.sku}
          placeholder="Search product…"
          emptyLabel="No products match"
        />
        {errors.product_id && <p className="mt-1 text-xs text-[var(--color-danger)]">Select a product</p>}
      </div>

      <div>
        <Label>Warehouse *</Label>
        <Select {...register("warehouse_id", { setValueAs: (v) => (v ? String(v) : undefined) })}>
          <option value="">— Select —</option>
          {warehouses?.data?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
        </Select>
        {errors.warehouse_id && <p className="mt-1 text-xs text-[var(--color-danger)]">Select a warehouse</p>}
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
          <Input type="number" step="0.001" min="0.001" {...register("quantity", { valueAsNumber: true })} />
          {errors.quantity && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.quantity.message}</p>}
        </div>
      </div>

      <div>
        <Label>Unit cost</Label>
        <Input type="number" step="0.01" min="0" {...register("unit_cost", { valueAsNumber: true })} placeholder="0.00" />
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

export default function InventoryPage() {
  const { can } = useAuth();
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [lowStock, setLowStock] = useState(false);
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const { data, isLoading } = useQuery<Paginated<InventoryBalance>>({
    queryKey: ["inventory", debouncedSearch, lowStock, page],
    queryFn: () =>
      api<Paginated<InventoryBalance>>(
        `/inventory/balances?search=${encodeURIComponent(debouncedSearch)}&page=${page}&per_page=25${lowStock ? "&low_stock=1" : ""}`,
      ),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex gap-2">
          <Button
            variant={lowStock ? "primary" : "outline"}
            size="sm"
            onClick={() => setLowStock((v) => !v)}
          >
            <AlertTriangle className="h-4 w-4" />
            Low stock
          </Button>
          {can("inventory.update") && (
            <Button size="sm" onClick={() => setDrawerOpen(true)}>
              Adjust stock
            </Button>
          )}
        </div>
      </div>

      <div className="relative">
        <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
        <Input
          className="ps-9"
          placeholder="Search products…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        />
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Warehouse</TableHead>
              <TableHead className="text-end">Qty</TableHead>
              <TableHead className="text-end">Available</TableHead>
              <TableHead className="text-end">Avg cost</TableHead>
              <TableHead className="text-end">Value</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              Array.from({ length: 8 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 8 }).map((__, j) => (
                    <TableCell key={j}><Skeleton className="h-4 w-full" /></TableCell>
                  ))}
                </TableRow>
              ))
            ) : data?.data?.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="py-12 text-center text-[var(--muted)]">
                  {search ? "No items match your search." : lowStock ? "No low stock items." : "No inventory data yet."}
                </TableCell>
              </TableRow>
            ) : (
              data?.data?.map((b) => {
                const isLow = b.quantity <= b.product.min_stock_level;
                return (
                  <TableRow key={b.id}>
                    <TableCell className="font-medium">{b.product.name}</TableCell>
                    <TableCell className="font-mono text-xs text-[var(--muted)]">{b.product.sku ?? "—"}</TableCell>
                    <TableCell>{b.warehouse.name}</TableCell>
                    <TableCell className="text-end">{b.quantity}</TableCell>
                    <TableCell className="text-end">{b.available_quantity}</TableCell>
                    <TableCell className="text-end">{formatCurrency(b.average_cost)}</TableCell>
                    <TableCell className="text-end">{formatCurrency(b.quantity * b.average_cost)}</TableCell>
                    <TableCell>
                      <Badge tone={isLow ? "danger" : "success"}>
                        {isLow ? "Low" : "OK"}
                      </Badge>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
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

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="Adjust stock">
        <AdjustForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
