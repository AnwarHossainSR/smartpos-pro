import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search, AlertTriangle, Package } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { useTranslation } from "@/i18n/provider";
import { formatNumber } from "@/lib/utils";

interface ReorderSuggestion {
  product: {
    id: string;
    name: string;
    sku: string | null;
    purchase_price: number;
  };
  supplier: {
    id: string;
    name: string;
  } | null;
  current_stock: number;
  reorder_point: number;
  avg_daily_sales: number;
  lead_time_days: number;
  suggested_qty: number;
}

interface Warehouse {
  id: string;
  name: string;
}

interface DraftPoResponse {
  data: Array<{
    id: string;
    reference_number: string;
    status: string;
    supplier: { id: string; name: string } | null;
  }>;
}

export default function ReorderSuggestionsPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [selected, setSelected] = useState<Record<string, boolean>>({});
  const [warehouseId, setWarehouseId] = useState("");
  const [editingQty, setEditingQty] = useState<Record<string, number>>({});

  const { data, isLoading } = useQuery<{ data: ReorderSuggestion[] }>({
    queryKey: ["reorder-suggestions"],
    queryFn: () => api("/reorder-suggestions"),
  });

  const { data: warehouses } = useQuery<{ data: Warehouse[] }>({
    queryKey: ["warehouses-all"],
    queryFn: () => api("/warehouses"),
  });

  const createMutation = useMutation({
    mutationFn: (items: Array<{ product_id: string; quantity: number; unit_cost: number; supplier_id: string }>) =>
      api<DraftPoResponse>("/reorder-suggestions/create-draft-po", {
        method: "POST",
        body: { items, warehouse_id: warehouseId },
      }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["reorder-suggestions"] });
      const count = res.data?.length ?? 0;
      toast(t("reorder.created") + (count > 1 ? ` (${count})` : ""));
      setSelected({});
      setEditingQty({});
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to create draft PO", "error");
    },
  });

  const suggestions = data?.data ?? [];

  const filtered = suggestions.filter((s) =>
    s.product.name.toLowerCase().includes(search.toLowerCase()) ||
    s.product.sku?.toLowerCase().includes(search.toLowerCase()) ||
    s.supplier?.name.toLowerCase().includes(search.toLowerCase())
  );

  const selectedItems = suggestions.filter((s) => selected[s.product.id]);

  function toggleAll() {
    const allSelected = filtered.length > 0 && filtered.every((s) => selected[s.product.id]);
    if (allSelected) {
      const next: Record<string, boolean> = {};
      for (const key of Object.keys(selected)) {
        if (!filtered.find((s) => s.product.id === key)) {
          next[key] = true;
        }
      }
      setSelected(next);
    } else {
      const next = { ...selected };
      for (const s of filtered) {
        next[s.product.id] = true;
      }
      setSelected(next);
    }
  }

  function toggleOne(id: string) {
    setSelected((prev) => ({ ...prev, [id]: !prev[id] }));
  }

  function handleCreateDraftPo() {
    if (!warehouseId) {
      toast("Select a warehouse first", "error");
      return;
    }

    if (selectedItems.length === 0) {
      toast("Select at least one item", "error");
      return;
    }

    const items = selectedItems.map((s) => ({
      product_id: s.product.id,
      quantity: editingQty[s.product.id] ?? s.suggested_qty,
      unit_cost: s.product.purchase_price,
      supplier_id: s.supplier?.id ?? "",
    })).filter((item) => item.supplier_id);

    if (items.length === 0) {
      toast("Selected items must have a supplier", "error");
      return;
    }

    createMutation.mutate(items);
  }

  const selectedCount = Object.values(selected).filter(Boolean).length;

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2">
          <AlertTriangle className="h-5 w-5 text-[var(--color-warning)]" />
          <h2 className="text-lg font-semibold">{t("reorder.title")}</h2>
        </div>
        {can("reorder-suggestions.create") && selectedCount > 0 && (
          <div className="flex items-center gap-2">
            <Select
              value={warehouseId}
              onChange={(e) => setWarehouseId(e.target.value)}
              className="w-48"
            >
              <option value="">{t("reorder.warehouse") ?? "Warehouse"}</option>
              {warehouses?.data?.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </Select>
            <Button size="sm" onClick={handleCreateDraftPo} disabled={createMutation.isPending}>
              {createMutation.isPending ? "..." : t("reorder.create_draft") + ` (${selectedCount})`}
            </Button>
          </div>
        )}
      </div>

      <div className="relative">
        <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
        <Input
          className="ps-9"
          placeholder={t("reorder.title") + "..."}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              {can("reorder-suggestions.create") && (
                <TableHead className="w-10">
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-[var(--border)] accent-[var(--primary)]"
                    checked={filtered.length > 0 && filtered.every((s) => selected[s.product.id])}
                    onChange={toggleAll}
                  />
                </TableHead>
              )}
              <TableHead>{t("reorder.product")}</TableHead>
              <TableHead className="text-end">{t("reorder.current_stock")}</TableHead>
              <TableHead className="text-end">{t("reorder.reorder_point")}</TableHead>
              <TableHead className="text-end">{t("reorder.avg_daily_sales")}</TableHead>
              <TableHead className="text-end">{t("reorder.lead_time")}</TableHead>
              <TableHead className="text-end">{t("reorder.suggested_qty")}</TableHead>
              <TableHead>{t("reorder.supplier")}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 8 }).map((__, j) => (
                    <TableCell key={j}><Skeleton className="h-4 w-full" /></TableCell>
                  ))}
                </TableRow>
              ))
            ) : filtered.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="py-12 text-center text-[var(--muted)]">
                  <Package className="mx-auto mb-2 h-8 w-8 opacity-50" />
                  {t("reorder.no_suggestions")}
                </TableCell>
              </TableRow>
            ) : (
              filtered.map((s) => {
                const qty = editingQty[s.product.id] ?? s.suggested_qty;
                return (
                  <TableRow key={s.product.id}>
                    {can("reorder-suggestions.create") && (
                      <TableCell>
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border-[var(--border)] accent-[var(--primary)]"
                          checked={!!selected[s.product.id]}
                          onChange={() => toggleOne(s.product.id)}
                        />
                      </TableCell>
                    )}
                    <TableCell className="font-medium">
                      {s.product.name}
                      {s.product.sku && (
                        <span className="ms-2 text-xs text-[var(--muted)]">{s.product.sku}</span>
                      )}
                    </TableCell>
                    <TableCell className="text-end">{formatNumber(s.current_stock)}</TableCell>
                    <TableCell className="text-end">{formatNumber(s.reorder_point)}</TableCell>
                    <TableCell className="text-end">{formatNumber(s.avg_daily_sales)}</TableCell>
                    <TableCell className="text-end">{s.lead_time_days}</TableCell>
                    <TableCell className="text-end">
                      {selected[s.product.id] ? (
                        <Input
                          type="number"
                          min="1"
                          value={qty}
                          onChange={(e) => setEditingQty((prev) => ({
                            ...prev,
                            [s.product.id]: parseInt(e.target.value, 10) || 1,
                          }))}
                          className="h-8 w-20 text-end"
                        />
                      ) : (
                        formatNumber(s.suggested_qty)
                      )}
                    </TableCell>
                    <TableCell>{s.supplier?.name ?? "—"}</TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
