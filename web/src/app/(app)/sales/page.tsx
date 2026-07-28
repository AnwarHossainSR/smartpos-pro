"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search, Eye, RotateCcw, ChevronLeft, ChevronRight } from "lucide-react";
import { api, ApiError, type Paginated } from "@/lib/api";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface SaleItem {
  id: number;
  name: string;
  quantity: number;
  unit_price: number;
  discount_amount: number;
  line_total: number;
}

interface Sale {
  id: number;
  invoice_number: string;
  status: string;
  payment_status: string;
  total: number;
  paid_amount: number;
  due_amount: number;
  sold_at: string;
  customer: { id: number; name: string } | null;
  cashier: { id: number; name: string } | null;
  items?: SaleItem[];
  payments?: { method: string; amount: number }[];
}

function SaleDetailModal({ sale, onClose, onReturn }: { sale: Sale; onClose: () => void; onReturn: () => void }) {
  const { can } = useAuth();
  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    completed: "success", held: "warning", draft: "default", quotation: "info" as "default",
  };
  const payColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    paid: "success", partial: "warning", unpaid: "danger",
  };

  return (
    <Dialog open title={`Invoice #${sale.invoice_number}`} onClose={onClose}>
      <div className="space-y-4 text-sm">
        <div className="grid grid-cols-2 gap-2">
          <div><span className="text-[var(--muted)]">Status</span><br /><Badge tone={statusColor[sale.status] ?? "default"}>{sale.status}</Badge></div>
          <div><span className="text-[var(--muted)]">Payment</span><br /><Badge tone={payColor[sale.payment_status] ?? "default"}>{sale.payment_status}</Badge></div>
          <div><span className="text-[var(--muted)]">Customer</span><br />{sale.customer?.name ?? "—"}</div>
          <div><span className="text-[var(--muted)]">Date</span><br />{new Date(sale.sold_at).toLocaleString()}</div>
        </div>
        <hr />
        <div className="space-y-1">
          {sale.items?.map((item) => (
            <div key={item.id} className="flex justify-between">
              <span className="flex-1 truncate">{item.name} × {item.quantity}</span>
              <span className="ml-4">{formatCurrency(item.line_total)}</span>
            </div>
          ))}
        </div>
        <hr />
        <div className="space-y-1">
          {sale.payments?.map((p, i) => (
            <div key={i} className="flex justify-between text-[var(--muted)]">
              <span className="capitalize">{p.method}</span><span>{formatCurrency(p.amount)}</span>
            </div>
          ))}
          <div className="flex justify-between font-semibold">
            <span>Total</span><span>{formatCurrency(sale.total)}</span>
          </div>
          {sale.due_amount > 0 && (
            <div className="flex justify-between text-amber-600">
              <span>Due</span><span>{formatCurrency(sale.due_amount)}</span>
            </div>
          )}
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onClose} className="flex-1">Close</Button>
          {can("sales.refund") && sale.status === "completed" && (
            <Button variant="danger" onClick={onReturn} className="flex-1">
              <RotateCcw className="h-4 w-4" /> Return
            </Button>
          )}
        </div>
      </div>
    </Dialog>
  );
}

