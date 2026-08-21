import { useEffect } from "react";
import { useNavigate, useParams } from "react-router";
import { useQuery, useMutation } from "@tanstack/react-query";
import { ArrowLeft, Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Logo } from "@/components/ui/logo";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { portalFetch, usePortalToken } from "@/lib/portal";
import { formatCurrency, formatDate } from "@/lib/utils";

interface InvoiceItem {
  id: string;
  name: string;
  quantity: number;
  unit_price: number;
  discount_amount: number;
  tax_amount: number;
  line_total: number;
}

interface InvoiceDetail {
  id: string;
  invoice_number: string;
  total: number;
  paid_amount: number;
  due_amount: number;
  payment_status: string;
  sold_at: string;
  items: InvoiceItem[];
}

function statusBadge(status: string) {
  switch (status) {
    case "paid": return <Badge tone="success">Paid</Badge>;
    case "partial": return <Badge tone="warning">Partial</Badge>;
    case "unpaid": return <Badge tone="danger">Unpaid</Badge>;
    default: return <Badge>{status}</Badge>;
  }
}

export default function PortalInvoiceDetailPage() {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const token = usePortalToken();
  const ready = !!token;

  useEffect(() => {
    if (!token) navigate("/portal", { replace: true });
  }, [token, navigate]);

  const { data: invoice, isLoading } = useQuery({
    queryKey: ["portal", "invoice", id],
    queryFn: () => portalFetch<{ data: InvoiceDetail }>(`/portal/invoices/${id}`).then((r) => r.data),
    enabled: ready,
  });

  const payMutation = useMutation({
    mutationFn: () => portalFetch<{ session_id: string }>(`/portal/pay/${id}`, {
      method: "POST",
    }),
    onSuccess: (res) => {
      window.location.href = `https://checkout.stripe.com/pay/${res.session_id}`;
    },
    onError: (err: Error) => {
      toast(err.message, "error");
    },
  });

  if (!ready) return null;

  return (
    <div className="min-h-screen bg-[var(--bg)]">
      <header className="border-b border-[var(--border)] bg-[var(--surface)]">
        <div className="mx-auto flex max-w-4xl items-center gap-3 px-6 py-4">
          <Button variant="ghost" size="sm" onClick={() => navigate("/portal/dashboard")}>
            <ArrowLeft className="mr-1 h-4 w-4" /> Back
          </Button>
          <Logo size={24} />
          <span className="font-semibold">Invoice</span>
        </div>
      </header>

      <main className="mx-auto max-w-4xl px-6 py-8">
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : !invoice ? (
          <p className="text-sm text-[var(--muted)]">Invoice not found.</p>
        ) : (
          <>
            <div className="mb-6 flex items-start justify-between">
              <div>
                <h1 className="text-2xl font-semibold">{invoice.invoice_number}</h1>
                <p className="text-sm text-[var(--muted)]">{formatDate(invoice.sold_at)}</p>
              </div>
              <div className="flex items-center gap-3">
                {statusBadge(invoice.payment_status)}
                <Button variant="outline" size="sm" onClick={() => window.print()}>
                  <Printer className="mr-1 h-4 w-4" /> Print
                </Button>
              </div>
            </div>

            <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Item</TableHead>
                    <TableHead className="text-right">Qty</TableHead>
                    <TableHead className="text-right">Price</TableHead>
                    <TableHead className="text-right">Discount</TableHead>
                    <TableHead className="text-right">Tax</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoice.items.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.name}</TableCell>
                      <TableCell className="text-right">{item.quantity}</TableCell>
                      <TableCell className="text-right">{formatCurrency(item.unit_price)}</TableCell>
                      <TableCell className="text-right">{formatCurrency(item.discount_amount)}</TableCell>
                      <TableCell className="text-right">{formatCurrency(item.tax_amount)}</TableCell>
                      <TableCell className="text-right font-medium">{formatCurrency(item.line_total)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <div className="mt-6 flex justify-end">
              <div className="w-72 space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-[var(--muted)]">Total</span>
                  <span className="font-medium">{formatCurrency(invoice.total)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--muted)]">Paid</span>
                  <span>{formatCurrency(invoice.paid_amount)}</span>
                </div>
                {invoice.due_amount > 0 && (
                  <div className="flex justify-between text-[var(--color-danger)]">
                    <span>Due</span>
                    <span className="font-medium">{formatCurrency(invoice.due_amount)}</span>
                  </div>
                )}
              </div>
            </div>

            {invoice.due_amount > 0 && (
              <div className="mt-8 text-center">
                <Button
                  size="lg"
                  onClick={() => payMutation.mutate()}
                  disabled={payMutation.isPending}
                >
                  {payMutation.isPending ? "Redirecting..." : `Pay Now — ${formatCurrency(invoice.due_amount)}`}
                </Button>
              </div>
            )}
          </>
        )}
      </main>
    </div>
  );
}
