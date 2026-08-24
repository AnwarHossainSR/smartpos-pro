import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { ClipboardList, Play, CheckCircle, XCircle } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, errorMessage, type Envelope, type Paginated } from "@/lib/api";
import { useBranches, useWarehouses } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { formatDate } from "@/lib/utils";

interface Stocktake {
  id: string;
  name: string;
  status: string;
  notes: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  warehouse: { id: string; name: string };
  branch: { id: string; name: string };
  counted_by: { id: string; name: string } | null;
}

interface StocktakeDetail extends Stocktake {
  items: StocktakeItem[];
}

interface StocktakeItem {
  id: string;
  system_qty: number;
  counted_qty: number | null;
  variance: number;
  notes: string | null;
  product: { id: string; name: string; sku: string | null };
  variant: { id: string; name: string } | null;
}

const stocktakeSchema = z.object({
  branch_id: z.string().min(1, "Select a branch"),
  warehouse_id: z.string().min(1, "Select a warehouse"),
  name: z.string().min(1, "Name is required"),
  notes: z.string().optional(),
});
type StocktakeValues = z.infer<typeof stocktakeSchema>;

const statusColors: Record<string, "info" | "warning" | "success" | "danger"> = {
  draft: "info",
  counting: "warning",
  completed: "success",
  cancelled: "danger",
};

const STATUSES = ["draft", "counting", "completed", "cancelled"] as const;

function StocktakeForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { warehouses } = useWarehouses();
  const { branches } = useBranches();

  const { register, handleSubmit, formState: { errors } } = useForm<StocktakeValues>({
    resolver: zodResolver(stocktakeSchema),
  });

  const mutation = useMutation({
    mutationFn: (data: StocktakeValues) => api("/stocktakes", { method: "POST", body: data }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.stocktakes.all });
      toast("Stocktake created");
      onSuccess();
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Name *</Label>
        <Input {...register("name")} placeholder="e.g. Monthly Count" />
        {errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}
      </div>

      <div>
        <Label>Branch *</Label>
        <Select {...register("branch_id")}>
          <option value="">— Select —</option>
          {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Select>
        {errors.branch_id && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.branch_id.message}</p>}
      </div>

      <div>
        <Label>Warehouse *</Label>
        <Select {...register("warehouse_id")}>
          <option value="">— Select —</option>
          {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
        </Select>
        {errors.warehouse_id && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.warehouse_id.message}</p>}
      </div>

      <div>
        <Label>Notes</Label>
        <Textarea {...register("notes")} placeholder="Optional notes..." />
      </div>

      <Button type="submit" disabled={mutation.isPending} className="w-full">
        {mutation.isPending ? "Creating..." : "Create Stocktake"}
      </Button>
    </form>
  );
}

