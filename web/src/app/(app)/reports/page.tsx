import { useState } from "react";
import { Link } from "react-router";
import { useQuery } from "@tanstack/react-query";
import {
  Area, AreaChart, Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from "recharts";
import { Download, Percent, Clock } from "lucide-react";
import { api, apiBlob } from "@/lib/api";
import { useBranches } from "@/lib/lookups";
import { formatCurrency, formatNumber } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { useTranslation } from "@/i18n/context";
import { useAuth } from "@/stores/auth";
import { queryKeys } from "@/lib/query-keys";

type ReportType = "sales-summary" | "sales-by-product" | "profit" | "inventory-valuation" | "low-stock" | "dues" | "z-report" | "shifts" | "profit-loss" | "balance-sheet";

interface ZReportData {
  date: string;
  currency: string;
  totals: {
    transactions: number;
    gross_sales: number;
    discounts: number;
    tax: number;
    shipping: number;
    net_sales: number;
    amount_paid: number;
    amount_due: number;
    average_basket: number;
  };
  payment_methods: { method: string; label: string; count: number; amount: number }[];
  taxes: { total: number; components: { name: string; rate: number; base_amount: number; amount: number }[] };
  discounts: { line: number; sale: number; coupon: number; total: number };
  invoices: { count: number; first: string | null; last: string | null };
  cash_sessions: {
    count: number;
    opening_cash: number;
    expected_cash: number;
    actual_cash: number | null;
    difference: number | null;
    sessions: { id: string; register: string; cashier: string | null; status: string; opening_cash: number; expected_cash: number; actual_cash: number | null; difference: number | null }[];
  };
  voids: { count: number; amount: number };
  returns: { count: number; amount: number };
}

export default function ReportsPage() {
  const t = useTranslation();
  const { can } = useAuth();
  const [report, setReport] = useState<ReportType>("sales-summary");
  const [from, setFrom] = useState(() => {
    const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10);
  });
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  const [branchId, setBranchId] = useState<string>("");

  const [asOf, setAsOf] = useState(() => new Date().toISOString().slice(0, 10));

  const params = report === "balance-sheet" ? `?as_of=${asOf}` : `?from=${from}&to=${to}`;

  // `/reports/shifts` is also rendered by the Team screen, so it keeps the shared shifts key;
  // everything else is one parameterised report endpoint under a single `reports.run` key.
  const { data, isLoading } = useQuery({
    queryKey: report === "shifts"
      ? queryKeys.shifts.report({ from, to })
      : queryKeys.reports.run({ report, from, to, asOf }),
    queryFn: () => report === "shifts"
      ? api<{ data: unknown }>(`/reports/shifts?from=${from}&to=${to}&group_by=day`)
      : api<{ data: unknown }>(`/reports/${report}${params}`),
    enabled: report !== "z-report",
  });

  const r = data?.data as Record<string, unknown> | null;

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Button asChild variant="outline" size="sm">
            <Link to="/reports/tax"><Percent className="h-3.5 w-3.5" /> {t("reports.taxReport")}</Link>
          </Button>
        </div>
        <div className="flex items-center gap-2 text-sm">
          {report === "balance-sheet" ? (
            <>
              <Label className="mb-0">{t("reports.asOf")}</Label>
              <Input type="date" className="h-9 w-36" value={asOf} onChange={(e) => setAsOf(e.target.value)} />
            </>
          ) : (
            <>
              <Label className="mb-0">{t("reports.from")}</Label>
              <Input type="date" className="h-9 w-36" value={from} onChange={(e) => setFrom(e.target.value)} />
              <Label className="mb-0">{t("reports.to")}</Label>
              <Input type="date" className="h-9 w-36" value={to} onChange={(e) => setTo(e.target.value)} />
            </>
          )}
        </div>
      </div>

      <div className="flex gap-2 flex-wrap">
        {REPORTS.map((rep) => (
          <Button
            key={rep.id}
            size="sm"
            variant={report === rep.id ? "default" : "outline"}
            onClick={() => setReport(rep.id)}
          >
            {rep.label}
          </Button>
        ))}
        {can("reports.view") && (
          <Button
            size="sm"
            variant={report === "shifts" ? "default" : "outline"}
            onClick={() => setReport("shifts")}
          >
            <Clock className="h-3.5 w-3.5" /> Shift hours
          </Button>
        )}
      </div>

      {report === "z-report" ? (
        <ZReportTab date={from} branchId={branchId} setBranchId={setBranchId} />
      ) : isLoading ? (
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-32 rounded-[var(--radius)]" />)}
        </div>
      ) : (
        <ReportContent report={report} data={r} />
      )}
    </div>
  );
}

