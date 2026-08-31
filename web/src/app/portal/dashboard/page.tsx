import { useQuery } from "@tanstack/react-query";
import { LogOut } from "lucide-react";
import { useEffect } from "react";
import { useNavigate } from "react-router";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Logo } from "@/components/ui/logo";
import { Skeleton } from "@/components/ui/skeleton";
import { clearPortalToken, portalFetch, usePortalToken } from "@/lib/portal";
import { queryKeys } from "@/lib/query-keys";
import { formatCurrency, formatDate } from "@/lib/utils";

interface PortalCustomer {
  id: string;
  name: string;
  email: string;
  phone: string | null;
}

interface PortalInvoice {
  id: string;
  invoice_number: string;
  total: number;
  paid_amount: number;
  due_amount: number;
  payment_status: string;
  sold_at: string;
}

function statusBadge(status: string) {
  switch (status) {
    case "paid":
      return <Badge tone="success">Paid</Badge>;
    case "partial":
      return <Badge tone="warning">Partial</Badge>;
    case "unpaid":
      return <Badge tone="danger">Unpaid</Badge>;
    default:
      return <Badge>{status}</Badge>;
  }
}

export default function PortalDashboardPage() {
  const navigate = useNavigate();
  const token = usePortalToken();
  const ready = !!token;

  useEffect(() => {
    if (!token) navigate("/portal", { replace: true });
  }, [token, navigate]);

  const { data: customer, isLoading: loadingCustomer } = useQuery({
    queryKey: queryKeys.portal.me(),
    queryFn: () => portalFetch<{ data: PortalCustomer }>("/portal/me").then((r) => r.data),
    enabled: ready,
  });

  const { data: invoices, isLoading: loadingInvoices } = useQuery({
    queryKey: queryKeys.portal.invoices(),
    queryFn: () => portalFetch<{ data: PortalInvoice[] }>("/portal/invoices").then((r) => r.data),
    enabled: ready,
  });

  if (!ready) return null;

  return (
    <div className="min-h-screen bg-[var(--bg)]">
      <header className="border-b border-[var(--border)] bg-[var(--surface)]">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-2">
            <Logo size={28} />
            <span className="font-semibold">My Account</span>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              clearPortalToken();
              window.location.href = "/portal";
            }}
          >
            <LogOut className="mr-1 h-4 w-4" /> Sign out
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-4xl px-6 py-8">
        {loadingCustomer ? (
          <Skeleton className="h-8 w-48 mb-4" />
        ) : customer ? (
          <div className="mb-8">
            <h1 className="text-2xl font-semibold">{customer.name}</h1>
            <p className="text-sm text-[var(--muted)]">{customer.email}</p>
          </div>
        ) : null}

        <h2 className="text-lg font-medium mb-4">Invoices</h2>

        {loadingInvoices ? (
          <div className="space-y-3">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
        ) : !invoices?.length ? (
          <p className="text-sm text-[var(--muted)]">No invoices found.</p>
        ) : (
          <div className="space-y-3">
            {invoices.map((inv) => (
              <div
                key={inv.id}
                className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4 cursor-pointer hover:bg-[var(--surface-2)] transition-colors"
                onClick={() => navigate(`/portal/invoices/${inv.id}`)}
              >
                <div>
                  <p className="font-medium">{inv.invoice_number}</p>
                  <p className="text-xs text-[var(--muted)]">{formatDate(inv.sold_at)}</p>
                </div>
                <div className="flex items-center gap-4 text-sm">
                  <div className="text-right">
                    <p>Total: {formatCurrency(inv.total)}</p>
                    {inv.due_amount > 0 && (
                      <p className="text-[var(--color-danger)]">
                        Due: {formatCurrency(inv.due_amount)}
                      </p>
                    )}
                  </div>
                  {statusBadge(inv.payment_status)}
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
