import { useQuery } from "@tanstack/react-query";
import {
  Area,
  AreaChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { useTranslation } from "@/i18n/provider";
import { Link } from "react-router";
import { api } from "@/lib/api";
import { StatCard } from "@/components/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { formatCurrency, formatNumber, formatDateTime } from "@/lib/utils";
import {
  TrendingUp,
  TrendingDown,
  ShoppingCart,
  Package,
  Wallet,
  Receipt,
  AlertTriangle,
} from "lucide-react";

interface TopProduct {
  name: string;
  quantity: number;
  revenue: number;
}

interface RecentSale {
  id: string;
  invoice_number: string;
  total: number;
  payment_status: string;
  sold_at: string;
  customer_name: string | null;
}

interface LowStockItem {
  name: string;
  warehouse: string;
  quantity: number;
  min_stock_level: number;
}

interface WeekComparison {
  this_week: number;
  last_week: number;
  change_pct: number;
}

interface Summary {
  todays_sales: number;
  todays_transactions: number;
  total_due: number;
  customer_dues: number;
  total_products: number;
  total_customers: number;
  low_stock_count: number;
  sales_trend: { day: string; total: number }[];
  top_products: TopProduct[];
  recent_sales: RecentSale[];
  low_stock_items: LowStockItem[];
  week_comparison: WeekComparison;
}

export default function DashboardPage() {
  const t = useTranslation();
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard-summary"],
    queryFn: () => api<{ data: Summary }>("/dashboard/summary"),
  });

  const s = data?.data;

  const weekComp = s?.week_comparison;
  const weekPct = weekComp?.change_pct ?? 0;
  const weekPositive = weekPct >= 0;

  return (
    <div className="space-y-6">
      {/* Row 1: KPI cards + revenue comparison */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard label={t("dashboard.todaySales")} value={formatCurrency(s?.todays_sales ?? 0)} loading={isLoading} tone="success" />
        <StatCard label={t("dashboard.transactions")} value={formatNumber(s?.todays_transactions ?? 0)} loading={isLoading} />
        <StatCard label={t("dashboard.customerDues")} value={formatCurrency(s?.customer_dues ?? 0)} loading={isLoading} tone="warning" />
        <StatCard label={t("dashboard.lowStockItems")} value={formatNumber(s?.low_stock_count ?? 0)} loading={isLoading} tone={s && s.low_stock_count > 0 ? "danger" : "default"} />
        <Card className="p-5">
          <div className="text-sm text-[var(--muted)]">{t("dashboard.weekComparison")}</div>
          {isLoading ? (
            <div className="mt-2 h-8 w-24 animate-pulse rounded bg-[var(--muted)]/20" />
          ) : (
            <div className="mt-1 flex items-center gap-2">
              <div className="text-2xl font-semibold tracking-tight">
                {weekPositive ? "+" : ""}{weekPct}%
              </div>
              {weekPositive ? (
                <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-400" />
              ) : (
                <TrendingDown className="h-5 w-5 text-red-600 dark:text-red-400" />
              )}
            </div>
          )}
          {weekComp && (
            <div className="mt-1 text-xs text-[var(--muted)]">
              {formatCurrency(weekComp.this_week)} / {formatCurrency(weekComp.last_week)}
            </div>
          )}
        </Card>
      </div>

      {/* Row 2: 14-day sales trend area chart */}
      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard.salesTrend")}</CardTitle>
        </CardHeader>
        <CardContent className="h-72">
          {s?.sales_trend?.length ? (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={s.sales_trend} margin={{ left: -18, right: 8, top: 8 }}>
                <defs>
                  <linearGradient id="fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--primary)" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="var(--primary)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="day" tick={{ fontSize: 11 }} stroke="var(--muted)" tickLine={false} axisLine={false} />
                <YAxis tick={{ fontSize: 11 }} stroke="var(--muted)" tickLine={false} axisLine={false} width={56} />
                <Tooltip
                  contentStyle={{
                    background: "var(--surface)",
                    border: "1px solid var(--border)",
                    borderRadius: 12,
                    fontSize: 12,
                  }}
                  formatter={(v) => formatCurrency(Number(v))}
                />
                <Area type="monotone" dataKey="total" stroke="var(--primary)" strokeWidth={2} fill="url(#fill)" />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-[var(--muted)]">
              {t("dashboard.noSalesData")}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Row 3: Top products + Recent transactions */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Top products mini-table */}
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard.topProducts")}</CardTitle>
          </CardHeader>
          <CardContent>
            {s?.top_products?.length ? (
              <div className="space-y-3">
                {s.top_products.map((product, idx) => (
                  <div key={idx} className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <span className="text-sm font-medium text-[var(--muted)]">#{idx + 1}</span>
                      <span className="text-sm">{product.name}</span>
                    </div>
                    <div className="flex items-center gap-4 text-sm">
                      <span className="text-[var(--muted)]">{product.quantity} sold</span>
                      <span className="font-medium">{formatCurrency(product.revenue)}</span>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex h-32 items-center justify-center text-sm text-[var(--muted)]">
                {t("dashboard.noTopProducts")}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent transactions list */}
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboard.recentTransactions")}</CardTitle>
          </CardHeader>
          <CardContent>
            {s?.recent_sales?.length ? (
              <div className="space-y-3">
                {s.recent_sales.map((sale) => (
                  <Link
                    key={sale.id}
                    to={`/sales?filter=${sale.id}`}
                    className="flex items-center justify-between rounded-lg p-2 hover:bg-[var(--muted)]/10 transition-colors"
                  >
                    <div className="flex flex-col gap-1">
                      <span className="text-sm font-medium">{sale.invoice_number}</span>
                      <span className="text-xs text-[var(--muted)]">
                        {sale.customer_name ?? t("dashboard.walkIn")} · {formatDateTime(sale.sold_at)}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge tone={sale.payment_status === "paid" ? "success" : "danger"}>
                        {t(`dashboard.status.${sale.payment_status}`)}
                      </Badge>
                      <span className="text-sm font-medium">{formatCurrency(sale.total)}</span>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <div className="flex h-32 items-center justify-center text-sm text-[var(--muted)]">
                {t("dashboard.noRecentSales")}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Row 4: Low-stock strip */}
      {s?.low_stock_items?.length ? (
        <div className="space-y-2">
          <h3 className="text-sm font-medium text-amber-600 dark:text-amber-400 flex items-center gap-2">
            <AlertTriangle className="h-4 w-4" />
            {t("dashboard.lowStockAlert")}
          </h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {s.low_stock_items.map((item, idx) => (
              <Link
                key={idx}
                to={`/inventory?filter=low-stock`}
                className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/30 hover:shadow-sm transition-shadow"
              >
                <div className="text-sm font-medium truncate">{item.name}</div>
                <div className="text-xs text-[var(--muted)]">{item.warehouse}</div>
                <div className="mt-1 text-xs">
                  <span className="text-amber-600 dark:text-amber-400 font-medium">{item.quantity}</span>
                  <span className="text-[var(--muted)]"> / {item.min_stock_level}</span>
                </div>
              </Link>
            ))}
          </div>
        </div>
      ) : null}

      {/* Row 5: Quick actions */}
      <Card>
        <CardHeader>
          <CardTitle>{t("dashboard.quickActions")}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
              to="/pos"
              className="flex flex-col items-center gap-2 rounded-lg border p-4 hover:bg-[var(--muted)]/10 transition-colors"
            >
              <ShoppingCart className="h-6 w-6 text-[var(--primary)]" />
              <span className="text-sm font-medium">{t("dashboard.newSale")}</span>
            </Link>
            <Link
              to="/products?new=1"
              className="flex flex-col items-center gap-2 rounded-lg border p-4 hover:bg-[var(--muted)]/10 transition-colors"
            >
              <Package className="h-6 w-6 text-[var(--primary)]" />
              <span className="text-sm font-medium">{t("dashboard.addProduct")}</span>
            </Link>
            <Link
              to="/cash-register"
              className="flex flex-col items-center gap-2 rounded-lg border p-4 hover:bg-[var(--muted)]/10 transition-colors"
            >
              <Wallet className="h-6 w-6 text-[var(--primary)]" />
              <span className="text-sm font-medium">{t("dashboard.openRegister")}</span>
            </Link>
            <Link
              to="/expenses?new=1"
              className="flex flex-col items-center gap-2 rounded-lg border p-4 hover:bg-[var(--muted)]/10 transition-colors"
            >
              <Receipt className="h-6 w-6 text-[var(--primary)]" />
              <span className="text-sm font-medium">{t("dashboard.recordExpense")}</span>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