function CountDrawer({ stocktake }: { stocktake: StocktakeDetail }) {
  const qc = useQueryClient();
  const [editingItem, setEditingItem] = useState<string | null>(null);
  const [countValue, setCountValue] = useState("");

  const countMutation = useMutation({
    mutationFn: ({ itemId, qty }: { itemId: string; qty: number }) =>
      api(`/stocktakes/${stocktake.id}/items/${itemId}/count`, { method: "POST", body: { counted_qty: qty } }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.stocktakes.detail(stocktake.id) });
      toast("Count recorded");
      setEditingItem(null);
      setCountValue("");
    },
  });

  return (
    <div className="space-y-4">
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead className="text-end">System</TableHead>
              <TableHead className="text-end">Counted</TableHead>
              <TableHead className="text-end">Variance</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {stocktake.items.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="font-medium">{item.product.name}</TableCell>
                <TableCell className="font-mono text-xs text-[var(--muted)]">{item.product.sku ?? "—"}</TableCell>
                <TableCell className="text-end">{item.system_qty}</TableCell>
                <TableCell className="text-end">{item.counted_qty ?? "—"}</TableCell>
                <TableCell className={`text-end ${item.variance !== 0 ? "font-medium text-[var(--color-danger)]" : ""}`}>
                  {item.counted_qty !== null ? item.variance : "—"}
                </TableCell>
                <TableCell className="text-end">
                  {editingItem === item.id ? (
                    <div className="flex items-center gap-1">
                      <Input
                        type="number"
                        step="0.001"
                        min="0"
                        value={countValue}
                        onChange={(e) => setCountValue(e.target.value)}
                        className="w-24"
                        autoFocus
                        onKeyDown={(e) => {
                          if (e.key === "Enter") {
                            countMutation.mutate({ itemId: item.id, qty: parseFloat(countValue) });
                          }
                          if (e.key === "Escape") {
                            setEditingItem(null);
                            setCountValue("");
                          }
                        }}
                      />
                      <Button
                        size="sm"
                        onClick={() => countMutation.mutate({ itemId: item.id, qty: parseFloat(countValue) })}
                        disabled={countMutation.isPending}
                      >
                        Save
                      </Button>
                    </div>
                  ) : (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => {
                        setEditingItem(item.id);
                        setCountValue(item.counted_qty?.toString() ?? "");
                      }}
                    >
                      Count
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
            {stocktake.items.length === 0 && (
              <TableRow>
                <TableCell colSpan={6} className="py-12 text-center text-[var(--muted)]">
                  No items to count. Start the stocktake first.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

export default function StocktakesPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [detailDrawer, setDetailDrawer] = useState<{ id: string } | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ type: "start" | "complete" | "cancel"; id: string } | null>(null);

  const list = useResourceList<Stocktake, { status: string }>({
    queryKey: queryKeys.stocktakes.lists(),
    queryFn: (params) => api<Paginated<Stocktake>>(`/stocktakes?${buildListQuery(params)}`),
    filterKeys: ["status"] as const,
  });

  const { data: detailData, isLoading: detailLoading } = useQuery({
    queryKey: queryKeys.stocktakes.detail(detailDrawer?.id),
    // `/stocktakes/{id}` answers with the standard `{ data }` envelope, unlike the paginated
    // index — unwrapping it here is what gives `CountDrawer` an actual `items` array.
    queryFn: () => api<Envelope<StocktakeDetail>>(`/stocktakes/${detailDrawer!.id}`),
    enabled: !!detailDrawer?.id,
  });
  const detail = detailData?.data;

  /** The three lifecycle actions differ only in endpoint and copy. */
  function lifecycleMutation(action: "start" | "complete" | "cancel", done: string, failed: string) {
    return {
      mutationFn: (id: string) => api(`/stocktakes/${id}/${action}`, { method: "POST" }),
      onSuccess: () => {
        qc.invalidateQueries({ queryKey: queryKeys.stocktakes.all });
        toast(done);
        setConfirmAction(null);
      },
      // Kept (rather than left to the global handler) because the confirm dialog has to
      // close on failure too, otherwise it sits there over an unchanged row.
      onError: (e: unknown) => {
        toast(errorMessage(e, failed), "error");
        setConfirmAction(null);
      },
    };
  }

  const startMutation = useMutation(lifecycleMutation("start", "Stocktake started", "Failed to start"));
  const completeMutation = useMutation(lifecycleMutation("complete", "Stocktake completed", "Failed to complete"));
  const cancelMutation = useMutation(lifecycleMutation("cancel", "Stocktake cancelled", "Failed to cancel"));

  const columns: DataTableColumn<Stocktake>[] = [
    {
      key: "name",
      header: "Name",
      render: (s) => (
        // DataTable has no row-click hook, so the name is the affordance that opens the sheet.
        <button
          type="button"
          className="cursor-pointer text-start font-medium hover:underline"
          onClick={() => setDetailDrawer({ id: s.id })}
        >
          {s.name}
        </button>
      ),
    },
    { key: "warehouse", header: "Warehouse", render: (s) => s.warehouse.name },
    {
      key: "status",
      header: "Status",
      render: (s) => <Badge tone={statusColors[s.status] ?? "info"}>{s.status}</Badge>,
    },
    { key: "counted_by", header: "Counted by", render: (s) => s.counted_by?.name ?? "—" },
    { key: "created", header: "Created", render: (s) => formatDate(s.created_at) },
    {
      key: "actions",
      header: "Actions",
      align: "right",
      render: (s) => (
        <div className="flex items-center justify-end gap-1">
          {can("stocktakes.update") && s.status === "draft" && (
            <Button size="sm" variant="outline" onClick={() => setConfirmAction({ type: "start", id: s.id })}>
              <Play className="h-3 w-3" />
            </Button>
          )}
          {can("stocktakes.update") && s.status === "counting" && (
            <>
              <Button size="sm" variant="outline" onClick={() => setConfirmAction({ type: "complete", id: s.id })}>
                <CheckCircle className="h-3 w-3" />
              </Button>
              <Button size="sm" variant="outline" onClick={() => setConfirmAction({ type: "cancel", id: s.id })}>
                <XCircle className="h-3 w-3" />
              </Button>
            </>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search stocktakes..."
        addLabel={can("stocktakes.create") ? "New Stocktake" : undefined}
        onAdd={can("stocktakes.create") ? () => setDrawerOpen(true) : undefined}
        onRefresh={list.refetch}
        filters={
          <Select
            className="sm:w-44"
            value={list.filters.status ?? ""}
            onChange={(e) => list.setFilter("status", e.target.value || undefined)}
          >
            <option value="">All statuses</option>
            {STATUSES.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </Select>
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(s) => s.id}
          loading={list.isLoading}
          loadingRows={5}
          error={list.isError ? list.error : undefined}
          onRetry={() => list.refetch()}
          emptyMessage={
            <>
              <ClipboardList className="mx-auto mb-2 h-8 w-8 opacity-50" />
              {list.isFiltered
                ? "No stocktakes match your search."
                : "No stocktakes yet. Create your first stocktake."}
            </>
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

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="New Stocktake">
        <StocktakeForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>

      <Drawer
        open={!!detailDrawer}
        onClose={() => setDetailDrawer(null)}
        title={detail?.name ?? "Stocktake"}
      >
        {detailLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-8 w-full" />)}
          </div>
        ) : detail ? (
          <CountDrawer stocktake={detail} />
        ) : null}
      </Drawer>

      <Dialog open={!!confirmAction} onClose={() => setConfirmAction(null)} title="Confirm Action">
        <div className="space-y-4">
          <p>
            {confirmAction?.type === "start" && "Start counting? This will snapshot current stock levels."}
            {confirmAction?.type === "complete" && "Complete this stocktake? Adjustments will be posted to inventory."}
            {confirmAction?.type === "cancel" && "Cancel this stocktake?"}
          </p>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setConfirmAction(null)}>Cancel</Button>
            <Button
              onClick={() => {
                if (!confirmAction) return;
                if (confirmAction.type === "start") startMutation.mutate(confirmAction.id);
                if (confirmAction.type === "complete") completeMutation.mutate(confirmAction.id);
                if (confirmAction.type === "cancel") cancelMutation.mutate(confirmAction.id);
              }}
              disabled={startMutation.isPending || completeMutation.isPending || cancelMutation.isPending}
            >
              {confirmAction?.type === "start" && (startMutation.isPending ? "Starting..." : "Start")}
              {confirmAction?.type === "complete" && (completeMutation.isPending ? "Completing..." : "Complete")}
              {confirmAction?.type === "cancel" && (cancelMutation.isPending ? "Cancelling..." : "Cancel")}
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  );
}