function ZReportTab({ date, branchId, setBranchId }: { date: string; branchId: string; setBranchId: (v: string) => void }) {
  const t = useTranslation();
  const { data, isLoading } = useQuery({
    queryKey: queryKeys.reports.zReport({ date, branch_id: branchId || null }),
    queryFn: () => api<{ data: ZReportData }>(`/reports/z-report?date=${date}${branchId ? `&branch_id=${branchId}` : ""}`),
  });

  const { branches } = useBranches();

  const report = data?.data;

  const handleDownloadPdf = async () => {
    try {
      const blob = await apiBlob(`/reports/z-report/pdf?date=${date}${branchId ? `&branch_id=${branchId}` : ""}&download=1`);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `z-report-${date}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // PDF download failed silently
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-[var(--radius)]" />)}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <Label className="mb-0">{t("reports.branch")}</Label>
        <Select value={branchId} onValueChange={setBranchId}>
          <SelectTrigger className="h-9 w-48"><SelectValue placeholder={t("reports.allBranches")} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="">{t("reports.allBranches")}</SelectItem>
            {branches.map((b) => <SelectItem key={b.id} value={b.id}>{b.name}</SelectItem>)}
          </SelectContent>
        </Select>
        <div className="ml-auto">
          <Button size="sm" variant="outline" onClick={handleDownloadPdf}>
            <Download className="h-3.5 w-3.5" /> {t("reports.downloadPdf")}
          </Button>
        </div>
      </div>

      {!report ? (
        <div className="py-12 text-center text-[var(--muted)]">{t("reports.noData")}</div>
      ) : (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.transactions")}</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatNumber(report.totals.transactions)}</p></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.netSales")}</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatCurrency(report.totals.net_sales)}</p></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.tax")}</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatCurrency(report.totals.tax)}</p></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.averageBasket")}</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatCurrency(report.totals.average_basket)}</p></CardContent>
            </Card>
          </div>

          {report.payment_methods.length > 0 && (
            <Card>
              <CardHeader><CardTitle>{t("reports.paymentMethods")}</CardTitle></CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader><TableRow><TableHead>{t("reports.method")}</TableHead><TableHead className="text-end">{t("reports.count")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {report.payment_methods.map((m) => (
                      <TableRow key={m.method}><TableCell className="font-medium">{m.label}</TableCell><TableCell className="text-end">{m.count}</TableCell><TableCell className="text-end">{formatCurrency(m.amount)}</TableCell></TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          {report.taxes.components.length > 0 && (
            <Card>
              <CardHeader><CardTitle>{t("reports.taxBreakdown")}</CardTitle></CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader><TableRow><TableHead>{t("reports.component")}</TableHead><TableHead className="text-end">{t("reports.rate")}</TableHead><TableHead className="text-end">{t("reports.base")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {report.taxes.components.map((c, i) => (
                      <TableRow key={`${c.name}-${i}`}><TableCell className="font-medium">{c.name}</TableCell><TableCell className="text-end text-[var(--muted)]">{c.rate}%</TableCell><TableCell className="text-end">{formatCurrency(c.base_amount)}</TableCell><TableCell className="text-end font-medium">{formatCurrency(c.amount)}</TableCell></TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader><CardTitle>{t("reports.cashReconciliation")}</CardTitle></CardHeader>
            <CardContent>
              {report.cash_sessions.count === 0 ? (
                <p className="text-sm text-[var(--muted)]">{t("reports.noRegisterSession")}</p>
              ) : (
                <div className="space-y-3">
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                      <p className="text-xs text-[var(--muted)]">{t("reports.openingCash")}</p>
                      <p className="text-lg font-bold">{formatCurrency(report.cash_sessions.opening_cash)}</p>
                    </div>
                    <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                      <p className="text-xs text-[var(--muted)]">{t("reports.expectedCash")}</p>
                      <p className="text-lg font-bold">{formatCurrency(report.cash_sessions.expected_cash)}</p>
                    </div>
                    <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                      <p className="text-xs text-[var(--muted)]">{t("reports.actualCash")}</p>
                      <p className="text-lg font-bold">{report.cash_sessions.actual_cash !== null ? formatCurrency(report.cash_sessions.actual_cash) : "—"}</p>
                    </div>
                    <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                      <p className="text-xs text-[var(--muted)]">{t("reports.difference")}</p>
                      <p className={`text-lg font-bold ${report.cash_sessions.difference !== null && report.cash_sessions.difference < 0 ? "text-[var(--color-danger)]" : ""}`}>
                        {report.cash_sessions.difference !== null ? formatCurrency(report.cash_sessions.difference) : "—"}
                      </p>
                    </div>
                  </div>
                  {report.cash_sessions.sessions.length > 0 && (
                    <Table>
                      <TableHeader><TableRow><TableHead>{t("reports.register")}</TableHead><TableHead>{t("reports.cashier")}</TableHead><TableHead className="text-end">{t("reports.status")}</TableHead><TableHead className="text-end">{t("reports.difference")}</TableHead></TableRow></TableHeader>
                      <TableBody>
                        {report.cash_sessions.sessions.map((s) => (
                          <TableRow key={s.id}><TableCell className="font-medium">{s.register}</TableCell><TableCell>{s.cashier ?? "—"}</TableCell><TableCell className="text-end text-xs text-[var(--muted)]">{s.status}</TableCell><TableCell className="text-end">{s.difference !== null ? formatCurrency(s.difference) : "—"}</TableCell></TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          <div className="grid gap-4 sm:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>{t("reports.discounts")}</CardTitle></CardHeader>
              <CardContent className="space-y-1 text-sm">
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.lineDiscounts")}</span><span>{formatCurrency(report.discounts.line)}</span></div>
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.saleDiscounts")}</span><span>{formatCurrency(report.discounts.sale)}</span></div>
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.couponDiscounts")}</span><span>{formatCurrency(report.discounts.coupon)}</span></div>
                <div className="flex justify-between font-medium border-t border-[var(--border)] pt-1"><span>{t("reports.totalDiscounts")}</span><span>{formatCurrency(report.discounts.total)}</span></div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>{t("reports.corrections")}</CardTitle></CardHeader>
              <CardContent className="space-y-1 text-sm">
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.voidedSales")} ({report.voids.count})</span><span>{formatCurrency(report.voids.amount)}</span></div>
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.returns")} ({report.returns.count})</span><span>{formatCurrency(report.returns.amount)}</span></div>
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.firstInvoice")}</span><span className="font-mono text-xs">{report.invoices.first ?? "—"}</span></div>
                <div className="flex justify-between"><span className="text-[var(--muted)]">{t("reports.lastInvoice")}</span><span className="font-mono text-xs">{report.invoices.last ?? "—"}</span></div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}

const REPORTS: { id: ReportType; label: string }[] = [
  { id: "sales-summary", label: "Sales summary" },
  { id: "sales-by-product", label: "Sales by product" },
  { id: "profit", label: "Profit & loss" },
  { id: "inventory-valuation", label: "Inventory valuation" },
  { id: "low-stock", label: "Low stock" },
  { id: "dues", label: "Customer dues" },
  { id: "z-report", label: "Z-Report" },
  { id: "profit-loss", label: "P&L Statement" },
  { id: "balance-sheet", label: "Balance Sheet" },
];

function ReportContent({ report, data }: { report: ReportType; data: Record<string, unknown> | null }) {
  const t = useTranslation();
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

  if (report === "shifts") {
    const s = data as { rows?: { user_name: string; period: string; shifts: number; hours: number; overtime_hours: number; is_overtime: boolean }[]; by_user?: { user_name: string; hours: number; overtime_hours: number; is_overtime: boolean }[]; totals?: { shifts: number; hours: number; overtime_hours: number } };
    return (
      <div className="space-y-4">
        {s.totals && (
          <div className="grid gap-4 sm:grid-cols-3">
            <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">Total shifts</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{s.totals.shifts}</p></CardContent></Card>
            <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">Total hours</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{s.totals.hours.toFixed(1)}h</p></CardContent></Card>
            <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">Overtime hours</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold text-amber-600">{s.totals.overtime_hours.toFixed(1)}h</p></CardContent></Card>
          </div>
        )}
        {s.by_user && s.by_user.length > 0 && (
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
            <Table>
              <TableHeader><TableRow><TableHead>Employee</TableHead><TableHead className="text-end">Hours</TableHead><TableHead className="text-end">Overtime</TableHead></TableRow></TableHeader>
              <TableBody>
                {s.by_user.map((u, i) => (
                  <TableRow key={i}>
                    <TableCell className="font-medium">{u.user_name}</TableCell>
                    <TableCell className="text-end">{u.hours.toFixed(1)}h</TableCell>
                    <TableCell className="text-end">{u.overtime_hours > 0 ? <Badge tone="warning">{u.overtime_hours.toFixed(1)}h</Badge> : "—"}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
        {s.rows && s.rows.length > 0 && (
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
            <h3 className="mb-2 text-sm font-medium">Daily breakdown</h3>
            <div className="rounded-[var(--radius)] border border-[var(--border)]">
              <Table>
                <TableHeader><TableRow><TableHead>Employee</TableHead><TableHead>Date</TableHead><TableHead className="text-end">Shifts</TableHead><TableHead className="text-end">Hours</TableHead><TableHead className="text-end">Overtime</TableHead></TableRow></TableHeader>
                <TableBody>
                  {s.rows.map((r, i) => (
                    <TableRow key={i}>
                      <TableCell>{r.user_name}</TableCell>
                      <TableCell className="text-sm">{r.period}</TableCell>
                      <TableCell className="text-end">{r.shifts}</TableCell>
                      <TableCell className="text-end">{r.hours.toFixed(1)}h</TableCell>
                      <TableCell className="text-end">{r.is_overtime ? <Badge tone="warning">{r.overtime_hours.toFixed(1)}h</Badge> : "—"}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>
        )}
        {(!s.rows || s.rows.length === 0) && <div className="py-12 text-center text-[var(--muted)]">No shift data for this period.</div>}
      </div>
    );
  }

  if (report === "profit-loss") {
    const pl = data as { sections?: { name: string; accounts: { name: string; amount: number }[]; total: number }[]; revenue_total: number; cogs_total: number; gross_profit: number; expenses_total: number; net_profit: number };
    return (
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.revenue")}</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold text-green-600">{formatCurrency(pl.revenue_total ?? 0)}</p></CardContent></Card>
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.grossProfit")}</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(pl.gross_profit ?? 0)}</p></CardContent></Card>
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.netProfit")}</CardTitle></CardHeader><CardContent><p className={`text-2xl font-bold ${(pl.net_profit ?? 0) >= 0 ? "text-green-600" : "text-[var(--color-danger)]"}`}>{formatCurrency(pl.net_profit ?? 0)}</p></CardContent></Card>
        </div>
        {pl.sections?.map((section) => (
          <Card key={section.name}>
            <CardHeader><CardTitle>{section.name}</CardTitle></CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader><TableRow><TableHead>{t("reports.method")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                <TableBody>
                  {section.accounts.map((a) => (
                    <TableRow key={a.name}><TableCell className="font-medium">{a.name}</TableCell><TableCell className="text-end">{formatCurrency(a.amount)}</TableCell></TableRow>
                  ))}
                  <TableRow><TableCell className="font-medium border-t border-[var(--border)]">{section.name} {t("reports.amount")}</TableCell><TableCell className="text-end font-bold border-t border-[var(--border)]">{formatCurrency(section.total)}</TableCell></TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  if (report === "balance-sheet") {
    const bs = data as { assets?: { name: string; amount: number }[]; liabilities?: { name: string; amount: number }[]; equity?: { name: string; amount: number }[]; assets_total: number; liabilities_total: number; equity_total: number; retained_earnings: number; is_balanced: boolean };
    return (
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-3">
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.totalAssets")}</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(bs.assets_total ?? 0)}</p></CardContent></Card>
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.totalLiabilities")}</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(bs.liabilities_total ?? 0)}</p></CardContent></Card>
          <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">{t("reports.totalEquity")}</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(bs.equity_total ?? 0)}</p></CardContent></Card>
        </div>
        <Card>
          <CardContent className="flex items-center gap-2">
            <Badge tone={bs.is_balanced ? "success" : "danger"}>{t("reports.balanceCheck")}: {bs.is_balanced ? t("reports.balanced") : t("reports.unbalanced")}</Badge>
          </CardContent>
        </Card>
        <div className="grid gap-4 lg:grid-cols-2">
          {bs.assets && bs.assets.length > 0 && (
            <Card>
              <CardHeader><CardTitle>{t("reports.assets")}</CardTitle></CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader><TableRow><TableHead>{t("reports.method")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {bs.assets.map((a) => (
                      <TableRow key={a.name}><TableCell className="font-medium">{a.name}</TableCell><TableCell className="text-end">{formatCurrency(a.amount)}</TableCell></TableRow>
                    ))}
                    <TableRow><TableCell className="font-medium border-t border-[var(--border)]">{t("reports.totalAssets")}</TableCell><TableCell className="text-end font-bold border-t border-[var(--border)]">{formatCurrency(bs.assets_total)}</TableCell></TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}
          <div className="space-y-4">
            {bs.liabilities && bs.liabilities.length > 0 && (
              <Card>
                <CardHeader><CardTitle>{t("reports.liabilities")}</CardTitle></CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader><TableRow><TableHead>{t("reports.method")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {bs.liabilities.map((l) => (
                        <TableRow key={l.name}><TableCell className="font-medium">{l.name}</TableCell><TableCell className="text-end">{formatCurrency(l.amount)}</TableCell></TableRow>
                      ))}
                      <TableRow><TableCell className="font-medium border-t border-[var(--border)]">{t("reports.totalLiabilities")}</TableCell><TableCell className="text-end font-bold border-t border-[var(--border)]">{formatCurrency(bs.liabilities_total)}</TableCell></TableRow>
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            )}
            {bs.equity && bs.equity.length > 0 && (
              <Card>
                <CardHeader><CardTitle>{t("reports.equity")}</CardTitle></CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader><TableRow><TableHead>{t("reports.method")}</TableHead><TableHead className="text-end">{t("reports.amount")}</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {bs.equity.map((e) => (
                        <TableRow key={e.name}><TableCell className="font-medium">{e.name}</TableCell><TableCell className="text-end">{formatCurrency(e.amount)}</TableCell></TableRow>
                      ))}
                      <TableRow><TableCell className="font-medium">{t("reports.retainedEarnings")}</TableCell><TableCell className="text-end">{formatCurrency(bs.retained_earnings ?? 0)}</TableCell></TableRow>
                      <TableRow><TableCell className="font-medium border-t border-[var(--border)]">{t("reports.totalEquity")}</TableCell><TableCell className="text-end font-bold border-t border-[var(--border)]">{formatCurrency(bs.equity_total)}</TableCell></TableRow>
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    );
  }

  return <pre className="text-xs overflow-auto">{JSON.stringify(data, null, 2)}</pre>;
}
