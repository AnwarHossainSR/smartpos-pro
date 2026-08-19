"use client";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { use } from "react";

interface OrderStatus {
  invoice_number: string | null;
  status: string;
  payment_status: string;
  sold_at: string | null;
  currency: string | null;
  subtotal: number;
  discount_amount: number;
  tax_amount: number;
  shipping_amount: number;
  total: number;
  paid_amount: number;
  due_amount: number;
  items: { name: string; quantity: number; unit_price: number; line_total: number }[];
  seller: { name: string | null; phone: string | null; email: string | null; branch: string | null };
}

const PAYMENT_TONE: Record<string, "success" | "warning" | "danger"> = {
  paid: "success",
  partial: "warning",
  due: "danger",
};

export default function OrderStatusPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const t = useTranslation();

  const { data, isPending, error } = useQuery({
    queryKey: ["order-status", token],
    queryFn: () => api<{ data: OrderStatus }>(`/orders/${token}`),
    retry: false,
  });

  if (isPending) {
    return (
      <main className="mx-auto w-full max-w-2xl space-y-4 p-6">
        <Skeleton className="h-8 w-56" />
        <Skeleton className="h-64 w-full" />
      </main>
    );
  }

  if (error) {
    const notFound = error instanceof ApiError && error.status === 404;
    return (
      <main className="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-center justify-center gap-2 p-6 text-center">
        <h1 className="text-xl font-semibold">{t("orderStatus.notFoundTitle")}</h1>
        <p className="text-sm text-[var(--muted)]">
          {notFound ? t("orderStatus.notFoundBody") : t("orderStatus.loadFailed")}
        </p>
      </main>
    );
  }

  const order = data.data;
  const currency = order.currency ?? undefined;
  const money = (value: number) => formatCurrency(value, currency);

  return (
    <main className="mx-auto w-full max-w-2xl space-y-4 p-4 sm:p-6">
      <header className="space-y-1">
        <p className="text-sm text-[var(--muted)]">{order.seller.name}</p>
        <h1 className="text-2xl font-semibold">
          {t("orderStatus.title", { invoice: order.invoice_number ?? "—" })}
        </h1>
        <p className="text-sm text-[var(--muted)]">{formatDate(order.sold_at)}</p>
      </header>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <CardTitle className="text-base">{t("orderStatus.status")}</CardTitle>
          <div className="flex gap-2">
            <Badge tone={order.status === "cancelled" ? "danger" : "info"}>
              {t(`orderStatus.state.${order.status}`)}
            </Badge>
            <Badge tone={PAYMENT_TONE[order.payment_status] ?? "default"}>
              {t(`orderStatus.payment.${order.payment_status}`)}
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <ul className="divide-y divide-[var(--border)]">
            {order.items.map((item, index) => (
              <li key={`${item.name}-${index}`} className="flex justify-between gap-4 py-2 text-sm">
                <span className="min-w-0">
                  <span className="block truncate">{item.name}</span>
                  <span className="text-xs text-[var(--muted)]">
                    {item.quantity} × {money(item.unit_price)}
                  </span>
                </span>
                <span className="shrink-0 font-medium">{money(item.line_total)}</span>
              </li>
            ))}
          </ul>

          <dl className="space-y-1 text-sm">
            <Row label={t("orderStatus.subtotal")} value={money(order.subtotal)} />
            {order.discount_amount > 0 && (
              <Row label={t("orderStatus.discount")} value={`− ${money(order.discount_amount)}`} />
            )}
            {order.tax_amount > 0 && <Row label={t("orderStatus.tax")} value={money(order.tax_amount)} />}
            {order.shipping_amount > 0 && (
              <Row label={t("orderStatus.shipping")} value={money(order.shipping_amount)} />
            )}
            <Row label={t("orderStatus.total")} value={money(order.total)} strong />
            <Row label={t("orderStatus.paid")} value={money(order.paid_amount)} />
            {order.due_amount > 0 && (
              <Row label={t("orderStatus.due")} value={money(order.due_amount)} strong />
            )}
          </dl>
        </CardContent>
      </Card>

      {(order.seller.phone || order.seller.email || order.seller.branch) && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("orderStatus.contact")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-1 text-sm text-[var(--muted)]">
            {order.seller.branch && <p>{order.seller.branch}</p>}
            {order.seller.phone && <p>{order.seller.phone}</p>}
            {order.seller.email && <p>{order.seller.email}</p>}
          </CardContent>
        </Card>
      )}
    </main>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className={`flex justify-between gap-4 ${strong ? "font-semibold" : ""}`}>
      <dt className={strong ? "" : "text-[var(--muted)]"}>{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}
