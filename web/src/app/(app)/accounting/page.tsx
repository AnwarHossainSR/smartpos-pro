import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import { useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { z } from "zod";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { formatCurrency, formatDate } from "@/lib/utils";
import { useAuth } from "@/stores/auth";

interface Account {
  id: string;
  name: string;
  account_number: string | null;
  type: string;
  normal_side: string;
  balance: number;
  is_active: boolean;
}

interface TrialBalanceRow {
  account_id: string;
  name: string;
  account_number: string | null;
  type: string;
  normal_side: string;
  debit: number;
  credit: number;
}

interface JournalEntry {
  id: string;
  reference: string;
  description: string;
  total_debit: number;
  entry_date: string;
  lines?: { account_id: string; type: string; amount: number }[];
}

type Tab = "accounts" | "journals" | "trial-balance";

const journalSchema = z.object({
  description: z.string().min(1, "Description required"),
  entry_date: z.string().min(1),
  lines: z
    .array(
      z.object({
        account_id: z.string(),
        type: z.enum(["debit", "credit"]),
        amount: z.number().min(0.01),
      }),
    )
    .min(2, "At least 2 lines required"),
});
type JournalValues = z.infer<typeof journalSchema>;

function JournalForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  // The picker's unpaginated fetch is a different shape from the paged Chart of accounts
  // table, so it gets its own cache entry rather than clobbering that one.
  const { data: accounts } = useQuery({
    queryKey: queryKeys.accounts.options(),
    queryFn: () => api<Paginated<Account>>("/accounts?per_page=200"),
  });

  const { register, handleSubmit, control, watch } = useForm<JournalValues>({
    resolver: zodResolver(journalSchema),
    defaultValues: {
      entry_date: new Date().toISOString().slice(0, 10),
      lines: [
        { account_id: "", type: "debit", amount: 0 },
        { account_id: "", type: "credit", amount: 0 },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const lines = watch("lines");
  const debitTotal = lines.reduce((s, l) => s + (l.type === "debit" ? l.amount || 0 : 0), 0);
  const creditTotal = lines.reduce((s, l) => s + (l.type === "credit" ? l.amount || 0 : 0), 0);
  const balanced = Math.abs(debitTotal - creditTotal) < 0.001;

  const mutation = useMutation({
    mutationFn: (data: JournalValues) => api("/journals", { method: "POST", body: data }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.journals.all });
      qc.invalidateQueries({ queryKey: queryKeys.accounts.all });
      // A posted journal moves account balances, so the trial balance is stale too.
      qc.invalidateQueries({ queryKey: queryKeys.trialBalance.all });
      toast("Journal posted");
      onSuccess();
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Description *</Label>
        <Input {...register("description")} />
      </div>
      <div>
        <Label>Date *</Label>
        <Input type="date" {...register("entry_date")} />
      </div>

      <div>
        <div className="mb-2 flex items-center justify-between">
          <Label>Lines *</Label>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => append({ account_id: "", type: "debit", amount: 0 })}
          >
            + Add line
          </Button>
        </div>
        <div className="space-y-2">
          {fields.map((field, idx) => (
            <div key={field.id} className="flex items-center gap-2">
              <Select
                className="flex-1"
                {...register(`lines.${idx}.account_id`, {
                  setValueAs: (v) => (v ? String(v) : ""),
                })}
              >
                <option value="">— Account —</option>
                {accounts?.data?.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.account_number} {a.name}
                  </option>
                ))}
              </Select>
              <Select className="w-24" {...register(`lines.${idx}.type`)}>
                <option value="debit">Debit</option>
                <option value="credit">Credit</option>
              </Select>
              <Input
                className="w-28"
                type="number"
                step="0.01"
                min="0"
                {...register(`lines.${idx}.amount`, { valueAsNumber: true })}
              />
              {fields.length > 2 && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 shrink-0"
                  onClick={() => remove(idx)}
                >
                  ×
                </Button>
              )}
            </div>
          ))}
        </div>
        <div className="mt-2 flex justify-between text-sm">
          <span>
            Debit: <strong>{formatCurrency(debitTotal)}</strong>
          </span>
          <span>
            Credit: <strong>{formatCurrency(creditTotal)}</strong>
          </span>
          {!balanced && <span className="text-[var(--color-danger)]">Not balanced</span>}
          {balanced && debitTotal > 0 && <span className="text-green-600">Balanced</span>}
        </div>
      </div>

      <Button type="submit" disabled={mutation.isPending || !balanced} className="w-full">
        {mutation.isPending ? "Posting…" : "Post journal"}
      </Button>
    </form>
  );
}

