"use client";

import { useState, useCallback, useMemo } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Search, ShoppingCart, Trash2, Plus, Minus, X, User, Printer } from "lucide-react";
import { api, ApiError, type Paginated } from "@/lib/api";
import { formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

/* ---- types ---- */
interface Product {
  id: number;
  name: string;
  sku: string | null;
  selling_price: number;
  purchase_price: number;
  stock: number;
  is_active: boolean;
  category: { id: number; name: string } | null;
}

interface Customer {
  id: number;
  name: string;
  phone: string | null;
  balance_due: number;
}

interface Warehouse {
  id: number;
  name: string;
  is_default: boolean;
}

interface CartItem {
  product: Product;
  qty: number;
  unit_price: number;
  discount: number;
}

interface SaleResult {
  data: {
    id: number;
    invoice_number: string;
    total: number;
    paid_amount: number;
    change_amount: number;
    due_amount: number;
    sold_at: string;
    customer?: { id: number; name: string };
    items: { name: string; quantity: number; unit_price: number; total: number }[];
    payments: { method: string; amount: number }[];
  };
}

/* ---- receipt modal ---- */
function ReceiptModal({ sale, onClose }: { sale: SaleResult["data"]; onClose: () => void }) {
  return (
    <Dialog open title="Receipt" onClose={onClose}>
      <div className="space-y-3 text-sm">
        <div className="text-center">
          <p className="font-semibold text-base">Invoice #{sale.invoice_number}</p>
          <p className="text-[var(--muted)]">{new Date(sale.sold_at).toLocaleString()}</p>
          {sale.customer && <p className="mt-1">{sale.customer.name}</p>}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          {sale.items?.map((item, i) => (
            <div key={i} className="flex justify-between">
              <span className="flex-1 truncate">{item.name} × {item.quantity}</span>
              <span className="ms-4 font-medium">{formatCurrency(item.total)}</span>
            </div>
          ))}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          <div className="flex justify-between font-semibold">
            <span>Total</span><span>{formatCurrency(sale.total)}</span>
          </div>
          {sale.payments?.map((p, i) => (
            <div key={i} className="flex justify-between text-[var(--muted)]">
              <span className="capitalize">{p.method}</span><span>{formatCurrency(p.amount)}</span>
            </div>
          ))}
          {sale.change_amount > 0 && (
            <div className="flex justify-between text-green-600">
              <span>Change</span><span>{formatCurrency(sale.change_amount)}</span>
            </div>
          )}
          {sale.due_amount > 0 && (
            <div className="flex justify-between text-amber-600">
              <span>Due</span><span>{formatCurrency(sale.due_amount)}</span>
            </div>
          )}
        </div>
        <div className="flex gap-2 mt-4">
          <Button variant="outline" className="flex-1" onClick={() => window.print()}>
            <Printer className="h-4 w-4" /> Print
          </Button>
          <Button className="flex-1" onClick={onClose}>New sale</Button>
        </div>
      </div>
    </Dialog>
  );
}

/* ---- payment modal ---- */
function PaymentModal({
  total,
  onConfirm,
  onClose,
  loading,
}: {
  total: number;
  onConfirm: (payments: { method: string; amount: number }[]) => void;
  onClose: () => void;
  loading: boolean;
}) {
  const [method, setMethod] = useState<string>("cash");
  const [amount, setAmount] = useState<string>(total.toFixed(2));

  const handleSubmit = () => {
    const paid = parseFloat(amount);
    if (isNaN(paid) || paid <= 0) { toast("Enter a valid amount", "error"); return; }
    onConfirm([{ method, amount: paid }]);
  };

  return (
    <Dialog open title="Payment" onClose={onClose}>
      <div className="space-y-4">
        <div>
          <Label>Amount due</Label>
          <p className="text-2xl font-bold">{formatCurrency(total)}</p>
        </div>
        <div>
          <Label>Payment method</Label>
          <Select value={method} onChange={(e) => setMethod(e.target.value)}>
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="mobile_banking">Mobile banking</option>
            <option value="bank">Bank transfer</option>
            <option value="wallet">Wallet</option>
            <option value="due">Due / Credit</option>
          </Select>
        </div>
        <div>
          <Label>Amount tendered</Label>
          <Input
            type="number"
            step="0.01"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            autoFocus
          />
        </div>
        {parseFloat(amount) > total && (
          <p className="text-sm text-green-600">Change: {formatCurrency(parseFloat(amount) - total)}</p>
        )}
        <div className="flex gap-2">
          <Button variant="outline" onClick={onClose} disabled={loading} className="flex-1">Cancel</Button>
          <Button onClick={handleSubmit} disabled={loading} className="flex-1">
            {loading ? "Processing…" : "Confirm payment"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

/* ---- main POS page ---- */
export default function POSPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [cart, setCart] = useState<CartItem[]>([]);
  const [customerId, setCustomerId] = useState<number | null>(null);
  const [customerSearch, setCustomerSearch] = useState("");
  const [showPayment, setShowPayment] = useState(false);
  const [receipt, setReceipt] = useState<SaleResult["data"] | null>(null);

  const { data: productsData, isLoading: productsLoading } = useQuery<Paginated<Product>>({
    queryKey: ["pos-products", search],
    queryFn: () => api<Paginated<Product>>(`/products?search=${encodeURIComponent(search)}&per_page=30&is_active=true`),
    staleTime: 15_000,
  });

  const { data: customersData } = useQuery<Paginated<Customer>>({
    queryKey: ["pos-customers", customerSearch],
    queryFn: () => api<Paginated<Customer>>(`/customers?search=${encodeURIComponent(customerSearch)}&per_page=10`),
    enabled: customerSearch.length > 0,
  });

  const { data: warehousesData } = useQuery({
    queryKey: ["warehouses-all"],
    queryFn: () => api<{ data: Warehouse[] }>("/warehouses"),
  });

  const defaultWarehouse = useMemo(
    () => warehousesData?.data?.find((w) => w.is_default) ?? warehousesData?.data?.[0],
    [warehousesData],
  );

  const selectedCustomer = useMemo(
    () => (customerId ? customersData?.data?.find((c) => c.id === customerId) : null),
    [customerId, customersData],
  );

  const addToCart = useCallback((product: Product) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === product.id);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id ? { ...i, qty: i.qty + 1 } : i,
        );
      }
      return [...prev, { product, qty: 1, unit_price: product.selling_price, discount: 0 }];
    });
  }, []);

  const updateQty = (id: number, qty: number) => {
    if (qty <= 0) {
      setCart((prev) => prev.filter((i) => i.product.id !== id));
    } else {
      setCart((prev) => prev.map((i) => (i.product.id === id ? { ...i, qty } : i)));
    }
  };

  const removeItem = (id: number) => setCart((prev) => prev.filter((i) => i.product.id !== id));

  const cartTotal = useMemo(
    () => cart.reduce((sum, i) => sum + (i.unit_price - i.discount) * i.qty, 0),
    [cart],
  );

  const checkoutMutation = useMutation({
    mutationFn: (payments: { method: string; amount: number }[]) => {
      const idempotencyKey = crypto.randomUUID();
      return api<SaleResult>("/sales", {
        method: "POST",
        idempotencyKey,
        body: {
          branch_id: user?.branch_id,
          warehouse_id: defaultWarehouse?.id,
          customer_id: customerId,
          items: cart.map((i) => ({
            product_id: i.product.id,
            quantity: i.qty,
            unit_price: i.unit_price,
            discount_amount: i.discount * i.qty,
          })),
          payments,
        },
      });
    },
    onSuccess: (data) => {
      setReceipt(data.data);
      setCart([]);
      setCustomerId(null);
      setCustomerSearch("");
      setShowPayment(false);
      qc.invalidateQueries({ queryKey: ["products"] });
      qc.invalidateQueries({ queryKey: ["pos-products"] });
      qc.invalidateQueries({ queryKey: ["dashboard-summary"] });
    },
    onError: (e: unknown) => {
      toast(e instanceof ApiError ? e.message : "Checkout failed", "error");
    },
  });

  return (
    <div className="flex h-[calc(100vh-4rem)] gap-4 overflow-hidden">
      {/* Left: product search + grid */}
      <div className="flex flex-1 flex-col min-w-0 gap-3">
        <div className="relative">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
          <Input
            className="ps-9"
            placeholder="Search products…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            autoFocus
          />
        </div>

        <div className="flex-1 overflow-y-auto">
          {productsLoading ? (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
              {Array.from({ length: 12 }).map((_, i) => (
                <Skeleton key={i} className="h-24 rounded-[var(--radius)]" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
              {productsData?.data?.map((p) => (
                <button
                  key={p.id}
                  onClick={() => addToCart(p)}
                  disabled={(p.stock ?? 0) <= 0}
                  className="group flex flex-col rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-3 text-start transition hover:border-[var(--primary)] hover:shadow-sm disabled:opacity-50"
                >
                  <p className="line-clamp-2 text-sm font-medium">{p.name}</p>
                  {p.category && <p className="mt-0.5 text-xs text-[var(--muted)]">{p.category.name}</p>}
                  <p className="mt-2 font-semibold text-[var(--primary)]">{formatCurrency(p.selling_price)}</p>
                  <p className="text-xs text-[var(--muted)]">Stock: {p.stock ?? 0}</p>
                </button>
              ))}
              {productsData?.data?.length === 0 && (
                <div className="col-span-4 py-12 text-center text-sm text-[var(--muted)]">
                  No products found
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Right: cart */}
      <div className="flex w-80 shrink-0 flex-col rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <div className="flex items-center justify-between border-b border-[var(--border)] px-4 py-3">
          <div className="flex items-center gap-2 font-semibold">
            <ShoppingCart className="h-4 w-4" />
            Cart
            {cart.length > 0 && (
              <Badge tone="info">{cart.reduce((s, i) => s + i.qty, 0)}</Badge>
            )}
          </div>
          {cart.length > 0 && (
            <button onClick={() => setCart([])} className="text-xs text-[var(--muted)] hover:text-[var(--color-danger)]">
              Clear
            </button>
          )}
        </div>

        {/* Customer selector */}
        <div className="border-b border-[var(--border)] p-3">
          <div className="relative">
            <User className="absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--muted)]" />
            <Input
              className="ps-8 h-8 text-xs"
              placeholder={selectedCustomer ? selectedCustomer.name : "Search customer (optional)…"}
              value={selectedCustomer ? selectedCustomer.name : customerSearch}
              onChange={(e) => { setCustomerSearch(e.target.value); setCustomerId(null); }}
            />
            {customerId && (
              <button className="absolute end-2.5 top-1/2 -translate-y-1/2" onClick={() => { setCustomerId(null); setCustomerSearch(""); }}>
                <X className="h-3.5 w-3.5 text-[var(--muted)]" />
              </button>
            )}
          </div>
          {customerSearch && !customerId && customersData?.data && customersData.data.length > 0 && (
            <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] shadow-lg">
              {customersData.data.map((c) => (
                <button
                  key={c.id}
                  onClick={() => { setCustomerId(c.id); setCustomerSearch(""); }}
                  className="w-full px-3 py-2 text-start text-sm hover:bg-[var(--surface-2)]"
                >
                  {c.name} {c.phone && <span className="text-[var(--muted)]">· {c.phone}</span>}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Cart items */}
        <div className="flex-1 overflow-y-auto p-3 space-y-2">
          {cart.length === 0 ? (
            <div className="flex h-full items-center justify-center text-sm text-[var(--muted)]">
              Add products to the cart
            </div>
          ) : (
            cart.map((item) => (
              <div key={item.product.id} className="rounded-[var(--radius)] border border-[var(--border)] p-2.5">
                <div className="flex items-start justify-between gap-2">
                  <p className="flex-1 text-sm font-medium leading-tight">{item.product.name}</p>
                  <button onClick={() => removeItem(item.product.id)}>
                    <X className="h-3.5 w-3.5 text-[var(--muted)] hover:text-[var(--color-danger)]" />
                  </button>
                </div>
                <div className="mt-2 flex items-center justify-between">
                  <div className="flex items-center gap-1">
                    <Button variant="outline" size="icon" className="h-6 w-6" onClick={() => updateQty(item.product.id, item.qty - 1)}>
                      <Minus className="h-3 w-3" />
                    </Button>
                    <span className="w-8 text-center text-sm">{item.qty}</span>
                    <Button variant="outline" size="icon" className="h-6 w-6" onClick={() => updateQty(item.product.id, item.qty + 1)}>
                      <Plus className="h-3 w-3" />
                    </Button>
                  </div>
                  <span className="text-sm font-semibold">{formatCurrency(item.unit_price * item.qty)}</span>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Cart footer */}
        <div className="border-t border-[var(--border)] p-4 space-y-3">
          <div className="flex justify-between text-sm">
            <span className="text-[var(--muted)]">Subtotal</span>
            <span>{formatCurrency(cartTotal)}</span>
          </div>
          <div className="flex justify-between font-semibold">
            <span>Total</span>
            <span>{formatCurrency(cartTotal)}</span>
          </div>
          <Button
            className="w-full"
            disabled={cart.length === 0 || !defaultWarehouse || !user?.branch_id}
            onClick={() => setShowPayment(true)}
          >
            Checkout · {formatCurrency(cartTotal)}
          </Button>
          {!defaultWarehouse && (
            <p className="text-xs text-center text-[var(--color-danger)]">No warehouse configured</p>
          )}
        </div>
      </div>

      {showPayment && (
        <PaymentModal
          total={cartTotal}
          onConfirm={(payments) => checkoutMutation.mutate(payments)}
          onClose={() => setShowPayment(false)}
          loading={checkoutMutation.isPending}
        />
      )}

      {receipt && (
        <ReceiptModal
          sale={receipt}
          onClose={() => setReceipt(null)}
        />
      )}
    </div>
  );
}
