import { FileText } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { toast } from "@/components/ui/toast";
import type { Sale } from "@/features/pos/types";
import { mergeTaxComponents } from "@/features/tax/calc";
import { apiBlob } from "@/lib/api";
import { formatCurrency, formatDateTime, openPdfBlob } from "@/lib/utils";

export function ReceiptModal({ sale, onClose }: { sale: Sale; onClose: () => void }) {
  const [loadingInvoice, setLoadingInvoice] = useState(false);

  // Aggregate each item's own component breakdown when the API provides one (today, only
  // sale returns do — see the `taxes` comment on the Sale type); falls back to the single
  // blended `tax_amount` line so the receipt still works against the current `/sales` response.
  const taxComponents = mergeTaxComponents(
    sale.items?.map(
      (item) =>
        item.taxes?.map((t) => ({
          name: t.name,
          rate: t.rate,
          type: "percentage" as const,
          is_compound: false,
          base_amount: 0,
          amount: t.amount,
        })) ?? [],
    ) ?? [],
  );

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
    <Dialog open title="Receipt" onClose={onClose}>
      <div data-receipt className="space-y-3 text-sm">
        <div className="text-center">
          <p className="font-semibold text-base">Invoice #{sale.invoice_number}</p>
          <p className="text-[var(--muted)]">{formatDateTime(sale.sold_at)}</p>
          {sale.customer && <p className="mt-1">{sale.customer.name}</p>}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          {sale.items?.map((item, i) => (
            <div key={i} className="flex justify-between">
              <span className="flex-1 truncate">
                {item.name} × {item.quantity}
              </span>
              <span className="ms-4 font-medium">{formatCurrency(item.line_total)}</span>
            </div>
          ))}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          {sale.tax_amount > 0 && (
            <>
              <div className="flex justify-between text-[var(--muted)]">
                <span>Subtotal</span>
                <span>{formatCurrency(sale.subtotal)}</span>
              </div>
              {taxComponents.length > 0 ? (
                taxComponents.map((c) => (
                  <div
                    key={`${c.name}@${c.rate}`}
                    className="flex justify-between text-[var(--muted)]"
                  >
                    <span>
                      {c.name} {c.rate > 0 ? `(${c.rate}%)` : ""}
                    </span>
                    <span>{formatCurrency(c.amount)}</span>
                  </div>
                ))
              ) : (
                <div className="flex justify-between text-[var(--muted)]">
                  <span>Tax</span>
                  <span>{formatCurrency(sale.tax_amount)}</span>
                </div>
              )}
            </>
          )}
          <div className="flex justify-between font-semibold">
            <span>Total</span>
            <span>{formatCurrency(sale.total)}</span>
          </div>
          {sale.payments?.map((p, i) => (
            <div key={i} className="flex justify-between text-[var(--muted)]">
              <span className="capitalize">{p.method}</span>
              <span>{formatCurrency(p.amount)}</span>
            </div>
          ))}
          {sale.change_amount > 0 && (
            <div className="flex justify-between text-green-600 text-2xl font-bold">
              <span>Change</span>
              <span>{formatCurrency(sale.change_amount)}</span>
            </div>
          )}
          {sale.due_amount > 0 && (
            <div className="flex justify-between text-amber-600">
              <span>Due</span>
              <span>{formatCurrency(sale.due_amount)}</span>
            </div>
          )}
        </div>
        <div className="flex gap-2 mt-4">
          <Button
            variant="outline"
            className="flex-1"
            onClick={viewInvoice}
            disabled={loadingInvoice}
          >
            <FileText className="h-4 w-4" /> {loadingInvoice ? "Loading…" : "View invoice"}
          </Button>
          <Button variant="outline" className="flex-1" onClick={() => window.print()}>
            Print receipt
          </Button>
          <Button className="flex-1" onClick={onClose}>
            New sale
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
