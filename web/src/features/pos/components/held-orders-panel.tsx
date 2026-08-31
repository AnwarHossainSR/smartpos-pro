import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Clock, RotateCcw, Trash2 } from "lucide-react";
import { useState } from "react";
import type { Product } from "@/app/(app)/pos/page";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { ApiError, api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { formatCurrency, formatDateTime } from "@/lib/utils";

export interface HeldSale {
  id: string;
  reference: string;
  invoice_number: string;
  customer: { id: string; name: string } | null;
  items: {
    name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    product_id: string;
  }[];
  total: number;
  sold_at: string;
  status: string;
}

export interface HeldCartItem {
  product: Product;
  qty: number;
  unit_price: number;
  discount: number;
}

interface HeldOrdersPanelProps {
  open: boolean;
  onClose: () => void;
  onResume: (cart: HeldCartItem[], customerId: string | null) => void;
}

export function HeldOrdersPanel({ open, onClose, onResume }: HeldOrdersPanelProps) {
  const t = useTranslation();
  const qc = useQueryClient();
  const [discardTarget, setDiscardTarget] = useState<HeldSale | null>(null);

  const { data, isLoading } = useQuery<Paginated<HeldSale>>({
    queryKey: queryKeys.heldSales.list(),
    queryFn: () => api<Paginated<HeldSale>>("/sales?status=held&per_page=20&sort=-sold_at"),
    enabled: open,
  });

  const resumeMutation = useMutation({
    mutationFn: async (saleId: string) => {
      const sale = await api<{ data: HeldSale }>(`/sales/${saleId}`);
      const items = sale.data.items ?? [];

      const productIds = [...new Set(items.map((i) => i.product_id))];
      const products = await Promise.all(
        productIds.map((id) => api<{ data: Product }>(`/products/${id}`).then((r) => r.data)),
      );
      const productMap = new Map(products.map((p) => [p.id, p]));

      const cart: HeldCartItem[] = items
        .map((item) => {
          const product = productMap.get(item.product_id);
          if (!product) return null;
          return {
            product,
            qty: item.quantity,
            unit_price: item.unit_price,
            discount: 0,
          };
        })
        .filter(Boolean) as HeldCartItem[];

      return { cart, customerId: sale.data.customer?.id ?? null };
    },
    onSuccess: ({ cart, customerId }) => {
      onResume(cart, customerId);
      qc.invalidateQueries({ queryKey: queryKeys.heldSales.all });
      onClose();
    },
    onError: (e: unknown) => {
      const detail = e instanceof ApiError ? e.message : "Could not resume sale";
      toast(detail, "error");
    },
  });

  const discardMutation = useMutation({
    mutationFn: (saleId: string) => api(`/sales/${saleId}/void`, { method: "POST" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.heldSales.all });
      qc.invalidateQueries({ queryKey: queryKeys.sales.all });
      toast(t("pos.heldOrders.discardSuccess"), "success");
      setDiscardTarget(null);
    },
    onError: (e: unknown) => {
      const detail = e instanceof ApiError ? e.message : "Could not discard sale";
      toast(detail, "error");
    },
  });

  if (!open) return null;

  const sales = data?.data ?? [];

  return (
    <>
      <Dialog open title={t("pos.heldOrders.title")} onClose={onClose}>
        <div className="space-y-3 max-h-96 overflow-y-auto">
          {isLoading ? (
            <div className="space-y-2">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-20 rounded-[var(--radius)]" />
              ))}
            </div>
          ) : sales.length === 0 ? (
            <p className="text-center text-sm text-[var(--muted)] py-8">
              {t("pos.heldOrders.empty")}
            </p>
          ) : (
            sales.map((sale) => (
              <div
                key={sale.id}
                className="rounded-[var(--radius)] border border-[var(--border)] p-3 space-y-2"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-medium truncate">
                      {sale.reference || sale.invoice_number}
                    </p>
                    <p className="text-xs text-[var(--muted)]">
                      {sale.customer?.name ?? t("pos.heldOrders.walkIn")}
                    </p>
                  </div>
                  <span className="text-sm font-semibold shrink-0">
                    {formatCurrency(sale.total)}
                  </span>
                </div>
                <div className="flex items-center justify-between text-xs text-[var(--muted)]">
                  <span>
                    {sale.items?.length ?? 0} {t("pos.heldOrders.items")}
                  </span>
                  <span className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    {formatDateTime(sale.sold_at)}
                  </span>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => resumeMutation.mutate(sale.id)}
                    disabled={resumeMutation.isPending}
                  >
                    <RotateCcw className="h-3.5 w-3.5" />
                    {t("pos.heldOrders.resume")}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-[var(--color-danger)]"
                    onClick={() => setDiscardTarget(sale)}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                    {t("pos.heldOrders.discard")}
                  </Button>
                </div>
              </div>
            ))
          )}
        </div>
      </Dialog>

      {discardTarget && (
        <Dialog
          open
          title={t("pos.heldOrders.discardTitle")}
          description={t("pos.heldOrders.discardConfirm", {
            reference: discardTarget.reference || discardTarget.invoice_number,
          })}
          confirmLabel={t("pos.heldOrders.discard")}
          confirmVariant="danger"
          onConfirm={() => discardMutation.mutate(discardTarget.id)}
          onClose={() => setDiscardTarget(null)}
          loading={discardMutation.isPending}
        />
      )}
    </>
  );
}
