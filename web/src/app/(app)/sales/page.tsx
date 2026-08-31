import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Eye, FileText, Loader2, Mail, RotateCcw } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api, apiBlob, type Envelope, errorMessage, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { downloadBlob, formatCurrency, formatDate, formatDateTime, openPdfBlob } from "@/lib/utils";
import { useAuth } from "@/stores/auth";

interface SaleItem {
  id: string;
  name: string;
  quantity: number;
  unit_price: number;
  discount_amount: number;
  line_total: number;
}

interface Sale {
  id: string;
  // Null until the sale is completed — a held/draft cart carries no invoice number.
  invoice_number: string | null;
  status: string;
  payment_status: string;
  total: number;
  paid_amount: number;
  due_amount: number;
  sold_at: string;
  customer: { id: string; name: string } | null;
  cashier: { id: string; name: string } | null;
  items?: SaleItem[];
  payments?: { method: string; amount: number }[];
}

const statusColor: Record<string, "success" | "warning" | "danger" | "info" | "default"> = {
  completed: "success",
  held: "warning",
  draft: "default",
  quotation: "info",
};
const payColor: Record<string, "success" | "warning" | "danger" | "default"> = {
  paid: "success",
  partial: "warning",
  unpaid: "danger",
};

/** Fetches the invoice PDF with `?download=1` and saves it to disk. `downloadingId` tracks a
 *  single row at a time so each button gets its own spinner without a per-row state map. */
function useInvoiceDownload() {
  const t = useTranslation();
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  const downloadInvoice = async (sale: Pick<Sale, "id" | "invoice_number">) => {
    setDownloadingId(sale.id);
    try {
      const blob = await apiBlob(`/sales/${sale.id}/invoice?download=1`);
      downloadBlob(blob, `${sale.invoice_number}.pdf`);
    } catch {
      toast(t("actions.downloadInvoiceFailed"), "error");
    } finally {
      setDownloadingId(null);
    }
  };

  return { downloadingId, downloadInvoice };
}

