import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  FileText, Wallet, Lock, ArrowDownCircle, ArrowUpCircle, Clock, Download, Loader2, Printer,
} from "lucide-react";
import { api, apiBlob, ApiError } from "@/lib/api";
import { cn, downloadBlob, formatCurrency, formatDateTime, openPdfBlob } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatCard } from "@/components/stat-card";
import { Dialog } from "@/components/ui/dialog";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";
import { useAuth } from "@/stores/auth";

interface Register {
  id: string;
  name: string;
  branch_id: string;
  is_active: boolean;
}

interface Session {
  id: string;
  status: "open" | "closed";
  opening_cash: number;
  cash_in: number;
  cash_out: number;
  expected_cash: number;
  actual_cash: number | null;
  difference: number | null;
  opened_at: string;
  closed_at: string | null;
}

interface Movement {
  type: "in" | "out";
  amount: number;
  reason: string | null;
  created_at: string;
}

export default function CashRegisterPage() {
  const qc = useQueryClient();
  const t = useTranslation();
  const { can } = useAuth();
  const [openingAmount, setOpeningAmount] = useState("0");
  const [closeAmount, setCloseAmount] = useState("0");
  const [movementAmount, setMovementAmount] = useState("");
  const [movementType, setMovementType] = useState<"in" | "out">("in");
  const [movementNote, setMovementNote] = useState("");
  const [showClose, setShowClose] = useState(false);

  const { data: registers } = useQuery<{ data: Register[] }>({
    queryKey: ["registers"],
    queryFn: () => api<{ data: Register[] }>("/cash-registers?per_page=10"),
  });

  const register = registers?.data?.[0];

  const { data: sessionData, isLoading } = useQuery<{ data: Session | null }>({
    queryKey: ["cash-session", register?.id],
    queryFn: () => api<{ data: Session | null }>(`/cash-registers/${register?.id}/current-session`),
    enabled: !!register?.id,
  });

  const session = sessionData?.data;
  const [lastClosed, setLastClosed] = useState<Session | null>(null);
  const [loadingSlip, setLoadingSlip] = useState(false);
  const [downloadingSlip, setDownloadingSlip] = useState(false);

  const { data: detailData } = useQuery<{ data: Session & { transactions?: Movement[] } }>({
    queryKey: ["cash-session-detail", session?.id],
    queryFn: () => api(`/cash-register-sessions/${session?.id}`),
    enabled: !!session?.id,
  });

  const movements = detailData?.data?.transactions ?? [];

  const openMutation = useMutation({
    mutationFn: () => api(`/cash-registers/${register?.id}/open`, { method: "POST", body: { opening_cash: parseFloat(openingAmount) } }),
    onSuccess: () => { setLastClosed(null); qc.invalidateQueries({ queryKey: ["cash-session"] }); toast("Register opened"); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const closeMutation = useMutation({
    mutationFn: () => api<{ data: Session }>(`/cash-register-sessions/${session?.id}/close`, { method: "POST", body: { actual_cash: parseFloat(closeAmount) } }),
    onSuccess: ({ data }) => {
      setLastClosed(data);
      qc.invalidateQueries({ queryKey: ["cash-session"] });
      toast("Register closed");
      setShowClose(false);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const movementMutation = useMutation({
    mutationFn: () => api(`/cash-register-sessions/${session?.id}/movement`, { method: "POST", body: { type: movementType, amount: parseFloat(movementAmount), reason: movementNote } }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-session"] });
      qc.invalidateQueries({ queryKey: ["cash-session-detail"] });
      toast("Movement recorded");
      setMovementAmount("");
      setMovementNote("");
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const slipSessionId = session?.id ?? lastClosed?.id;

  const viewSlip = async () => {
    if (!slipSessionId) return;
    setLoadingSlip(true);
    try {
      openPdfBlob(await apiBlob(`/cash-register-sessions/${slipSessionId}/slip`));
    } catch {
      toast("Could not load slip", "error");
    } finally {
      setLoadingSlip(false);
    }
  };

  /** Filename mirrors the one the backend sets on `?download=1` — Content-Disposition is lost
   *  once the response is read as a blob, so it has to be re-applied client-side. */
  const downloadSlip = async () => {
    if (!slipSessionId) return;
    setDownloadingSlip(true);
    try {
      const blob = await apiBlob(`/cash-register-sessions/${slipSessionId}/slip?download=1`);
      downloadBlob(blob, `cash-slip-${slipSessionId}.pdf`);
    } catch {
      toast(t("actions.downloadSlipFailed"), "error");
    } finally {
      setDownloadingSlip(false);
    }
  };

  const closeDiff = parseFloat(closeAmount || "0") - (session?.expected_cash ?? 0);

  if (!register) {
    return (
      <div className="flex flex-col items-center justify-center gap-2 py-24 text-center">
        <Wallet className="h-8 w-8 text-[var(--muted)]" />
        <p className="text-sm text-[var(--muted)]">No cash register configured for your branch.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-[var(--muted)]">{register.name}</p>
        </div>
        <div className="flex items-center gap-2">
          {slipSessionId && can("accounting.view") && (
            <>
              <Button variant="outline" size="sm" onClick={viewSlip} disabled={loadingSlip}>
                <FileText className="h-3.5 w-3.5" /> {loadingSlip ? "Loading…" : "Print slip"}
              </Button>
              <Button variant="outline" size="sm" onClick={downloadSlip} disabled={downloadingSlip}>
                {downloadingSlip
                  ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  : <Download className="h-3.5 w-3.5" />}
                {downloadingSlip ? t("actions.downloading") : t("actions.downloadSlip")}
              </Button>
            </>
          )}
          {session && <Badge tone={session.status === "open" ? "success" : "default"} className="px-3 py-1 text-sm">{session.status}</Badge>}
        </div>
      </div>

      {isLoading ? (
        <Card className="h-40 animate-pulse" />
      ) : !session ? (
        <div className="space-y-5">
          {lastClosed && (
            <Card>
              <CardHeader><CardTitle>Last closed session</CardTitle></CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 gap-4">
                  <div><p className="text-xs text-[var(--muted)]">Actual counted</p><p className="text-xl font-bold">{formatCurrency(lastClosed.actual_cash ?? 0)}</p></div>
                  <div>
                    <p className="text-xs text-[var(--muted)]">Difference</p>
                    <p className={cn("text-xl font-bold", (lastClosed.difference ?? 0) < 0 ? "text-[var(--color-danger)]" : "text-[var(--color-success)]")}>
                      {formatCurrency(lastClosed.difference ?? 0)}
                    </p>
                  </div>
                </div>
                {can("reports.view") && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-3"
                    onClick={() => {
                      const d = lastClosed.closed_at ? new Date(lastClosed.closed_at).toISOString().slice(0, 10) : new Date().toISOString().slice(0, 10);
                      window.open(`/api/v1/reports/z-report/pdf?date=${d}&download=1`, "_blank");
                    }}
                  >
                    <Printer className="h-3.5 w-3.5" /> {t("cashRegister.printZReport") ?? "Print Z-report"}
                  </Button>
                )}
              </CardContent>
            </Card>
          )}

          {/* Empty / open state — a focused hero rather than a plain form, since this is the
              first thing a cashier sees at the start of every shift. */}
          <Card className="mx-auto max-w-sm">
            <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
              <div className="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--primary)]/10 text-[var(--primary)]">
                <Wallet className="h-6 w-6" />
              </div>
              <div>
                <p className="font-semibold">Register is closed</p>
                <p className="mt-1 text-sm text-[var(--muted)]">Count the opening float and start a new session.</p>
              </div>
              <div className="w-full text-start">
                <Label>Opening balance</Label>
                <Input type="number" step="0.01" min="0" value={openingAmount} onChange={(e) => setOpeningAmount(e.target.value)} autoFocus />
              </div>
              <Button onClick={() => openMutation.mutate()} disabled={openMutation.isPending} className="w-full">
                {openMutation.isPending ? "Opening…" : "Open register"}
              </Button>
            </CardContent>
          </Card>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="Opening balance" value={formatCurrency(session.opening_cash)} />
            <StatCard label="Cash in" value={`+${formatCurrency(session.cash_in)}`} tone="success" />
            <StatCard label="Cash out" value={`-${formatCurrency(session.cash_out)}`} tone="danger" />
            <StatCard label="Expected balance" value={formatCurrency(session.expected_cash)} />
          </div>

          <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
            {/* Cash movement ledger */}
            <Card className="lg:col-span-2">
              <CardHeader><CardTitle>Cash movements</CardTitle></CardHeader>
              <CardContent className="pt-4">
                {movements.length === 0 ? (
                  <div className="flex flex-col items-center gap-2 py-10 text-center text-sm text-[var(--muted)]">
                    <Clock className="h-5 w-5" />
                    No cash movements recorded yet this session.
                  </div>
                ) : (
                  <div className="space-y-1">
                    {movements.map((m, i) => (
                      <div key={i} className="flex items-center gap-3 rounded-[var(--radius)] px-2 py-2.5 transition-colors hover:bg-[var(--surface-2)]">
                        <div className={cn(
                          "flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
                          m.type === "in" ? "bg-green-500/10 text-green-600 dark:text-green-400" : "bg-red-500/10 text-red-600 dark:text-red-400",
                        )}>
                          {m.type === "in" ? <ArrowDownCircle className="h-4 w-4" /> : <ArrowUpCircle className="h-4 w-4" />}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{m.reason || (m.type === "in" ? "Cash in" : "Cash out")}</p>
                          <p className="text-xs text-[var(--muted)]">{formatDateTime(m.created_at)}</p>
                        </div>
                        <span className={cn("shrink-0 text-sm font-semibold", m.type === "in" ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400")}>
                          {m.type === "in" ? "+" : "-"}{formatCurrency(m.amount)}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Actions */}
            <div className="space-y-5">
              <Card>
                <CardHeader><CardTitle>Record movement</CardTitle></CardHeader>
                <CardContent className="space-y-3 pt-4">
                  <div className="grid grid-cols-2 gap-1 rounded-[var(--radius)] bg-[var(--surface-2)] p-1">
                    <button
                      type="button"
                      onClick={() => setMovementType("in")}
                      className={cn(
                        "flex cursor-pointer items-center justify-center gap-1.5 rounded-[calc(var(--radius)-4px)] py-2 text-sm font-medium transition-colors",
                        movementType === "in" ? "bg-[var(--surface)] text-green-600 shadow-sm dark:text-green-400" : "text-[var(--muted)] hover:text-[var(--fg)]",
                      )}
                    >
                      <ArrowDownCircle className="h-4 w-4" /> Cash in
                    </button>
                    <button
                      type="button"
                      onClick={() => setMovementType("out")}
                      className={cn(
                        "flex cursor-pointer items-center justify-center gap-1.5 rounded-[calc(var(--radius)-4px)] py-2 text-sm font-medium transition-colors",
                        movementType === "out" ? "bg-[var(--surface)] text-red-600 shadow-sm dark:text-red-400" : "text-[var(--muted)] hover:text-[var(--fg)]",
                      )}
                    >
                      <ArrowUpCircle className="h-4 w-4" /> Cash out
                    </button>
                  </div>
                  <div>
                    <Label>Amount</Label>
                    <Input type="number" step="0.01" min="0" placeholder="0.00" value={movementAmount} onChange={(e) => setMovementAmount(e.target.value)} />
                  </div>
                  <div>
                    <Label>Note</Label>
                    <Input value={movementNote} onChange={(e) => setMovementNote(e.target.value)} placeholder="Optional" />
                  </div>
                  <Button
                    variant="secondary"
                    className="w-full"
                    onClick={() => movementMutation.mutate()}
                    disabled={movementMutation.isPending || !movementAmount || parseFloat(movementAmount) <= 0}
                  >
                    {movementMutation.isPending ? "Recording…" : "Record movement"}
                  </Button>
                </CardContent>
              </Card>

              <Card>
                <CardHeader><CardTitle>Close register</CardTitle></CardHeader>
                <CardContent className="space-y-3 pt-4">
                  <div>
                    <Label>Actual cash on hand</Label>
                    <Input type="number" step="0.01" min="0" value={closeAmount} onChange={(e) => setCloseAmount(e.target.value)} />
                  </div>
                  {closeAmount !== "" && (
                    <div className="flex items-center justify-between rounded-[var(--radius)] bg-[var(--surface-2)] px-3 py-2 text-sm">
                      <span className="text-[var(--muted)]">Difference</span>
                      <span className={cn("font-semibold", closeDiff < 0 ? "text-[var(--color-danger)]" : closeDiff > 0 ? "text-[var(--color-success)]" : "")}>
                        {closeDiff >= 0 ? "+" : ""}{formatCurrency(closeDiff)}
                      </span>
                    </div>
                  )}
                  <Button variant="danger" className="w-full" onClick={() => setShowClose(true)}>
                    <Lock className="h-3.5 w-3.5" /> Close register
                  </Button>
                </CardContent>
              </Card>
            </div>
          </div>
        </>
      )}

      <Dialog
        open={showClose}
        onClose={() => setShowClose(false)}
        title="Close register"
        description={`Close with actual balance of ${formatCurrency(parseFloat(closeAmount || "0"))}?`}
        confirmLabel="Close"
        confirmVariant="danger"
        onConfirm={() => closeMutation.mutate()}
        loading={closeMutation.isPending}
      />
    </div>
  );
}
