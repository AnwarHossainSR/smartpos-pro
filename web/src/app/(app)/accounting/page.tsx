"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, ChevronLeft, ChevronRight } from "lucide-react";
import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Account {
  id: number;
  name: string;
  code: string;
  type: string;
  normal_side: string;
  balance: number;
  is_active: boolean;
}

interface JournalEntry {
  id: number;
  entry_number: string;
  description: string;
  total_debit: number;
  posted_at: string;
  lines?: { account: { name: string }; direction: string; amount: number }[];
}

type Tab = "accounts" | "journals" | "trial-balance";

const journalSchema = z.object({
  description: z.string().min(1, "Description required"),
  posted_at: z.string().min(1),
  lines: z.array(z.object({
    account_id: z.number(),
    direction: z.enum(["debit", "credit"]),
    amount: z.number().min(0.01),
  })).min(2, "At least 2 lines required"),
});
type JournalValues = z.infer<typeof journalSchema>;

function JournalForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { data: accounts } = useQuery({ queryKey: ["accounts-all"], queryFn: () => api<Paginated<Account>>("/accounts?per_page=200") });

  const { register, handleSubmit, control, formState: { errors }, watch } = useForm<JournalValues>({
    resolver: zodResolver(journalSchema),
    defaultValues: {
      posted_at: new Date().toISOString().slice(0, 10),
      lines: [{ account_id: 0, direction: "debit", amount: 0 }, { account_id: 0, direction: "credit", amount: 0 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const lines = watch("lines");
  const debitTotal = lines.reduce((s, l) => s + (l.direction === "debit" ? (l.amount || 0) : 0), 0);
  const creditTotal = lines.reduce((s, l) => s + (l.direction === "credit" ? (l.amount || 0) : 0), 0);
  const balanced = Math.abs(debitTotal - creditTotal) < 0.001;

  const mutation = useMutation({
    mutationFn: (data: JournalValues) => api("/journal-entries", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["journals"] }); qc.invalidateQueries({ queryKey: ["accounts"] }); toast("Journal posted"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div><Label>Description *</Label><Input {...register("description")} /></div>
      <div><Label>Date *</Label><Input type="date" {...register("posted_at")} /></div>

      <div>
        <div className="mb-2 flex items-center justify-between">
          <Label>Lines *</Label>
          <Button type="button" variant="outline" size="sm" onClick={() => append({ account_id: 0, direction: "debit", amount: 0 })}>+ Add line</Button>
        </div>
        <div className="space-y-2">
          {fields.map((field, idx) => (
            <div key={field.id} className="flex items-center gap-2">
              <Select className="flex-1" {...register(`lines.${idx}.account_id`, { setValueAs: (v) => v ? Number(v) : 0 })}>
                <option value="">— Account —</option>
                {accounts?.data?.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
              </Select>
              <Select className="w-24" {...register(`lines.${idx}.direction`)}>
                <option value="debit">Debit</option>
                <option value="credit">Credit</option>
              </Select>
              <Input className="w-28" type="number" step="0.01" min="0" {...register(`lines.${idx}.amount`, { valueAsNumber: true })} />
              {fields.length > 2 && <Button type="button" variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={() => remove(idx)}>×</Button>}
            </div>
          ))}
        </div>
        <div className="mt-2 flex justify-between text-sm">
          <span>Debit: <strong>{formatCurrency(debitTotal)}</strong></span>
          <span>Credit: <strong>{formatCurrency(creditTotal)}</strong></span>
          {!balanced && <span className="text-[var(--color-danger)]">Not balanced</span>}
          {balanced && debitTotal > 0 && <span className="text-green-600">Balanced</span>}
        </div>
      </div>

      <Button type="submit" disabled={mutation.isPending || !balanced} className="w-full">{mutation.isPending ? "Posting…" : "Post journal"}</Button>
    </form>
  );
}

export default function AccountingPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("accounts");
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const { data: accounts, isLoading: accLoading } = useQuery<Paginated<Account>>({
    queryKey: ["accounts", page],
    queryFn: () => api<Paginated<Account>>(`/accounts?page=${page}&per_page=25`),
    enabled: tab === "accounts",
  });

  const { data: journals, isLoading: jrnLoading } = useQuery<Paginated<JournalEntry>>({
    queryKey: ["journals", page],
    queryFn: () => api<Paginated<JournalEntry>>(`/journal-entries?page=${page}&per_page=20`),
    enabled: tab === "journals",
  });

  const { data: trialBalance } = useQuery({
    queryKey: ["trial-balance"],
    queryFn: () => api<{ data: { accounts: Account[]; total_debit: number; total_credit: number } }>("/accounts/trial-balance"),
    enabled: tab === "trial-balance",
  });

  const typeColors: Record<string, "success" | "warning" | "info" | "default"> = {
    asset: "success", liability: "warning", equity: "info", revenue: "success", expense: "default",
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Accounting</h1>
        {tab === "journals" && can("accounting.create") && (
          <Button size="sm" onClick={() => setDrawerOpen(true)}><Plus className="h-4 w-4" />Post journal</Button>
        )}
      </div>

      <div className="flex gap-2">
        {(["accounts", "journals", "trial-balance"] as Tab[]).map((t) => (
          <Button key={t} size="sm" variant={tab === t ? "primary" : "outline"} onClick={() => { setTab(t); setPage(1); }}>
            {t === "accounts" ? "Chart of accounts" : t === "journals" ? "Journal entries" : "Trial balance"}
          </Button>
        ))}
      </div>

      {tab === "accounts" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Code</TableHead><TableHead>Name</TableHead><TableHead>Type</TableHead><TableHead className="text-end">Balance</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
              {accLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 5 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
                : accounts?.data?.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-mono text-xs">{a.code}</TableCell>
                    <TableCell className="font-medium">{a.name}</TableCell>
                    <TableCell><Badge tone={typeColors[a.type] ?? "default"}>{a.type}</Badge></TableCell>
                    <TableCell className="text-end">{formatCurrency(a.balance)}</TableCell>
                    <TableCell><Badge tone={a.is_active ? "success" : "default"}>{a.is_active ? "Active" : "Inactive"}</Badge></TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </div>
      )}

      {tab === "journals" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Number</TableHead><TableHead>Description</TableHead><TableHead>Date</TableHead><TableHead className="text-end">Amount</TableHead></TableRow></TableHeader>
            <TableBody>
              {jrnLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 4 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
                : journals?.data?.length === 0 ? <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--muted)]">No journal entries yet.</TableCell></TableRow>
                : journals?.data?.map((j) => (
                  <TableRow key={j.id}>
                    <TableCell className="font-mono text-xs">{j.entry_number}</TableCell>
                    <TableCell>{j.description}</TableCell>
                    <TableCell className="text-[var(--muted)]">{new Date(j.posted_at).toLocaleDateString()}</TableCell>
                    <TableCell className="text-end">{formatCurrency(j.total_debit)}</TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
          {journals?.meta && journals.meta.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
              <p className="text-sm text-[var(--muted)]">Page {journals.meta.current_page} of {journals.meta.last_page}</p>
              <div className="flex gap-1">
                <Button variant="outline" size="icon" className="h-8 w-8" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}><ChevronLeft className="h-4 w-4 rtl:scale-x-[-1]" /></Button>
                <Button variant="outline" size="icon" className="h-8 w-8" disabled={page >= (journals.meta.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}><ChevronRight className="h-4 w-4 rtl:scale-x-[-1]" /></Button>
              </div>
            </div>
          )}
        </div>
      )}

      {tab === "trial-balance" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Account</TableHead><TableHead>Type</TableHead><TableHead className="text-end">Debit</TableHead><TableHead className="text-end">Credit</TableHead></TableRow></TableHeader>
            <TableBody>
              {trialBalance?.data?.accounts?.map((a) => (
                <TableRow key={a.id}>
                  <TableCell className="font-medium">{a.code} {a.name}</TableCell>
                  <TableCell><Badge tone={typeColors[a.type] ?? "default"}>{a.type}</Badge></TableCell>
                  <TableCell className="text-end">{a.normal_side === "debit" ? formatCurrency(a.balance) : "—"}</TableCell>
                  <TableCell className="text-end">{a.normal_side === "credit" ? formatCurrency(a.balance) : "—"}</TableCell>
                </TableRow>
              ))}
              <TableRow className="font-semibold bg-[var(--surface-2)]">
                <TableCell colSpan={2}>Total</TableCell>
                <TableCell className="text-end">{formatCurrency(trialBalance?.data?.total_debit ?? 0)}</TableCell>
                <TableCell className="text-end">{formatCurrency(trialBalance?.data?.total_credit ?? 0)}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      )}

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="Post journal entry" width="max-w-2xl">
        <JournalForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
