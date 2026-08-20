"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, ClipboardList, Play, CheckCircle, XCircle } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
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

interface Warehouse { id: string; name: string; is_default: boolean; }
interface Branch { id: string; name: string; is_default: boolean; }

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

function StocktakeForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { data: warehouses } = useQuery({ queryKey: ["warehouses-all"], queryFn: () => api<{ data: Warehouse[] }>("/warehouses") });
  const { data: branches } = useQuery({ queryKey: ["branches-all"], queryFn: () => api<{ data: Branch[] }>("/branches") });

  const { register, handleSubmit, formState: { errors } } = useForm<StocktakeValues>({
    resolver: zodResolver(stocktakeSchema),
  });

  const mutation = useMutation({
    mutationFn: (data: StocktakeValues) => api("/stocktakes", { method: "POST", body: data }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["stocktakes"] });
      toast("Stocktake created");
      onSuccess();
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to create stocktake", "error");
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
          {branches?.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Select>
        {errors.branch_id && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.branch_id.message}</p>}
      </div>

      <div>
        <Label>Warehouse *</Label>
        <Select {...register("warehouse_id")}>
          <option value="">— Select —</option>
          {warehouses?.data?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
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
      qc.invalidateQueries({ queryKey: ["stocktake", stocktake.id] });
      toast("Count recorded");
      setEditingItem(null);
      setCountValue("");
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to record count", "error");
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
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [detailDrawer, setDetailDrawer] = useState<{ id: string } | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ type: "start" | "complete" | "cancel"; id: string } | null>(null);

  const { data, isLoading } = useQuery<Paginated<Stocktake>>({
    queryKey: ["stocktakes", debouncedSearch, page],
    queryFn: () =>
      api<Paginated<Stocktake>>(
        `/stocktakes?search=${encodeURIComponent(debouncedSearch)}&page=${page}&per_page=20`,
      ),
  });

  const { data: detailData, isLoading: detailLoading } = useQuery<StocktakeDetail>({
    queryKey: ["stocktake", detailDrawer?.id],
    queryFn: () => api<StocktakeDetail>(`/stocktakes/${detailDrawer!.id}`),
    enabled: !!detailDrawer?.id,
  });

  const startMutation = useMutation({
    mutationFn: (id: string) => api(`/stocktakes/${id}/start`, { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["stocktakes"] });
      toast("Stocktake started");
      setConfirmAction(null);
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to start", "error");
      setConfirmAction(null);
    },
  });

  const completeMutation = useMutation({
    mutationFn: (id: string) => api(`/stocktakes/${id}/complete`, { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["stocktakes"] });
      toast("Stocktake completed");
      setConfirmAction(null);
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to complete", "error");
      setConfirmAction(null);
    },
  });

  const cancelMutation = useMutation({
    mutationFn: (id: string) => api(`/stocktakes/${id}/cancel`, { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["stocktakes"] });
      toast("Stocktake cancelled");
      setConfirmAction(null);
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Failed to cancel", "error");
      setConfirmAction(null);
    },
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div />
        {can("stocktakes.create") && (
          <Button size="sm" onClick={() => setDrawerOpen(true)}>
            <Plus className="h-4 w-4" />
            New Stocktake
          </Button>
        )}
      </div>

      <div className="relative">
        <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
        <Input
          className="ps-9"
          placeholder="Search stocktakes..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        />
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Warehouse</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Counted by</TableHead>
              <TableHead>Created</TableHead>
              <TableHead className="text-end">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 6 }).map((__, j) => (
                    <TableCell key={j}><Skeleton className="h-4 w-full" /></TableCell>
                  ))}
                </TableRow>
              ))
            ) : data?.data?.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="py-12 text-center text-[var(--muted)]">
                  <ClipboardList className="mx-auto mb-2 h-8 w-8 opacity-50" />
                  {search ? "No stocktakes match your search." : "No stocktakes yet. Create your first stocktake."}
                </TableCell>
              </TableRow>
            ) : (
              data?.data?.map((s) => (
                <TableRow key={s.id} className="cursor-pointer hover:bg-[var(--muted)]/5" onClick={() => setDetailDrawer({ id: s.id })}>
                  <TableCell className="font-medium">{s.name}</TableCell>
                  <TableCell>{s.warehouse.name}</TableCell>
                  <TableCell>
                    <Badge tone={statusColors[s.status] ?? "info"}>{s.status}</Badge>
                  </TableCell>
                  <TableCell>{s.counted_by?.name ?? "—"}</TableCell>
                  <TableCell>{formatDate(s.created_at)}</TableCell>
                  <TableCell className="text-end" onClick={(e) => e.stopPropagation()}>
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
                  </TableCell>
                </TableRow>
              ))
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

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="New Stocktake">
        <StocktakeForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>

      <Drawer
        open={!!detailDrawer}
        onClose={() => setDetailDrawer(null)}
        title={detailData?.name ?? "Stocktake"}
      >
        {detailLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-8 w-full" />)}
          </div>
        ) : detailData ? (
          <CountDrawer stocktake={detailData} />
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
