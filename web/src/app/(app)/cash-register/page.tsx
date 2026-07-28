"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, ApiError } from "@/lib/api";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Register {
  id: number;
  name: string;
  branch_id: number;
  is_active: boolean;
}

interface Session {
  id: number;
  status: "open" | "closed";
  opening_balance: number;
  expected_balance: number | null;
  actual_balance: number | null;
  difference: number | null;
  opened_at: string;
  closed_at: string | null;
}

export default function CashRegisterPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const [openingAmount, setOpeningAmount] = useState("0");
  const [closeAmount, setCloseAmount] = useState("0");
  const [movementAmount, setMovementAmount] = useState("0");
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

  const openMutation = useMutation({
    mutationFn: () => api(`/cash-registers/${register?.id}/open`, { method: "POST", body: { opening_balance: parseFloat(openingAmount) } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cash-session"] }); toast("Register opened"); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const closeMutation = useMutation({
    mutationFn: () => api(`/cash-registers/${register?.id}/sessions/${session?.id}/close`, { method: "POST", body: { actual_balance: parseFloat(closeAmount) } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cash-session"] }); toast("Register closed"); setShowClose(false); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const movementMutation = useMutation({
    mutationFn: () => api(`/cash-registers/${register?.id}/sessions/${session?.id}/movements`, { method: "POST", body: { type: movementType, amount: parseFloat(movementAmount), note: movementNote } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["cash-session"] }); toast("Movement recorded"); setMovementAmount("0"); setMovementNote(""); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  if (!register) {
    return <div className="py-12 text-center text-[var(--muted)]">No cash register configured for your branch.</div>;
  }

  return (
    <div className="max-w-2xl space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Cash Register</h1>
        {session && <Badge tone={session.status === "open" ? "success" : "default"} className="text-sm px-3 py-1">{session.status}</Badge>}
      </div>

      {!session || session.status === "closed" ? (
        <Card>
          <CardHeader><CardTitle>Open register</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div><Label>Opening balance</Label><Input type="number" step="0.01" min="0" value={openingAmount} onChange={(e) => setOpeningAmount(e.target.value)} /></div>
            <Button onClick={() => openMutation.mutate()} disabled={openMutation.isPending} className="w-full">{openMutation.isPending ? "Opening…" : "Open register"}</Button>
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4">
            <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">Opening balance</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(session.opening_balance)}</p></CardContent></Card>
            {session.expected_balance != null && <Card><CardHeader><CardTitle className="text-sm text-[var(--muted)]">Expected balance</CardTitle></CardHeader><CardContent><p className="text-2xl font-bold">{formatCurrency(session.expected_balance)}</p></CardContent></Card>}
          </div>

          <Card>
            <CardHeader><CardTitle>Cash movement</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <Label>Type</Label>
                  <Select value={movementType} onChange={(e) => setMovementType(e.target.value as "in" | "out")}>
                    <option value="in">Cash in</option>
                    <option value="out">Cash out</option>
                  </Select>
                </div>
                <div>
                  <Label>Amount</Label>
                  <Input type="number" step="0.01" min="0" value={movementAmount} onChange={(e) => setMovementAmount(e.target.value)} />
                </div>
                <div>
                  <Label>Note</Label>
                  <Input value={movementNote} onChange={(e) => setMovementNote(e.target.value)} placeholder="Optional" />
                </div>
              </div>
              <Button variant="secondary" onClick={() => movementMutation.mutate()} disabled={movementMutation.isPending}>Record movement</Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle>Close register</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <div><Label>Actual cash on hand</Label><Input type="number" step="0.01" min="0" value={closeAmount} onChange={(e) => setCloseAmount(e.target.value)} /></div>
              <Button variant="danger" onClick={() => setShowClose(true)}>Close register</Button>
            </CardContent>
          </Card>
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
