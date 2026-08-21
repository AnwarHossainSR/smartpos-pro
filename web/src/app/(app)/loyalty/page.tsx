import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Select } from "@/components/ui/select";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Customer { id: string; name: string; phone: string | null; loyalty_points: number; }
interface Coupon {
  id: string;
  code: string;
  type: string;
  value: number;
  min_order_amount: number;
  max_uses: number | null;
  used_count: number;
  valid_from: string | null;
  valid_until: string | null;
  is_active: boolean;
}

const couponSchema = z.object({
  code: z.string().min(1),
  type: z.enum(["percentage", "fixed"]),
  value: z.number().min(0.01),
  min_order_amount: z.number().min(0).optional(),
  max_uses: z.number().min(1).optional(),
  valid_from: z.string().optional(),
  valid_until: z.string().optional(),
});
type CouponValues = z.infer<typeof couponSchema>;

function CouponForm({ onSuccess }: { onSuccess: () => void }) {
  const qc = useQueryClient();
  const { register, handleSubmit, formState: { errors } } = useForm<CouponValues>({
    resolver: zodResolver(couponSchema),
    defaultValues: { type: "percentage", value: 10, min_order_amount: 0 },
  });
  const mutation = useMutation({
    mutationFn: (d: CouponValues) => api("/coupons", { method: "POST", body: d }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["coupons"] }); toast("Coupon created"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });
  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div><Label>Code *</Label><Input {...register("code")} placeholder="SUMMER20" />{errors.code && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.code.message}</p>}</div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Type</Label>
          <Select {...register("type")}>
            <option value="percentage">Percentage %</option>
            <option value="fixed">Fixed amount</option>
          </Select>
        </div>
        <div><Label>Value *</Label><Input type="number" step="0.01" min="0.01" {...register("value", { valueAsNumber: true })} /></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Min order</Label><Input type="number" step="0.01" min="0" {...register("min_order_amount", { valueAsNumber: true })} /></div>
        <div><Label>Max uses</Label><Input type="number" min="1" {...register("max_uses", { valueAsNumber: true })} placeholder="Unlimited" /></div>
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div><Label>Valid from</Label><Input type="date" {...register("valid_from")} /></div>
        <div><Label>Valid until</Label><Input type="date" {...register("valid_until")} /></div>
      </div>
      <Button type="submit" disabled={mutation.isPending} className="w-full">{mutation.isPending ? "Creating…" : "Create coupon"}</Button>
    </form>
  );
}

type Tab = "coupons" | "points";

export default function LoyaltyPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("coupons");
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const { data: coupons, isLoading: couponsLoading } = useQuery<Paginated<Coupon>>({
    queryKey: ["coupons", debouncedSearch],
    queryFn: () => api<Paginated<Coupon>>(`/coupons?per_page=20`),
    enabled: tab === "coupons",
  });

  const { data: customers, isLoading: customersLoading } = useQuery<Paginated<Customer>>({
    queryKey: ["customers-loyalty", debouncedSearch],
    queryFn: () => api<Paginated<Customer>>(`/customers?search=${encodeURIComponent(debouncedSearch)}&per_page=20`),
    enabled: tab === "points",
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        {tab === "coupons" && can("coupons.create") && <Button size="sm" onClick={() => setDrawerOpen(true)}>+ New coupon</Button>}
      </div>

      <div className="flex gap-2">
        {(["coupons", "points"] as Tab[]).map((t) => (
          <Button key={t} size="sm" variant={tab === t ? "primary" : "outline"} onClick={() => setTab(t)}>
            {t === "coupons" ? "Coupons" : "Loyalty points"}
          </Button>
        ))}
      </div>

      {tab === "points" && (
        <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder="Search customers…" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
      )}

      {tab === "coupons" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Code</TableHead><TableHead>Type</TableHead><TableHead>Value</TableHead><TableHead>Min order</TableHead><TableHead>Uses</TableHead><TableHead>Valid until</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
              {couponsLoading ? Array.from({ length: 5 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 7 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
                : coupons?.data?.length === 0 ? <TableRow><TableCell colSpan={7} className="py-8 text-center text-[var(--muted)]">No coupons yet.</TableCell></TableRow>
                : coupons?.data?.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-mono font-semibold">{c.code}</TableCell>
                    <TableCell>{c.type}</TableCell>
                    <TableCell>{c.type === "percentage" ? `${c.value}%` : formatCurrency(c.value)}</TableCell>
                    <TableCell>{c.min_order_amount > 0 ? formatCurrency(c.min_order_amount) : "—"}</TableCell>
                    <TableCell>{c.used_count}{c.max_uses ? ` / ${c.max_uses}` : ""}</TableCell>
                    <TableCell>{c.valid_until ?? "—"}</TableCell>
                    <TableCell><Badge tone={c.is_active ? "success" : "default"}>{c.is_active ? "Active" : "Inactive"}</Badge></TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </div>
      )}

      {tab === "points" && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
          <Table>
            <TableHeader><TableRow><TableHead>Customer</TableHead><TableHead>Phone</TableHead><TableHead className="text-end">Loyalty points</TableHead></TableRow></TableHeader>
            <TableBody>
              {customersLoading ? Array.from({ length: 5 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 3 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
                : customers?.data?.length === 0 ? <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--muted)]">No customers found.</TableCell></TableRow>
                : customers?.data?.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.name}</TableCell>
                    <TableCell>{c.phone ?? "—"}</TableCell>
                    <TableCell className="text-end font-semibold">{c.loyalty_points} pts</TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </div>
      )}

      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title="New coupon">
        <CouponForm onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
    </div>
  );
}
