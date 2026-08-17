"use client";

import { useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import {
  Area, AreaChart, Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from "recharts";
import { Percent } from "lucide-react";
import { api } from "@/lib/api";
import { formatCurrency, formatNumber } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";

type ReportType = "sales-summary" | "sales-by-product" | "profit" | "inventory-valuation" | "low-stock" | "dues";

const REPORTS: { id: ReportType; label: string }[] = [
  { id: "sales-summary", label: "Sales summary" },
  { id: "sales-by-product", label: "Sales by product" },
  { id: "profit", label: "Profit & loss" },
  { id: "inventory-valuation", label: "Inventory valuation" },
  { id: "low-stock", label: "Low stock" },
  { id: "dues", label: "Customer dues" },
];

export default function ReportsPage() {
  const [report, setReport] = useState<ReportType>("sales-summary");
  const [from, setFrom] = useState(() => {
    const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10);
  });
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));

  const params = `?from=${from}&to=${to}`;

  const { data, isLoading } = useQuery({
    queryKey: ["report", report, from, to],
    queryFn: () => api<{ data: unknown }>(`/reports/${report}${params}`),
  });

  const r = data?.data as Record<string, unknown> | null;

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Button asChild variant="outline" size="sm">
            <Link href="/reports/tax"><Percent className="h-3.5 w-3.5" /> Tax report</Link>
          </Button>
        </div>
        <div className="flex items-center gap-2 text-sm">
          <Label className="mb-0">From</Label>
          <Input type="date" className="h-9 w-36" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Label className="mb-0">To</Label>
          <Input type="date" className="h-9 w-36" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>

      <div className="flex gap-2 flex-wrap">
        {REPORTS.map((rep) => (
          <Button
            key={rep.id}
            size="sm"
            variant={report === rep.id ? "primary" : "outline"}
            onClick={() => setReport(rep.id)}
          >
            {rep.label}
          </Button>
        ))}
      </div>

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-32 rounded-[var(--radius)]" />)}
        </div>
      ) : (
        <ReportContent report={report} data={r} />
      )}
    </div>
  );
}