export default function AccountingPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("accounts");
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const { data: accounts, isLoading: accLoading } = useQuery<Paginated<Account>>({
    queryKey: queryKeys.accounts.list({ page }),
    queryFn: () => api<Paginated<Account>>(`/accounts?page=${page}&per_page=25`),
    enabled: tab === "accounts",
  });

  const { data: journals, isLoading: jrnLoading } = useQuery<Paginated<JournalEntry>>({
    queryKey: queryKeys.journals.list({ page }),
    queryFn: () => api<Paginated<JournalEntry>>(`/journals?page=${page}&per_page=20`),
    enabled: tab === "journals",
  });

  const { data: trialBalance } = useQuery({
    queryKey: queryKeys.trialBalance.all,
    queryFn: () =>
      api<{ data: { accounts: TrialBalanceRow[]; total_debit: number; total_credit: number } }>(
        "/journals/trial-balance",
      ),
    enabled: tab === "trial-balance",
  });

  const typeColors: Record<string, "success" | "warning" | "info" | "default"> = {
    asset: "success",
    liability: "warning",
    equity: "info",
    revenue: "success",
    expense: "default",
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        {tab === "journals" && can("accounting.create") && (
          <Button size="sm" onClick={() => setDrawerOpen(true)}>
            <Plus className="h-4 w-4" />
            Post journal
          </Button>
        )}
      </div>

      <div className="flex gap-2">
        {(["accounts", "journals", "trial-balance"] as Tab[]).map((t) => (
          <Button
            key={t}
            size="sm"
            variant={tab === t ? "primary" : "outline"}
            onClick={() => {
              setTab(t);
              setPage(1);
            }}
          >
            {t === "accounts"
              ? "Chart of accounts"
              : t === "journals"
                ? "Journal entries"
                : "Trial balance"}
          </Button>
        ))}
      </div>

      {tab === "accounts" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Code</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-end">Balance</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {accLoading
                ? Array.from({ length: 6 }).map((_, i) => (
                    <TableRow key={i}>
                      {Array.from({ length: 5 }).map((__, j) => (
                        <TableCell key={j}>
                          <Skeleton className="h-4" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                : accounts?.data?.map((a) => (
                    <TableRow key={a.id}>
                      <TableCell className="font-mono text-xs">{a.account_number}</TableCell>
                      <TableCell className="font-medium">{a.name}</TableCell>
                      <TableCell>
                        <Badge tone={typeColors[a.type] ?? "default"}>{a.type}</Badge>
                      </TableCell>
                      <TableCell className="text-end">{formatCurrency(a.balance)}</TableCell>
                      <TableCell>
                        <Badge tone={a.is_active ? "success" : "default"}>
                          {a.is_active ? "Active" : "Inactive"}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
            </TableBody>
          </Table>
        </div>
      )}

      {tab === "journals" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Number</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Date</TableHead>
                <TableHead className="text-end">Amount</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {jrnLoading ? (
                Array.from({ length: 6 }).map((_, i) => (
                  <TableRow key={i}>
                    {Array.from({ length: 4 }).map((__, j) => (
                      <TableCell key={j}>
                        <Skeleton className="h-4" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : journals?.data?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="py-8 text-center text-[var(--muted)]">
                    No journal entries yet.
                  </TableCell>
                </TableRow>
              ) : (
                journals?.data?.map((j) => (
                  <TableRow key={j.id}>
                    <TableCell className="font-mono text-xs">{j.reference}</TableCell>
                    <TableCell>{j.description}</TableCell>
                    <TableCell className="text-[var(--muted)]">
                      {formatDate(j.entry_date)}
                    </TableCell>
                    <TableCell className="text-end">{formatCurrency(j.total_debit)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          {journals?.meta && (
            <Pagination
              page={page}
              lastPage={journals.meta.last_page}
              onPageChange={setPage}
              label={`Page ${journals.meta.current_page} of ${journals.meta.last_page}`}
            />
          )}
        </div>
      )}

      {tab === "trial-balance" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Account</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-end">Debit</TableHead>
                <TableHead className="text-end">Credit</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {trialBalance?.data?.accounts?.map((a) => (
                <TableRow key={a.account_id}>
                  <TableCell className="font-medium">
                    {a.account_number} {a.name}
                  </TableCell>
                  <TableCell>
                    <Badge tone={typeColors[a.type] ?? "default"}>{a.type}</Badge>
                  </TableCell>
                  <TableCell className="text-end">
                    {a.normal_side === "debit" ? formatCurrency(a.debit) : "—"}
                  </TableCell>
                  <TableCell className="text-end">
                    {a.normal_side === "credit" ? formatCurrency(a.credit) : "—"}
                  </TableCell>
                </TableRow>
              ))}
              <TableRow className="font-semibold bg-[var(--surface-2)]">
                <TableCell colSpan={2}>Total</TableCell>
                <TableCell className="text-end">
                  {formatCurrency(trialBalance?.data?.total_debit ?? 0)}
                </TableCell>
                <TableCell className="text-end">
                  {formatCurrency(trialBalance?.data?.total_credit ?? 0)}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      )}

      <Drawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Post journal entry"
        width="max-w-2xl"
      >
        <JournalForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
