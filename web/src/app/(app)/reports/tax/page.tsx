import { Suspense, lazy, useMemo, useState } from "react";
import { Link } from "react-router";
import { ArrowLeft, Printer } from "lucide-react";
import { useBranches } from "@/lib/lookups";
import { cn, formatCurrency } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { useTaxSummaryReport, useTaxReturnReport } from "@/features/tax/hooks";
import type { TaxReportComponent } from "@/features/tax/types";

const TaxReportChart = lazy(() => import("@/features/tax/components/tax-report-chart"));

function firstOfMonth(): string {
  const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10);
}
function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function ComponentTable({ rows, emptyLabel }: { rows: TaxReportComponent[]; emptyLabel: string }) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Component</TableHead>
          <TableHead className="text-end">Rate</TableHead>
          <TableHead className="text-end">Base</TableHead>
          <TableHead className="text-end">Amount</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.length === 0 ? (
          <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--muted)]">{emptyLabel}</TableCell></TableRow>
        ) : (
          rows.map((c, i) => (
            <TableRow key={`${c.name}-${i}`}>
              <TableCell className="font-medium">{c.name}</TableCell>
              <TableCell className="text-end text-[var(--muted)]">{c.rate}%</TableCell>
              <TableCell className="text-end">{formatCurrency(c.base_amount)}</TableCell>
              <TableCell className="text-end font-medium">{formatCurrency(c.amount)}</TableCell>
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );
}

type View = "summary" | "return";

function TaxReportBody() {
  const { can } = useAuth();
  const [from, setFrom] = useState(firstOfMonth);
  const [to, setTo] = useState(today);
  const [branchId, setBranchId] = useState<string>("");
  const [view, setView] = useState<View>("summary");

  const canView = can("taxes.view");

  // Branch filter is optional — only fetch the list if the user can actually see branches;
  // a 403 here shouldn't break the report itself.
  const { branches } = useBranches({ enabled: canView && can("branches.view") });

  const filters = useMemo(
    () => ({ from, to, branch_id: branchId ? branchId : null }),
    [from, to, branchId],
  );

  const summary = useTaxSummaryReport(filters, canView && view === "summary");
  const taxReturn = useTaxReturnReport(filters, canView && view === "return");

  const data = view === "summary" ? summary.data?.data : undefined;
  const returnData = view === "return" ? taxReturn.data?.data : undefined;
  const loading = view === "summary" ? summary.isLoading : taxReturn.isLoading;
  const active = view === "summary" ? data : returnData;

  if (!canView) {
    return <div className="py-16 text-center text-[var(--muted)]">You don&apos;t have permission to view tax reports.</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div className="flex items-center gap-2">
          <Link to="/reports" className="text-[var(--muted)] hover:text-[var(--fg)]">
            <ArrowLeft className="h-4 w-4" />
          </Link>
        </div>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <Label className="mb-0">From</Label>
          <Input type="date" className="h-9 w-36" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Label className="mb-0">To</Label>
          <Input type="date" className="h-9 w-36" value={to} onChange={(e) => setTo(e.target.value)} />
          {branches.length > 0 && (
            <Select className="h-9 w-40" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              <option value="">All branches</option>
              {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </Select>
          )}
        </div>
      </div>

      <div className="flex items-center justify-between print:hidden">
        <div className="flex gap-2">
          <Button size="sm" variant={view === "summary" ? "primary" : "outline"} onClick={() => setView("summary")}>
            Summary
          </Button>
          <Button size="sm" variant={view === "return" ? "primary" : "outline"} onClick={() => setView("return")}>
            Tax return (filing view)
          </Button>
        </div>
        {view === "return" && returnData && (
          <Button size="sm" variant="outline" onClick={() => window.print()}>
            <Printer className="h-4 w-4" /> Print
          </Button>
        )}
      </div>

      {loading ? (
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-[var(--radius)]" />)}
        </div>
      ) : !active ? (
        <div className="py-12 text-center text-[var(--muted)]">No data for this period.</div>
      ) : view === "summary" && data ? (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <Card>
              <CardHeader><CardTitle>Output tax (charged on sales)</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatCurrency(data.output_tax.total)}</p></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>Input tax (paid on purchases)</CardTitle></CardHeader>
              <CardContent><p className="text-2xl font-bold">{formatCurrency(data.input_tax.total)}</p></CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>Net payable</CardTitle></CardHeader>
              <CardContent>
                <p className={cn("text-2xl font-bold", data.net_payable >= 0 ? "text-[var(--color-danger)]" : "text-green-600")}>
                  {formatCurrency(data.net_payable)}
                </p>
                <p className="mt-1 text-xs text-[var(--muted)]">
                  {data.net_payable >= 0 ? "Owed to the tax authority" : "Reclaimable / carried forward"}
                </p>
              </CardContent>
            </Card>
          </div>

          {(data.output_tax.components.length > 0 || data.input_tax.components.length > 0) && (
            <Card>
              <CardHeader><CardTitle>Output vs input, by component</CardTitle></CardHeader>
              <CardContent className="h-64">
                <Suspense fallback={<Skeleton className="h-full w-full" />}>
                  <TaxReportChart output={data.output_tax.components} input={data.input_tax.components} />
                </Suspense>
              </CardContent>
            </Card>
          )}

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>Output tax components</CardTitle></CardHeader>
              <CardContent className="p-0">
                <ComponentTable rows={data.output_tax.components} emptyLabel="No output tax in this period." />
              </CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle>Input tax components</CardTitle></CardHeader>
              <CardContent className="p-0">
                <ComponentTable rows={data.input_tax.components} emptyLabel="No input tax in this period." />
              </CardContent>
            </Card>
          </div>
        </div>
      ) : returnData ? (
        <Card className="print:border-0 print:shadow-none">
          <CardContent className="space-y-5 p-6">
            <div className="flex flex-col items-start justify-between gap-2 border-b border-[var(--border)] pb-4 sm:flex-row">
              <div>
                <p className="text-lg font-semibold">{returnData.business.name ?? "—"}</p>
                <p className="text-sm text-[var(--muted)]">
                  {returnData.business.tax_number_label}: <span className="font-mono">{returnData.business.tax_number ?? "— not set —"}</span>
                </p>
              </div>
              <div className="text-start sm:text-end">
                <p className="text-sm font-medium">{returnData.profile.name ?? "Generic"} {returnData.profile.code ? `(${returnData.profile.code})` : ""}</p>
                <p className="text-xs text-[var(--muted)]">Period: {returnData.period.from} – {returnData.period.to}</p>
              </div>
            </div>

            {returnData.profile.required_invoice_fields.length > 0 && (
              <div>
                <p className="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--muted)]">Fields required on this filing</p>
                <div className="flex flex-wrap gap-1">
                  {returnData.profile.required_invoice_fields.map((f) => <Badge key={f}>{f}</Badge>)}
                </div>
              </div>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                <p className="text-xs text-[var(--muted)]">Output tax</p>
                <p className="text-xl font-bold">{formatCurrency(returnData.output_tax.total)}</p>
              </div>
              <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                <p className="text-xs text-[var(--muted)]">Input tax</p>
                <p className="text-xl font-bold">{formatCurrency(returnData.input_tax.total)}</p>
              </div>
              <div className="rounded-[var(--radius)] border border-[var(--border)] p-3">
                <p className="text-xs text-[var(--muted)]">Net payable</p>
                <p className="text-xl font-bold">{formatCurrency(returnData.net_payable)}</p>
              </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              <div>
                <p className="mb-1 text-sm font-medium">Output tax by component</p>
                <ComponentTable rows={returnData.output_tax.components} emptyLabel="None." />
              </div>
              <div>
                <p className="mb-1 text-sm font-medium">Input tax by component</p>
                <ComponentTable rows={returnData.input_tax.components} emptyLabel="None." />
              </div>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}

function TaxReportSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-7 w-40" />
      <div className="grid gap-4 md:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-[var(--radius)]" />)}
      </div>
    </div>
  );
}

export default function TaxReportPage() {
  return (
    <Suspense fallback={<TaxReportSkeleton />}>
      <TaxReportBody />
    </Suspense>
  );
}