function SaleDetailModal({
  sale,
  onClose,
  onReturn,
}: {
  sale: Sale;
  onClose: () => void;
  onReturn: () => void;
}) {
  const { can } = useAuth();
  const t = useTranslation();
  const [loadingInvoice, setLoadingInvoice] = useState(false);
  const [emailingLink, setEmailingLink] = useState(false);
  const { downloadingId, downloadInvoice } = useInvoiceDownload();

  const emailStatusLink = async () => {
    setEmailingLink(true);
    try {
      const res = await api<Envelope<{ sent_to: string }>>(`/sales/${sale.id}/email-status-link`, {
        method: "POST",
      });
      toast(t("orderStatus.linkSent", { email: res.data.sent_to }));
    } catch (e) {
      toast(errorMessage(e, t("orderStatus.linkFailed")), "error");
    } finally {
      setEmailingLink(false);
    }
  };

  const viewInvoice = async () => {
    setLoadingInvoice(true);
    try {
      openPdfBlob(await apiBlob(`/sales/${sale.id}/invoice`));
    } catch {
      toast("Could not load invoice", "error");
    } finally {
      setLoadingInvoice(false);
    }
  };

  return (
    <Dialog
      open
      title={sale.invoice_number ? `Invoice #${sale.invoice_number}` : "Sale (not yet invoiced)"}
      onClose={onClose}
    >
      <div className="space-y-4 text-sm">
        <div className="grid grid-cols-2 gap-2">
          <div>
            <span className="text-[var(--muted)]">Status</span>
            <br />
            <Badge tone={statusColor[sale.status] ?? "default"}>{sale.status}</Badge>
          </div>
          <div>
            <span className="text-[var(--muted)]">Payment</span>
            <br />
            <Badge tone={payColor[sale.payment_status] ?? "default"}>{sale.payment_status}</Badge>
          </div>
          <div>
            <span className="text-[var(--muted)]">Customer</span>
            <br />
            {sale.customer?.name ?? "—"}
          </div>
          <div>
            <span className="text-[var(--muted)]">Date</span>
            <br />
            {formatDateTime(sale.sold_at)}
          </div>
        </div>
        <hr />
        <div className="space-y-1">
          {sale.items?.map((item) => (
            <div key={item.id} className="flex justify-between">
              <span className="flex-1 truncate">
                {item.name} × {item.quantity}
              </span>
              <span className="ms-4">{formatCurrency(item.line_total)}</span>
            </div>
          ))}
        </div>
        <hr />
        <div className="space-y-1">
          {sale.payments?.map((p, i) => (
            <div key={i} className="flex justify-between text-[var(--muted)]">
              <span className="capitalize">{p.method}</span>
              <span>{formatCurrency(p.amount)}</span>
            </div>
          ))}
          <div className="flex justify-between font-semibold">
            <span>Total</span>
            <span>{formatCurrency(sale.total)}</span>
          </div>
          {sale.due_amount > 0 && (
            <div className="flex justify-between text-amber-600">
              <span>Due</span>
              <span>{formatCurrency(sale.due_amount)}</span>
            </div>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={onClose} className="flex-1 basis-28">
            Close
          </Button>
          {can("sales.view") && (
            <>
              <Button
                variant="outline"
                onClick={viewInvoice}
                disabled={loadingInvoice}
                className="flex-1 basis-28"
              >
                <FileText className="h-4 w-4" /> {loadingInvoice ? "Loading…" : "View invoice"}
              </Button>
              <Button
                variant="outline"
                onClick={() => downloadInvoice(sale)}
                disabled={downloadingId === sale.id}
                className="flex-1 basis-28"
              >
                {downloadingId === sale.id ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Download className="h-4 w-4" />
                )}
                {downloadingId === sale.id
                  ? t("actions.downloading")
                  : t("actions.downloadInvoice")}
              </Button>
              {sale.status === "completed" && (
                <Button
                  variant="outline"
                  onClick={emailStatusLink}
                  disabled={emailingLink}
                  className="flex-1 basis-28"
                >
                  <Mail className="h-4 w-4" />{" "}
                  {emailingLink ? t("orderStatus.linkSending") : t("orderStatus.linkAction")}
                </Button>
              )}
            </>
          )}
          {can("sales.refund") && sale.status === "completed" && (
            <Button variant="danger" onClick={onReturn} className="flex-1 basis-28">
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
  const [quantities, setQuantities] = useState<Record<string, number>>({});
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
      qc.invalidateQueries({ queryKey: queryKeys.sales.all });
      toast("Return processed");
      onClose();
    },
    // Kept for the domain-specific fallback wording; owning onError means owning the toast.
    onError: (e) => toast(errorMessage(e, "Return failed"), "error"),
  });

  const hasItems = Object.values(quantities).some((q) => q > 0);

  return (
    <Dialog open title={`Return – #${sale.invoice_number ?? sale.id}`} onClose={onClose}>
      <div className="space-y-4 text-sm">
        <p className="text-[var(--muted)]">Enter quantities to return. Leave blank to skip item.</p>
        <div className="space-y-2">
          {sale.items?.map((item) => (
            <div key={item.id} className="flex items-center justify-between gap-3">
              <span className="flex-1">
                {item.name} (sold: {item.quantity})
              </span>
              <Input
                type="number"
                className="w-24"
                min="0"
                max={item.quantity}
                value={quantities[item.id] ?? ""}
                onChange={(e) =>
                  setQuantities((p) => ({ ...p, [item.id]: Number(e.target.value) }))
                }
              />
            </div>
          ))}
        </div>
        <div>
          <Label>Reason</Label>
          <Input
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Return reason…"
          />
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={onClose}
            disabled={mutation.isPending}
            className="flex-1"
          >
            Cancel
          </Button>
          <Button
            variant="danger"
            onClick={() => mutation.mutate()}
            disabled={!hasItems || mutation.isPending}
            className="flex-1"
          >
            {mutation.isPending ? "Processing…" : "Confirm return"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

export default function SalesPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const { downloadingId, downloadInvoice } = useInvoiceDownload();
  const [detail, setDetail] = useState<Sale | null>(null);
  const [returning, setReturning] = useState<Sale | null>(null);

  const list = useResourceList<Sale>({
    queryKey: queryKeys.sales.lists(),
    queryFn: (p) => api<Paginated<Sale>>(`/sales?${buildListQuery(p)}`),
  });

  // The list projection omits items and payments, which the detail modal and the return
  // flow both need — so the row click refetches the full record.
  const { data: saleDetail } = useQuery({
    queryKey: queryKeys.sales.detail(detail?.id),
    queryFn: () => api<Envelope<Sale>>(`/sales/${detail?.id}`),
    enabled: !!detail?.id,
  });

  const columns: DataTableColumn<Sale>[] = [
    {
      key: "invoice",
      header: "Invoice",
      className: "font-mono text-xs font-medium",
      render: (s) => s.invoice_number ?? <span className="text-[var(--muted)]">—</span>,
    },
    {
      key: "customer",
      header: "Customer",
      render: (s) => s.customer?.name ?? <span className="text-[var(--muted)]">Walk-in</span>,
    },
    {
      key: "date",
      header: "Date",
      className: "text-[var(--muted)]",
      render: (s) => formatDate(s.sold_at),
    },
    {
      key: "total",
      header: "Total",
      align: "right",
      className: "font-medium",
      render: (s) => formatCurrency(s.total),
    },
    {
      key: "status",
      header: "Status",
      render: (s) => <Badge tone={statusColor[s.status] ?? "default"}>{s.status}</Badge>,
    },
    {
      key: "payment",
      header: "Payment",
      render: (s) => (
        <Badge tone={payColor[s.payment_status] ?? "default"}>{s.payment_status}</Badge>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search by invoice number…"
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(s) => s.id}
          loading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No sales yet. Use the POS terminal to make a sale."
          actions={(s) =>
            can("sales.view") && (
              <>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => setDetail(s)}
                >
                  <Eye className="h-3.5 w-3.5" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  title={t("actions.downloadInvoice")}
                  aria-label={t("actions.downloadInvoice")}
                  disabled={downloadingId === s.id}
                  onClick={() => downloadInvoice(s)}
                >
                  {downloadingId === s.id ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <FileText className="h-3.5 w-3.5" />
                  )}
                </Button>
              </>
            )
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

      {detail && saleDetail && (
        <SaleDetailModal
          sale={saleDetail.data}
          onClose={() => setDetail(null)}
          onReturn={() => {
            setReturning(saleDetail.data);
            setDetail(null);
          }}
        />
      )}
      {returning && <ReturnModal sale={returning} onClose={() => setReturning(null)} />}
    </div>
  );
}
