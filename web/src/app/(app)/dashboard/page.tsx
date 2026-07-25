"use client";

import { useQuery } from "@tanstack/react-query";
import {
  Area,
  AreaChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { api } from "@/lib/api";
import { StatCard } from "@/components/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency, formatNumber } from "@/lib/utils";

interface Summary {
  todays_sales: number;
  todays_transactions: number;
  total_due: number;
  customer_dues: number;
  total_products: number;
  total_customers: number;
  low_stock_count: number;
  sales_trend: { day: string; total: number }[];
}

export default function DashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard-summary"],
    queryFn: () => api<{ data: Summary }>("/dashboard/summary"),
  });

  const s = data?.data;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Today's sales" value={formatCurrency(s?.todays_sales ?? 0)} loading={isLoading} tone="success" />
        <StatCard label="Transactions" value={formatNumber(s?.todays_transactions ?? 0)} loading={isLoading} />
        <StatCard label="Customer dues" value={formatCurrency(s?.customer_dues ?? 0)} loading={isLoading} tone="warning" />
        <StatCard label="Low stock items" value={formatNumber(s?.low_stock_count ?? 0)} loading={isLoading} tone={s && s.low_stock_count > 0 ? "danger" : "default"} />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Sales trend (last 14 days)</CardTitle>
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
                No sales data yet
              </div>
            )}
          </CardContent>
        </Card>

        <div className="space-y-4">
          <StatCard label="Total products" value={formatNumber(s?.total_products ?? 0)} loading={isLoading} />
          <StatCard label="Total customers" value={formatNumber(s?.total_customers ?? 0)} loading={isLoading} />
          <StatCard label="Outstanding invoices" value={formatCurrency(s?.total_due ?? 0)} loading={isLoading} tone="warning" />
        </div>
      </div>
    </div>
  );
}
