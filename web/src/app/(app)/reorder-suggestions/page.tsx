import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Input } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api } from "@/lib/api";
import { useWarehouses } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { formatNumber } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, Package } from "lucide-react";
import { useMemo, useState } from "react";

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
  const [selected, setSelected] = useState<Set<string | number>>(new Set());
  const [warehouseId, setWarehouseId] = useState("");
  const [editingQty, setEditingQty] = useState<Record<string, number>>({});

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: queryKeys.reorderSuggestions.lists(),
    queryFn: () => api<{ data: ReorderSuggestion[] }>("/reorder-suggestions"),
  });

  const { warehouses } = useWarehouses({ enabled: can("reorder-suggestions.create") });

  const createMutation = useMutation({
    mutationFn: (items: Array<{ product_id: string; quantity: number; unit_cost: number; supplier_id: string }>) =>
      api<DraftPoResponse>("/reorder-suggestions/create-draft-po", {
        method: "POST",
        body: { items, warehouse_id: warehouseId },
      }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: queryKeys.reorderSuggestions.all });
      const count = res.data?.length ?? 0;
      toast(t("reorder.created") + (count > 1 ? ` (${count})` : ""));
      setSelected(new Set());
      setEditingQty({});
    },
  });

  const suggestions = useMemo(() => data?.data ?? [], [data]);

  // The endpoint returns the whole (short) suggestion set in one go, so search filters it in
  // memory — there is no page to fetch, which is why this screen keeps its own search state.
  const filtered = useMemo(() => {
    const term = search.toLowerCase();
    if (!term) return suggestions;
    return suggestions.filter((s) =>
      s.product.name.toLowerCase().includes(term) ||
      s.product.sku?.toLowerCase().includes(term) ||
      s.supplier?.name.toLowerCase().includes(term)
    );
  }, [suggestions, search]);

  const selectedItems = suggestions.filter((s) => selected.has(s.product.id));
  const canCreate = can("reorder-suggestions.create");

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

  const columns: DataTableColumn<ReorderSuggestion>[] = [
    {
      key: "product",
      header: t("reorder.product"),
      render: (s) => (
        <span className="font-medium">
          {s.product.name}
          {s.product.sku && <span className="ms-2 text-xs text-[var(--muted)]">{s.product.sku}</span>}
        </span>
      ),
    },
    {
      key: "current_stock",
      header: t("reorder.current_stock"),
      align: "right",
      render: (s) => formatNumber(s.current_stock),
    },
    {
      key: "reorder_point",
      header: t("reorder.reorder_point"),
      align: "right",
      render: (s) => formatNumber(s.reorder_point),
    },
    {
      key: "avg_daily_sales",
      header: t("reorder.avg_daily_sales"),
      align: "right",
      render: (s) => formatNumber(s.avg_daily_sales),
    },
    {
      key: "lead_time",
      header: t("reorder.lead_time"),
      align: "right",
      render: (s) => s.lead_time_days,
    },
    {
      key: "suggested_qty",
      header: t("reorder.suggested_qty"),
      align: "right",
      // Selecting a row is what makes its quantity editable — an unselected row is not
      // going onto a purchase order, so its number is read-only.
      render: (s) =>
        selected.has(s.product.id) ? (
          <Input
            type="number"
            min="1"
            value={editingQty[s.product.id] ?? s.suggested_qty}
            onChange={(e) =>
              setEditingQty((prev) => ({
                ...prev,
                [s.product.id]: parseInt(e.target.value, 10) || 1,
              }))
            }
            className="ms-auto h-8 w-20 text-end"
          />
        ) : (
          formatNumber(s.suggested_qty)
        ),
    },
    { key: "supplier", header: t("reorder.supplier"), render: (s) => s.supplier?.name ?? "—" },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <AlertTriangle className="h-5 w-5 text-[var(--color-warning)]" />
        <h2 className="text-lg font-semibold">{t("reorder.title")}</h2>
      </div>

      <ListToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder={t("reorder.title") + "..."}
        onRefresh={refetch}
        filters={
          canCreate && selected.size > 0 ? (
            <div className="flex items-center gap-2">
              <Select value={warehouseId} onValueChange={setWarehouseId}>
                <SelectTrigger className="w-48"><SelectValue placeholder={t("reorder.warehouse") ?? "Warehouse"} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="">{t("reorder.warehouse") ?? "Warehouse"}</SelectItem>
                  {warehouses.map((w) => (
                    <SelectItem key={w.id} value={w.id}>{w.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button size="sm" onClick={handleCreateDraftPo} disabled={createMutation.isPending}>
                {createMutation.isPending ? "..." : t("reorder.create_draft") + ` (${selected.size})`}
              </Button>
            </div>
          ) : undefined
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(s) => s.product.id}
          loading={isLoading}
          loadingRows={5}
          error={isError ? error : undefined}
          onRetry={() => refetch()}
          selectable={canCreate}
          selectedKeys={selected}
          onSelectionChange={setSelected}
          emptyMessage={
            <>
              <Package className="mx-auto mb-2 h-8 w-8 opacity-50" />
              {t("reorder.no_suggestions")}
            </>
          }
        />
      </div>
    </div>
  );
}
