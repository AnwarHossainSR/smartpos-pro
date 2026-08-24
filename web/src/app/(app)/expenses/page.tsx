import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { formatCurrency } from "@/lib/utils";
import type { Named } from "@/types/domain";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Drawer } from "@/components/ui/drawer";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Expense {
  id: string;
  reference: string;
  amount: number;
  payment_method: string;
  status: string;
  note: string | null;
  spent_at: string;
  category: Named | null;
}

const schema = z.object({
  category_id: z.string().optional(),
  amount: z.number().min(0.01, "Amount required"),
  payment_method: z.enum(["cash", "card", "bank", "mobile_banking"]),
  note: z.string().optional(),
  spent_at: z.string().min(1, "Date required"),
});
type FormValues = z.infer<typeof schema>;

function ExpenseForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  // Category list only fills a picker — cached apart from any paginated expense-category
  // list and kept fresh for a long while, since categories barely change during a session.
  const { data: cats } = useQuery({
    queryKey: queryKeys.expenseCategories.options(),
    queryFn: () => api<Paginated<Named>>("/expense-categories?per_page=100"),
    staleTime: 5 * 60_000,
  });
  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { payment_method: "cash", spent_at: new Date().toISOString().slice(0, 10) },
  });
  const mutation = useMutation({
    mutationFn: (data: FormValues) => api("/expenses", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.expenses.all }); toast("Expense added"); onSuccess(); },
  });
  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div>
        <Label>Category</Label>
        <Select {...register("category_id", { setValueAs: (v) => v ? String(v) : undefined })}>
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

const payColor: Record<string, "success" | "warning" | "danger" | "default"> = {
  approved: "success", pending: "warning", rejected: "danger",
};

export default function ExpensesPage() {
  const { can } = useAuth();
  const [drawerOpen, setDrawerOpen] = useState(false);

  const list = useResourceList<Expense>({
    queryKey: queryKeys.expenses.lists(),
    queryFn: (p) => api<Paginated<Expense>>(`/expenses?${buildListQuery(p)}`),
  });

  const columns: DataTableColumn<Expense>[] = [
    { key: "reference", header: "Reference", className: "font-mono text-xs", render: (e) => e.reference },
    { key: "category", header: "Category", render: (e) => e.category?.name ?? "—" },
    { key: "date", header: "Date", render: (e) => e.spent_at },
    { key: "method", header: "Method", className: "capitalize", render: (e) => e.payment_method.replace("_", " ") },
    { key: "amount", header: "Amount", align: "right", className: "font-medium", render: (e) => formatCurrency(e.amount) },
    { key: "status", header: "Status", render: (e) => <Badge tone={payColor[e.status] ?? "default"}>{e.status}</Badge> },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        addLabel={can("expenses.create") ? "Add expense" : undefined}
        onAdd={can("expenses.create") ? () => setDrawerOpen(true) : undefined}
        onRefresh={list.refetch}
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(e) => e.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No expenses yet."
        />
        {list.meta && (
          <Pagination
            page={list.page}
            lastPage={list.lastPage}
            onPageChange={list.setPage}
            label={`Page ${list.meta.current_page} of ${list.meta.last_page}`}
          />
        )}
      </div>
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="Add expense">
        <ExpenseForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
