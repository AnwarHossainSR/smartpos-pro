"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, ChevronLeft, ChevronRight, Pencil } from "lucide-react";
import { useForm } from "react-hook-form";
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

interface ExpenseCategory { id: number; name: string; }
interface Expense {
  id: number;
  reference: string;
  amount: number;
  payment_method: string;
  status: string;
  note: string | null;
  spent_at: string;
  category: { id: number; name: string } | null;
}

const schema = z.object({
  category_id: z.number().optional(),
  amount: z.number().min(0.01, "Amount required"),
  payment_method: z.enum(["cash", "card", "bank", "mobile_banking"]),
  note: z.string().optional(),
  spent_at: z.string().min(1, "Date required"),
});
type FormValues = z.infer<typeof schema>;

function ExpenseForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { data: cats } = useQuery({ queryKey: ["expense-categories"], queryFn: () => api<{ data: ExpenseCategory[] }>("/expense-categories?per_page=100") });
  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { payment_method: "cash", spent_at: new Date().toISOString().slice(0, 10) },
  });
  const mutation = useMutation({
    mutationFn: (data: FormValues) => api("/expenses", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["expenses"] }); toast("Expense added"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });
  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Category</Label>
        <Select {...register("category_id", { setValueAs: (v) => v ? Number(v) : undefined })}>
          <option value="">— None —</option>
          {cats?.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label>Amount *</Label>
          <Input type="number" step="0.01" min="0.01" {...register("amount", { valueAsNumber: true })} />
          {errors.amount && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.amount.message}</p>}
        </div>
        <div>
          <Label>Date *</Label>
          <Input type="date" {...register("spent_at")} />
        </div>
      </div>
      <div>
        <Label>Payment method</Label>
        <Select {...register("payment_method")}>
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="bank">Bank</option>
          <option value="mobile_banking">Mobile banking</option>
        </Select>
      </div>
      <div>
        <Label>Note</Label>
        <Textarea {...register("note")} placeholder="Optional note…" />
      </div>
      <Button type="submit" disabled={mutation.isPending} className="w-full">{mutation.isPending ? "Saving…" : "Add expense"}</Button>
    </form>
  );
}

export default function ExpensesPage() {
  const { can } = useAuth();
  const [search, setSearch] = useState(""); const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const { data, isLoading } = useQuery<Paginated<Expense>>({
    queryKey: ["expenses", search, page],
    queryFn: () => api<Paginated<Expense>>(`/expenses?page=${page}&per_page=20`),
  });

  const payColor: Record<string, "success" | "warning" | "default"> = { approved: "success", pending: "warning", rejected: "danger" as "default" };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Expenses</h1>
        {can("expenses.create") && <Button size="sm" onClick={() => setDrawerOpen(true)}><Plus className="h-4 w-4" />Add expense</Button>}
      </div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Reference</TableHead><TableHead>Category</TableHead><TableHead>Date</TableHead><TableHead>Method</TableHead><TableHead className="text-right">Amount</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
          <TableBody>
            {isLoading ? Array.from({ length: 6 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 6 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
              : data?.data?.length === 0 ? <TableRow><TableCell colSpan={6} className="py-12 text-center text-[var(--muted)]">No expenses yet.</TableCell></TableRow>
              : data?.data?.map((e) => (
                <TableRow key={e.id}>
                  <TableCell className="font-mono text-xs">{e.reference}</TableCell>
                  <TableCell>{e.category?.name ?? "—"}</TableCell>
                  <TableCell>{e.spent_at}</TableCell>
                  <TableCell className="capitalize">{e.payment_method.replace("_", " ")}</TableCell>
                  <TableCell className="text-right font-medium">{formatCurrency(e.amount)}</TableCell>
                  <TableCell><Badge tone={payColor[e.status] ?? "default"}>{e.status}</Badge></TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
        {data?.meta && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
            <p className="text-sm text-[var(--muted)]">Page {data.meta.current_page} of {data.meta.last_page}</p>
            <div className="flex gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}><ChevronLeft className="h-4 w-4" /></Button>
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page >= (data.meta.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}><ChevronRight className="h-4 w-4" /></Button>
            </div>
          </div>
        )}
      </div>
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="Add expense">
        <ExpenseForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