function ReportContent({ report, data }: { report: ReportType; data: Record<string, unknown> | null }) {
  if (!data) return <div className="py-12 text-center text-[var(--muted)]">No data for this period.</div>;

  if (report === "sales-summary") {
    const summary = data as { total_sales: number; total_revenue: number; total_discount: number; total_tax: number; avg_order_value: number; daily?: { day: string; total: number }[] };
    return (
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { label: "Total sales", value: formatNumber(summary.total_sales ?? 0) },
            { label: "Revenue", value: formatCurrency(summary.total_revenue ?? 0) },
            { label: "Discounts", value: formatCurrency(summary.total_discount ?? 0) },
            { label: "Avg order value", value: formatCurrency(summary.avg_order_value ?? 0) },
          ].map((s) => (
            <Card key={s.label}>
              <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{s.label}</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{s.value}</p></CardContent>
            </Card>
          ))}
        </div>
        {summary.daily && summary.daily.length > 0 && (
          <Card>
            <CardHeader><CardTitle>Daily trend</CardTitle></CardHeader>
            <CardContent className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={summary.daily}>
                  <XAxis dataKey="day" tick={{ fontSize: 11 }} stroke="var(--muted)" tickLine={false} axisLine={false} />
                  <YAxis tick={{ fontSize: 11 }} stroke="var(--muted)" tickLine={false} axisLine={false} width={60} />
                  <Tooltip contentStyle={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, fontSize: 12 }} formatter={(v) => formatCurrency(Number(v))} />
                  <Area type="monotone" dataKey="total" stroke="var(--primary)" strokeWidth={2} fill="var(--primary)" fillOpacity={0.1} />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        )}
      </div>
    );
  }

  if (report === "sales-by-product") {
    const items = data as { items?: { name: string; quantity: number; revenue: number; profit: number }[] };
    return (
      <div className="space-y-4">
        {items.items && items.items.length > 0 && (
          <Card>
            <CardContent className="h-64 pt-4">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={items.items?.slice(0, 10)} margin={{ left: -10 }}>
                  <XAxis dataKey="name" tick={{ fontSize: 10 }} tickLine={false} axisLine={false} />
                  <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                  <Tooltip formatter={(v) => formatCurrency(Number(v))} />
                  <Bar dataKey="revenue" fill="var(--primary)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        )}
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Product</TableHead><TableHead className="text-end">Qty sold</TableHead><TableHead className="text-end">Revenue</TableHead><TableHead className="text-end">Profit</TableHead></TableRow></TableHeader>
            <TableBody>
              {items.items?.map((item, i) => (
                <TableRow key={i}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="text-end">{item.quantity}</TableCell><TableCell className="text-end">{formatCurrency(item.revenue)}</TableCell><TableCell className="text-end text-green-600">{formatCurrency(item.profit)}</TableCell></TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>
    );
  }

  if (report === "profit") {
    const p = data as { revenue: number; cost: number; gross_profit: number; expenses: number; net_profit: number; gross_margin: number };
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {[
          { label: "Revenue", value: formatCurrency(p.revenue ?? 0), tone: "success" },
          { label: "Cost of goods", value: formatCurrency(p.cost ?? 0), tone: "default" },
          { label: "Gross profit", value: formatCurrency(p.gross_profit ?? 0), tone: "success" },
          { label: "Expenses", value: formatCurrency(p.expenses ?? 0), tone: "danger" },
          { label: "Net profit", value: formatCurrency(p.net_profit ?? 0), tone: p.net_profit >= 0 ? "success" : "danger" },
          { label: "Gross margin", value: `${(p.gross_margin ?? 0).toFixed(1)}%`, tone: "default" },
        ].map((s) => (
          <Card key={s.label}>
            <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{s.label}</CardTitle></CardHeader>
            <CardContent><p className={`text-2xl font-bold ${s.tone === "success" ? "text-green-600" : s.tone === "danger" ? "text-[var(--color-danger)]" : ""}`}>{s.value}</p></CardContent>
          </Card>
        ))}
      </div>
    );
  }

  if (report === "inventory-valuation") {
    const iv = data as { items?: { name: string; sku: string; quantity: number; average_cost: number; value: number }[]; total_value: number };
    return (
      <div className="space-y-4">
        <Card><CardHeader><CardTitle>Total inventory value</CardTitle></CardHeader><CardContent><p className="text-3xl font-bold">{formatCurrency(iv.total_value ?? 0)}</p></CardContent></Card>
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Product</TableHead><TableHead>SKU</TableHead><TableHead className="text-end">Qty</TableHead><TableHead className="text-end">Avg cost</TableHead><TableHead className="text-end">Value</TableHead></TableRow></TableHeader>
            <TableBody>
              {iv.items?.map((item, i) => (
                <TableRow key={i}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="font-mono text-xs">{item.sku ?? "—"}</TableCell><TableCell className="text-end">{item.quantity}</TableCell><TableCell className="text-end">{formatCurrency(item.average_cost)}</TableCell><TableCell className="text-end font-medium">{formatCurrency(item.value)}</TableCell></TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>
    );
  }

  if (report === "low-stock") {
    const ls = data as { items?: { name: string; sku: string; quantity: number; min_stock_level: number; warehouse: string }[] };
    return (
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Product</TableHead><TableHead>SKU</TableHead><TableHead>Warehouse</TableHead><TableHead className="text-end">Qty</TableHead><TableHead className="text-end">Min level</TableHead></TableRow></TableHeader>
          <TableBody>
            {ls.items?.length === 0 ? <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--muted)]">No low stock items.</TableCell></TableRow>
              : ls.items?.map((item, i) => (
                <TableRow key={i}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="font-mono text-xs">{item.sku ?? "—"}</TableCell><TableCell>{item.warehouse}</TableCell><TableCell className="text-end text-[var(--color-danger)]">{item.quantity}</TableCell><TableCell className="text-end">{item.min_stock_level}</TableCell></TableRow>
              ))}
          </TableBody>
        </Table>
      </div>
    );
  }

  if (report === "dues") {
    const d = data as { customers?: { name: string; phone: string; balance: number }[] };
    return (
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Customer</TableHead><TableHead>Phone</TableHead><TableHead className="text-end">Balance due</TableHead></TableRow></TableHeader>
          <TableBody>
            {d.customers?.length === 0 ? <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--muted)]">No outstanding dues.</TableCell></TableRow>
              : d.customers?.map((c, i) => (
                <TableRow key={i}><TableCell className="font-medium">{c.name}</TableCell><TableCell>{c.phone ?? "—"}</TableCell><TableCell className="text-end font-medium text-amber-600">{formatCurrency(c.balance)}</TableCell></TableRow>
              ))}
          </TableBody>
        </Table>
      </div>
    );
  }

  return <pre className="text-xs overflow-auto">{JSON.stringify(data, null, 2)}</pre>;
}