function ReturnModal({ sale, onClose }: { sale: Sale; onClose: () => void }) {
  const qc = useQueryClient();
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [reason, setReason] = useState("");

  const mutation = useMutation({
    mutationFn: () => {
      const items = (sale.items ?? [])
        .filter((i) => (quantities[i.id] ?? 0) > 0)
        .map((i) => ({ sale_item_id: i.id, quantity: quantities[i.id] }));
      return api(`/sales/${sale.id}/returns`, {
        method: "POST",
        idempotencyKey: crypto.randomUUID(),
        body: { items, reason },
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["sales"] });
      toast("Return processed");
      onClose();
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Return failed", "error");
    },
  });

  const hasItems = Object.values(quantities).some((q) => q > 0);

  return (
    <Dialog open title={`Return – #${sale.invoice_number}`} onClose={onClose}>
      <div className="space-y-4 text-sm">
        <p className="text-[var(--muted)]">Enter quantities to return. Leave blank to skip item.</p>
        <div className="space-y-2">
          {sale.items?.map((item) => (
            <div key={item.id} className="flex items-center justify-between gap-3">
              <span className="flex-1">{item.name} (sold: {item.quantity})</span>
              <Input
                type="number"
                className="w-24"
                min="0"
                max={item.quantity}
                value={quantities[item.id] ?? ""}
                onChange={(e) => setQuantities((p) => ({ ...p, [item.id]: Number(e.target.value) }))}
              />
            </div>
          ))}
        </div>
        <div>
          <Label>Reason</Label>
          <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Return reason…" />
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onClose} disabled={mutation.isPending} className="flex-1">Cancel</Button>
          <Button variant="danger" onClick={() => mutation.mutate()} disabled={!hasItems || mutation.isPending} className="flex-1">
            {mutation.isPending ? "Processing…" : "Confirm return"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

export default function SalesPage() {
  const { can } = useAuth();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [detail, setDetail] = useState<Sale | null>(null);
  const [returning, setReturning] = useState<Sale | null>(null);

  const { data, isLoading } = useQuery<Paginated<Sale>>({
    queryKey: ["sales", search, page],
    queryFn: () =>
      api<Paginated<Sale>>(
        `/sales?search=${encodeURIComponent(search)}&page=${page}&per_page=20`,
      ),
  });

  const { data: saleDetail } = useQuery<{ data: Sale }>({
    queryKey: ["sale-detail", detail?.id],
    queryFn: () => api<{ data: Sale }>(`/sales/${detail?.id}`),
    enabled: !!detail?.id,
  });

  const statusColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    completed: "success", held: "warning", draft: "default",
  };
  const payColor: Record<string, "success" | "warning" | "danger" | "default"> = {
    paid: "success", partial: "warning", unpaid: "danger",
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-xl font-semibold">Sales</h1>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
        <Input
          className="pl-9"
          placeholder="Search by invoice number…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1); }}
        />
      </div>

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Invoice</TableHead>
              <TableHead>Customer</TableHead>
              <TableHead>Date</TableHead>
              <TableHead className="text-right">Total</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Payment</TableHead>
              <TableHead className="w-20" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              Array.from({ length: 8 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 7 }).map((__, j) => (
                    <TableCell key={j}><Skeleton className="h-4 w-full" /></TableCell>
                  ))}
                </TableRow>
              ))
            ) : data?.data?.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="py-12 text-center text-[var(--muted)]">
                  No sales yet. Use the POS terminal to make a sale.
                </TableCell>
              </TableRow>
            ) : (
              data?.data?.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="font-mono text-xs font-medium">{s.invoice_number}</TableCell>
                  <TableCell>{s.customer?.name ?? <span className="text-[var(--muted)]">Walk-in</span>}</TableCell>
                  <TableCell className="text-[var(--muted)]">{new Date(s.sold_at).toLocaleDateString()}</TableCell>
                  <TableCell className="text-right font-medium">{formatCurrency(s.total)}</TableCell>
                  <TableCell><Badge tone={statusColor[s.status] ?? "default"}>{s.status}</Badge></TableCell>
                  <TableCell><Badge tone={payColor[s.payment_status] ?? "default"}>{s.payment_status}</Badge></TableCell>
                  <TableCell>
                    {can("sales.view") && (
                      <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setDetail(s)}>
                        <Eye className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
        {data?.meta && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
            <p className="text-sm text-[var(--muted)]">Page {data.meta.current_page} of {data.meta.last_page}</p>
            <div className="flex gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page >= (data.meta.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}>
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}
      </div>

      {detail && saleDetail && (
        <SaleDetailModal
          sale={saleDetail.data}
          onClose={() => setDetail(null)}
          onReturn={() => { setReturning(saleDetail.data); setDetail(null); }}
        />
      )}
      {returning && (
        <ReturnModal sale={returning} onClose={() => setReturning(null)} />
      )}
    </div>
  );
}
