import { approvalChallengeFrom, type ApprovalChallenge } from "@/components/approval-challenge";
import { ApprovalPinDialog } from "@/components/approval-pin-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { CategoryTabs } from "@/features/pos/components/category-tabs";
import { HeldOrdersPanel, type HeldCartItem } from "@/features/pos/components/held-orders-panel";
import { PaymentNumpad } from "@/features/pos/components/payment-numpad";
import { QuantityPopover } from "@/features/pos/components/quantity-popover";
import { ShortcutsOverlay } from "@/features/pos/components/shortcuts-overlay";
import { ViewToggle } from "@/features/pos/components/view-toggle";
import { calculateLineTax, mergeTaxComponents, type TaxComponentResult } from "@/features/tax/calc";
import type { PriceDisplay, TaxGroup } from "@/features/tax/types";
import { useTranslation } from "@/i18n/context";
import { api, apiBlob, ApiError, type Paginated } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { cn, formatCurrency, formatDateTime, openPdfBlob } from "@/lib/utils";
import { useAuth } from "@/stores/auth";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Minus, Pause, Plus, Search, ShoppingCart, Star, User, UserPlus, X } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

/* ---- types ---- */
export interface Product {
  id: string;
  name: string;
  sku: string | null;
  selling_price: number;
  purchase_price: number;
  /** Legacy single-rate fields — still what `/products` returns today. Kept as the fallback
   *  path below so the cart keeps working even before the API adds `tax_group`. */
  tax_rate: number;
  tax_inclusive: boolean;
  /** Not yet returned by `/products` (only `/tax-groups` has it so far) — read defensively so
   *  the multi-component breakdown activates automatically once the API adds this relation. */
  tax_group?: TaxGroup | null;
  stock: number;
  min_stock_level?: number;
  image_url?: string | null;
  is_active: boolean;
  category: { id: string; name: string } | null;
  is_weighed?: boolean;
  weight_unit?: string | null;
  price_per_unit?: number | null;
}

interface Customer {
  id: string;
  name: string;
  phone: string | null;
  balance_due: number;
  loyalty_points: number;
}

interface LoyaltyData {
  data: { points: number };
}

interface Warehouse {
  id: string;
  name: string;
  is_default: boolean;
}

interface CartItem {
  product: Product;
  qty: number;
  unit_price: number;
  discount: number;
  weight?: number | null;
}

interface SaleResult {
  data: {
    id: string;
    invoice_number: string;
    subtotal: number;
    tax_amount: number;
    total: number;
    paid_amount: number;
    change_amount: number;
    due_amount: number;
    sold_at: string;
    customer?: { id: string; name: string };
    items: {
      name: string;
      quantity: number;
      unit_price: number;
      line_total: number;
      tax_amount?: number;
      /** Not yet returned by `/sales` (only reversed on `/sale-returns` so far) — rendered when
       *  present, falls back to the blended `tax_amount` line below when absent. */
      taxes?: { name: string; rate: number; amount: number }[];
    }[];
    payments: { method: string; amount: number }[];
  };
}

const round2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Per-line tax, with the full per-component breakdown (e.g. CGST 9% + SGST 9% shown
 * separately) — mirrors the backend's `App\Domain\Tax\TaxCalculator::forLine()`
 * (see `src/features/tax/calc.ts`) so the amount tendered matches the server's total to 2dp.
 *
 * Falls back to the legacy single blended rate when a product hasn't got a `tax_group`
 * yet (today's `/products` response) — see the `Product` type above for why that's optional.
 */
function lineTotals({ product, qty, unit_price, discount }: CartItem, priceDisplay: PriceDisplay) {
  const taxable = Math.max(0, unit_price * qty - discount * qty);

  if (product.tax_group?.rates?.length) {
    const result = calculateLineTax(taxable, qty, product.tax_group.rates, priceDisplay);
    return { net: result.net, tax: result.tax, components: result.components as TaxComponentResult[] };
  }

  const rate = product.tax_rate ?? 0;
  const tax = product.tax_inclusive
    ? round2(taxable - taxable / (1 + rate / 100))
    : round2(taxable * (rate / 100));
  const net = product.tax_inclusive ? round2(taxable - tax) : round2(taxable);
  const components: TaxComponentResult[] =
    tax > 0 ? [{ name: "Tax", rate, type: "percentage", is_compound: false, base_amount: net, amount: tax }] : [];

  return { net, tax, components };
}

/* ---- receipt modal ---- */
function ReceiptModal({ sale, onClose }: { sale: SaleResult["data"]; onClose: () => void }) {
  const [loadingInvoice, setLoadingInvoice] = useState(false);

  // Aggregate each item's own component breakdown when the API provides one (today, only
  // sale returns do — see the `taxes` comment on SaleResult above); falls back to the single
  // blended `tax_amount` line so the receipt still works against the current `/sales` response.
  const taxComponents = mergeTaxComponents(
    sale.items?.map((item) =>
      item.taxes?.map((t) => ({ name: t.name, rate: t.rate, type: "percentage" as const, is_compound: false, base_amount: 0, amount: t.amount })) ?? [],
    ) ?? [],
  );

  const viewInvoice = async () => {
    setLoadingInvoice(true);
    try {
      openPdfBlob(await apiBlob(`/sales/${sale.id}/invoice`));
    } catch {
      toast("Could not load invoice", "error");
    } finally {
      setLoadingInvoice(false);
    }
  };

  return (
    <Dialog open title="Receipt" onClose={onClose}>
      <div data-receipt className="space-y-3 text-sm">
        <div className="text-center">
          <p className="font-semibold text-base">Invoice #{sale.invoice_number}</p>
          <p className="text-[var(--muted)]">{formatDateTime(sale.sold_at)}</p>
          {sale.customer && <p className="mt-1">{sale.customer.name}</p>}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          {sale.items?.map((item, i) => (
            <div key={i} className="flex justify-between">
              <span className="flex-1 truncate">{item.name} × {item.quantity}</span>
              <span className="ms-4 font-medium">{formatCurrency(item.line_total)}</span>
            </div>
          ))}
        </div>
        <hr className="border-dashed" />
        <div className="space-y-1">
          {sale.tax_amount > 0 && (
            <>
              <div className="flex justify-between text-[var(--muted)]">
                <span>Subtotal</span><span>{formatCurrency(sale.subtotal)}</span>
              </div>
              {taxComponents.length > 0 ? (
                taxComponents.map((c) => (
                  <div key={`${c.name}@${c.rate}`} className="flex justify-between text-[var(--muted)]">
                    <span>{c.name} {c.rate > 0 ? `(${c.rate}%)` : ""}</span><span>{formatCurrency(c.amount)}</span>
                  </div>
                ))
              ) : (
                <div className="flex justify-between text-[var(--muted)]">
                  <span>Tax</span><span>{formatCurrency(sale.tax_amount)}</span>
                </div>
              )}
            </>
          )}
          <div className="flex justify-between font-semibold">
            <span>Total</span><span>{formatCurrency(sale.total)}</span>
          </div>
          {sale.payments?.map((p, i) => (
            <div key={i} className="flex justify-between text-[var(--muted)]">
              <span className="capitalize">{p.method}</span><span>{formatCurrency(p.amount)}</span>
            </div>
          ))}
          {sale.change_amount > 0 && (
            <div className="flex justify-between text-green-600 text-2xl font-bold">
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
          <Button variant="outline" className="flex-1" onClick={viewInvoice} disabled={loadingInvoice}>
            <FileText className="h-4 w-4" /> {loadingInvoice ? "Loading…" : "View invoice"}
          </Button>
          <Button variant="outline" className="flex-1" onClick={() => window.print()}>
            Print receipt
          </Button>
          <Button className="flex-1" onClick={onClose}>New sale</Button>
        </div>
      </div>
    </Dialog>
  );
}

const PAYMENT_METHODS = [
  { value: "cash", label: "Cash" },
  { value: "card", label: "Card" },
  { value: "bank", label: "Bank Transfer" },
  { value: "mobile_banking", label: "Mobile Banking" },
  { value: "wallet", label: "Wallet" },
  { value: "due", label: "Due / Credit" },
];

const CURRENCIES = [
  { code: "USD", name: "US Dollar" },
  { code: "EUR", name: "Euro" },
  { code: "GBP", name: "British Pound" },
  { code: "BDT", name: "Bangladeshi Taka" },
  { code: "JPY", name: "Japanese Yen" },
  { code: "SAR", name: "Saudi Riyal" },
  { code: "AED", name: "UAE Dirham" },
];

interface PaymentRow { id: string; method: string; amount: string; }
let paymentRowSeq = 0;

/** Split tender: the checkout endpoint already accepts a `payments` array (e.g. part cash,
 *  part card on one sale) — this was previously a UI gap where only a single row could be
 *  entered. Row 1 defaults to the full amount due so the common single-method case is still
 *  a one-field flow; "Add payment" only shows up when it's actually needed. */
function PaymentModal({
  total,
  onConfirm,
  onClose,
  loading,
  baseCurrency,
}: {
  total: number;
  onConfirm: (payments: { method: string; amount: number }[], tenderCurrency?: string, fxRate?: number, tenderAmount?: number) => void;
  onClose: () => void;
  loading: boolean;
  baseCurrency?: string;
}) {
  const t = useTranslation();
  const [rows, setRows] = useState<PaymentRow[]>(() => [{ id: String(++paymentRowSeq), method: "cash", amount: total.toFixed(2) }]);
  const [tenderCurrency, setTenderCurrency] = useState<string>("");
  const tenderAmount = rows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);

  const effectiveCurrency = tenderCurrency || baseCurrency || "USD";
  const isForeign = tenderCurrency && tenderCurrency !== (baseCurrency || "USD");

  const addRow = () =>
    setRows((prev) => [...prev, { id: String(++paymentRowSeq), method: "cash", amount: remaining > 0 ? remaining.toFixed(2) : "0" }]);

  const remaining = round2(total - tenderAmount);
  const removeRow = (id: string) => setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.id !== id) : prev));
  const updateRow = (id: string, patch: Partial<Omit<PaymentRow, "id">>) =>
    setRows((prev) => prev.map((r) => (r.id === id ? { ...r, ...patch } : r)));

  const handleSubmit = () => {
    const payments = rows
      .map((r) => ({ method: r.method, amount: round2(parseFloat(r.amount) || 0) }))
      .filter((p) => p.amount > 0);
    if (payments.length === 0) { toast("Enter a valid amount", "error"); return; }
    onConfirm(payments, tenderCurrency || undefined);
  };

  return (
    <Dialog open title="Payment" onClose={onClose}>
      <div className="space-y-4">
        <div>
          <Label>Amount due</Label>
          <p className="text-2xl font-bold">{formatCurrency(total)} {baseCurrency && <span className="text-sm font-normal text-[var(--muted)]">{baseCurrency}</span>}</p>
          {isForeign && (
            <p className="text-sm text-[var(--muted)]">
              ~ {formatCurrency(round2(total))} {effectiveCurrency}
            </p>
          )}
        </div>

        <div>
          <Label>{t("currency.tender")}</Label>
          <Select
            value={tenderCurrency}
            onValueChange={(v) => setTenderCurrency(v)}
          >
            <SelectTrigger><SelectValue placeholder={`${baseCurrency || "USD"} (${t("currency.base")})`} /></SelectTrigger>
            <SelectContent>
              <SelectItem value="">{baseCurrency || "USD"} ({t("currency.base")})</SelectItem>
              {CURRENCIES.filter((c) => c.code !== (baseCurrency || "USD")).map((c) => (
                <SelectItem key={c.code} value={c.code}>{c.code} — {c.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          {rows.map((row, i) => (
            <div key={row.id} className="space-y-1">
              <div className="flex items-center gap-2">
                <Select
                  value={row.method}
                  onValueChange={(v) => updateRow(row.id, { method: v })}
                >
                  <SelectTrigger className="w-40 shrink-0"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {PAYMENT_METHODS.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Input
                  type="number"
                  step="0.01"
                  value={row.amount}
                  onChange={(e) => updateRow(row.id, { amount: e.target.value })}
                  autoFocus={i === 0}
                />
                {rows.length > 1 && (
                  <Button variant="ghost" size="icon" className="h-10 w-10 shrink-0 text-[var(--color-danger)]" onClick={() => removeRow(row.id)}>
                    <X className="h-4 w-4" />
                  </Button>
                )}
              </div>
              <PaymentNumpad
                value={row.amount}
                onChange={(v) => updateRow(row.id, { amount: v })}
              />
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" onClick={addRow}>
            <Plus className="h-3.5 w-3.5" /> Add payment (split tender)
          </Button>
        </div>

        {remaining > 0.001 ? (
          <p className="text-sm text-amber-600">Remaining: {formatCurrency(remaining)} (recorded as due if you confirm now)</p>
        ) : remaining < -0.001 ? (
          <p className="text-sm text-green-600">Change: {formatCurrency(-remaining)}</p>
        ) : null}

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
  const { user, can } = useAuth();
  const canOverridePrice = can("pos.price_override");
  const canDiscount = can("pos.discount");
  const t = useTranslation();
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [cart, setCart] = useState<CartItem[]>([]);
  const [customerId, setCustomerId] = useState<string | null>(null);
  const [customerSearch, setCustomerSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const debouncedCustomerSearch = useDebouncedValue(customerSearch);
  const [showPayment, setShowPayment] = useState(false);
  const [receipt, setReceipt] = useState<SaleResult["data"] | null>(null);
  const [showHelp, setShowHelp] = useState(false);
  const [showHeld, setShowHeld] = useState(false);
  const [showCreateCustomer, setShowCreateCustomer] = useState(false);
  /** Set when the API refuses an action pending a supervisor PIN; drives the challenge dialog. */
  const [challenge, setChallenge] = useState<ApprovalChallenge | null>(null);
  /** The tender the cashier had already confirmed, replayed once a manager approves. */
  const [pendingPayments, setPendingPayments] = useState<{ method: string; amount: number }[]>([]);
  const [newCustomerName, setNewCustomerName] = useState("");
  const [newCustomerPhone, setNewCustomerPhone] = useState("");
  const searchInputRef = useRef<HTMLInputElement>(null);
  const lastKeystrokeRef = useRef<number>(0);
  const keystrokeBufferRef = useRef<string>("");

  const [activeCategoryId, setActiveCategoryId] = useState<string | null>(null);
  const [viewMode, setViewMode] = useState<"grid" | "list">(() => {
    if (typeof window !== "undefined") {
      return (localStorage.getItem("pos-view-mode") as "grid" | "list") ?? "grid";
    }
    return "grid";
  });
  const [favorites, setFavorites] = useState<Set<string>>(() => {
    if (typeof window !== "undefined") {
      try { return new Set(JSON.parse(localStorage.getItem("pos-favorites") ?? "[]")); } catch { return new Set(); }
    }
    return new Set();
  });

  useEffect(() => { localStorage.setItem("pos-view-mode", viewMode); }, [viewMode]);
  useEffect(() => { localStorage.setItem("pos-favorites", JSON.stringify([...favorites])); }, [favorites]);

  const toggleFavorite = useCallback((productId: string) => {
    setFavorites((prev) => {
      const next = new Set(prev);
      if (next.has(productId)) next.delete(productId);
      else next.add(productId);
      return next;
    });
  }, []);

  const { data: productsData, isLoading: productsLoading } = useQuery<Paginated<Product>>({
    queryKey: ["pos-products", debouncedSearch, activeCategoryId],
    queryFn: () => {
      const params = new URLSearchParams({ per_page: "50", is_active: "true" });
      if (debouncedSearch) params.set("search", debouncedSearch);
      if (activeCategoryId && activeCategoryId !== "__favorites__") params.set("category_id", activeCategoryId);
      return api<Paginated<Product>>(`/products?${params.toString()}`);
    },
    staleTime: 15_000,
  });

  const { data: categoriesData } = useQuery<{ data: { id: string; name: string }[] }>({
    queryKey: ["categories-all"],
    queryFn: () => api<{ data: { id: string; name: string }[] }>("/categories?per_page=50"),
  });

  const displayedProducts = useMemo(() => {
    const list = productsData?.data ?? [];
    if (activeCategoryId === "__favorites__") return list.filter((p) => favorites.has(p.id));
    return list;
  }, [productsData, activeCategoryId, favorites]);

  const { data: customersData } = useQuery<Paginated<Customer>>({
    queryKey: ["pos-customers", debouncedCustomerSearch],
    queryFn: () => api<Paginated<Customer>>(`/customers?search=${encodeURIComponent(debouncedCustomerSearch)}&per_page=10`),
    enabled: debouncedCustomerSearch.length > 0,
  });

  const { data: warehousesData } = useQuery({
    queryKey: ["warehouses-all"],
    queryFn: () => api<{ data: Warehouse[] }>("/warehouses"),
  });

  // Same query key as Settings → Business and the app shell's currency sync, so this is a
  // cache hit in practice rather than an extra request just for the POS screen.
  const { data: businessSettings } = useQuery({
    queryKey: queryKeys.businessSettings,
    queryFn: () => api<{ data: { price_display: PriceDisplay; currency?: string } }>("/settings/business"),
  });
  const priceDisplay: PriceDisplay = businessSettings?.data?.price_display ?? "exclusive";

  const { data: heldData } = useQuery<Paginated<{ id: string }>>({
    queryKey: queryKeys.heldSales.list(),
    queryFn: () => api<Paginated<{ id: string }>>("/sales?status=held&per_page=20&sort=-sold_at"),
  });
  const heldCount = heldData?.data?.length ?? 0;

  const defaultWarehouse = useMemo(
    () => warehousesData?.data?.find((w) => w.is_default) ?? warehousesData?.data?.[0],
    [warehousesData],
  );

  const selectedCustomer = useMemo(
    () => (customerId ? customersData?.data?.find((c) => c.id === customerId) : null),
    [customerId, customersData],
  );

  const { data: loyaltyData } = useQuery<LoyaltyData>({
    queryKey: ["pos-customer-loyalty", customerId],
    queryFn: () => api<LoyaltyData>(`/customers/${customerId}/loyalty`),
    enabled: !!customerId,
  });

  const loyaltyPoints = loyaltyData?.data?.points ?? selectedCustomer?.loyalty_points ?? 0;

  const createCustomerMutation = useMutation({
    mutationFn: () =>
      api<{ data: Customer }>("/customers", {
        method: "POST",
        body: {
          name: newCustomerName,
          phone: newCustomerPhone || undefined,
        },
      }),
    onSuccess: (res) => {
      setCustomerId(res.data.id);
      setCustomerSearch("");
      setShowCreateCustomer(false);
      setNewCustomerName("");
      setNewCustomerPhone("");
      qc.invalidateQueries({ queryKey: ["pos-customers"] });
      toast(t("pos.customerSearch.created"), "success");
    },
    onError: (e: unknown) => {
      const detail = e instanceof ApiError ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : "Could not create customer";
      toast(detail, "error");
    },
  });

  const addToCart = useCallback((product: Product, weight?: number | null) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === product.id && i.weight === weight);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id && i.weight === weight ? { ...i, qty: i.qty + 1 } : i,
        );
      }
      const unitPrice = product.is_weighed && weight && product.price_per_unit
        ? product.price_per_unit
        : product.selling_price;
      return [...prev, { product, qty: 1, unit_price: unitPrice, discount: 0, weight: weight ?? null }];
    });
  }, []);

  /** Enter in the search box first tries an exact barcode match (what a scanner "types" +
   *  Enter looks like) before falling back to the live text search already wired to `search`. */
  const tryBarcodeLookup = useCallback(async (code: string) => {
    if (!code.trim()) return;
    try {
      const found = await api<{ data: Product; parsed_weight?: number | null }>(`/products/barcode/${encodeURIComponent(code.trim())}`);
      if (found?.data) {
        addToCart(found.data, found.parsed_weight);
        setSearch("");
        try { navigator.vibrate?.(50); } catch {}
        try {
          const ctx = new AudioContext();
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.frequency.value = 1200;
          gain.gain.value = 0.1;
          osc.start();
          osc.stop(ctx.currentTime + 0.08);
        } catch {}
      }
    } catch {
      toast(`Barcode "${code}" not found`, "error");
      try { navigator.vibrate?.([50, 30, 50]); } catch {}
    }
  }, [addToCart]);

  const voidLastLine = useCallback(() => {
    setCart((prev) => {
      if (prev.length === 0) return prev;
      return prev.slice(0, -1);
    });
  }, []);

  const holdSale = useCallback(() => {
    toast("Hold sale coming soon (G6)");
  }, []);

  const clearCart = useCallback(() => {
    if (cart.length === 0) return;
    if (!window.confirm("Clear all items from the cart?")) return;
    setCart([]);
    setCustomerId(null);
    setCustomerSearch("");
    toast("Cart cleared");
  }, [cart.length]);

  useEffect(() => {
    searchInputRef.current?.focus();
  }, []);

  const updateQty = (id: string, qty: number) => {
    if (qty <= 0) {
      setCart((prev) => prev.filter((i) => i.product.id !== id));
    } else {
      setCart((prev) => prev.map((i) => (i.product.id === id ? { ...i, qty } : i)));
    }
  };
  const updatePrice = (id: string, unit_price: number) =>
    setCart((prev) => prev.map((i) => (i.product.id === id ? { ...i, unit_price: Math.max(0, unit_price) } : i)));
  const updateDiscount = (id: string, discount: number) =>
    setCart((prev) => prev.map((i) => (i.product.id === id ? { ...i, discount: Math.max(0, discount) } : i)));

  const removeItem = (id: string) => setCart((prev) => prev.filter((i) => i.product.id !== id));

  const { cartSubtotal, cartTotal, cartTaxComponents } = useMemo(() => {
    const perLine = cart.map((item) => lineTotals(item, priceDisplay));
    const totals = perLine.reduce(
      (acc, { net, tax }) => ({ net: acc.net + net, tax: acc.tax + tax }),
      { net: 0, tax: 0 },
    );

    return {
      cartSubtotal: round2(totals.net),
      cartTotal: round2(totals.net + totals.tax),
      // Per-component totals across the whole basket — "CGST 9%: ৳18.00 / SGST 9%: ৳18.00"
      // rather than one blended "Tax" line, which is the whole point of the tax engine.
      cartTaxComponents: mergeTaxComponents(perLine.map((l) => l.components)),
    };
  }, [cart, priceDisplay]);

  const checkoutMutation = useMutation({
    mutationFn: ({ payments, approvalToken, tenderCurrency }: { payments: { method: string; amount: number }[]; approvalToken?: string; tenderCurrency?: string }) => {
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
          approval_token: approvalToken ?? null,
          tender_currency: tenderCurrency ?? null,
        },
      });
    },
    onSuccess: (data) => {
      setReceipt(data.data);
      setCart([]);
      setCustomerId(null);
      setCustomerSearch("");
      setShowPayment(false);
      setChallenge(null);
      setPendingPayments([]);
      qc.invalidateQueries({ queryKey: ["products"] });
      qc.invalidateQueries({ queryKey: ["pos-products"] });
      qc.invalidateQueries({ queryKey: ["dashboard-summary"] });
    },
    onError: (e: unknown) => {
      // Over a till limit: the server hands back exactly what needs approving, so the
      // PIN dialog challenges on the server's numbers rather than the cart's.
      const needsApproval = approvalChallengeFrom(e);
      if (needsApproval) {
        setChallenge(needsApproval);
        return;
      }
      if (e instanceof ApiError && e.status === 409) {
        toast("This sale was already processed (duplicate)", "success");
        setCart([]);
        setCustomerId(null);
        setCustomerSearch("");
        setShowPayment(false);
        qc.invalidateQueries({ queryKey: ["products"] });
        qc.invalidateQueries({ queryKey: ["pos-products"] });
        return;
      }
      const detail = e instanceof ApiError ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : "Checkout failed";
      toast(detail, "error");
    },
  });

  const parkMutation = useMutation({
    mutationFn: (approvalToken?: string) => {
      const idempotencyKey = crypto.randomUUID();
      return api("/sales", {
        method: "POST",
        idempotencyKey,
        body: {
          branch_id: user?.branch_id,
          warehouse_id: defaultWarehouse?.id,
          customer_id: customerId,
          status: "held",
          items: cart.map((i) => ({
            product_id: i.product.id,
            quantity: i.qty,
            unit_price: i.unit_price,
            discount_amount: i.discount * i.qty,
          })),
          payments: [],
          approval_token: approvalToken ?? null,
        },
      });
    },
    onSuccess: () => {
      setCart([]);
      setCustomerId(null);
      setCustomerSearch("");
      setChallenge(null);
      qc.invalidateQueries({ queryKey: queryKeys.heldSales.all });
      qc.invalidateQueries({ queryKey: queryKeys.sales.all });
      qc.invalidateQueries({ queryKey: ["products"] });
      qc.invalidateQueries({ queryKey: ["pos-products"] });
      toast(t("pos.parkSuccess"), "success");
    },
    onError: (e: unknown) => {
      const needsApproval = approvalChallengeFrom(e);
      if (needsApproval) {
        setChallenge(needsApproval);
        return;
      }
      const detail = e instanceof ApiError ? e.message : "Could not park sale";
      toast(detail, "error");
    },
  });

  const handleResume = useCallback(
    (items: HeldCartItem[], cid: string | null) => {
      setCart(items.map((i) => ({ product: i.product, qty: i.qty, unit_price: i.unit_price, discount: i.discount })));
      setCustomerId(cid);
      setCustomerSearch("");
      setShowHeld(false);
    },
    [],
  );

  const handlers = useMemo(() => ({
    focusSearch: () => searchInputRef.current?.focus(),
    holdSale,
    parkSale: () => {
      if (cart.length > 0 && !showPayment && !receipt) parkMutation.mutate(undefined);
    },
    openPayment: () => {
      if (cart.length > 0 && !showPayment && !receipt) setShowPayment(true);
    },
    voidLastLine,
    closeModal: () => {
      if (showPayment) setShowPayment(false);
      if (receipt) setReceipt(null);
    },
    clearCart,
    confirmPayment: () => {},
    toggleHelp: () => setShowHelp((v) => !v),
  }), [cart.length, showPayment, receipt, holdSale, voidLastLine, clearCart, parkMutation]);

  const shortcutState = useMemo(() => ({
    cartEmpty: cart.length === 0,
    showPayment,
    showReceipt: !!receipt,
    showHelp,
    showHeld,
  }), [cart.length, showPayment, receipt, showHelp, showHeld]);

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "F2") {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }
      if (e.key === "F4") {
        e.preventDefault();
        if (cart.length > 0 && !showPayment && !receipt && !showHeld) parkMutation.mutate(undefined);
        return;
      }
      if (e.key === "F9") {
        e.preventDefault();
        if (cart.length > 0 && !showPayment && !receipt) setShowPayment(true);
        return;
      }

      const active = document.activeElement;
      const isTypingElsewhere = active instanceof HTMLElement && ["INPUT", "TEXTAREA", "SELECT"].includes(active.tagName);
      if (!isTypingElsewhere && !showPayment && !receipt && !showHeld && /^[a-zA-Z0-9]$/.test(e.key)) {
        searchInputRef.current?.focus();
      }
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [cart.length, showPayment, receipt, showHeld, parkMutation]);

  return (
    <div className="flex h-full gap-4 overflow-hidden">
      {/* Left: product search + grid */}
      <div className="flex flex-1 flex-col min-w-0 gap-3">
        <div className="relative">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
          <Input
            ref={searchInputRef}
            className="ps-9"
            placeholder="Search or scan a barcode… (F2)"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                const now = Date.now();
                const gap = now - lastKeystrokeRef.current;
                lastKeystrokeRef.current = now;
                if (gap < 30 && keystrokeBufferRef.current.length >= 3) {
                  tryBarcodeLookup(keystrokeBufferRef.current);
                  keystrokeBufferRef.current = "";
                  return;
                }
                tryBarcodeLookup(search);
                keystrokeBufferRef.current = "";
                return;
              }
              if (e.key.length === 1) {
                const now = Date.now();
                const gap = now - lastKeystrokeRef.current;
                lastKeystrokeRef.current = now;
                if (gap < 30) {
                  keystrokeBufferRef.current += e.key;
                } else {
                  keystrokeBufferRef.current = e.key;
                }
              }
            }}
            autoFocus
          />
        </div>

        <div className="flex items-center gap-2">
          <div className="flex-1">
            <CategoryTabs
              categories={categoriesData?.data ?? []}
              activeCategoryId={activeCategoryId}
              onSelect={setActiveCategoryId}
              favoritesCount={favorites.size}
            />
          </div>
          <ViewToggle value={viewMode} onChange={setViewMode} />
        </div>

        <div className="flex-1 overflow-y-auto">
          {productsLoading ? (
            <div className={cn(viewMode === "grid" ? "grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" : "space-y-1")}>
              {Array.from({ length: 12 }).map((_, i) => (
                <Skeleton key={i} className={cn(viewMode === "grid" ? "h-24 rounded-[var(--radius)]" : "h-12 rounded-[var(--radius)]")} />
              ))}
            </div>
          ) : viewMode === "grid" ? (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
              {displayedProducts.map((p) => (
                <div key={p.id} className="relative group">
                  <button
                    onClick={() => addToCart(p)}
                    disabled={(p.stock ?? 0) <= 0}
                    className={cn(
                      "flex w-full cursor-pointer flex-col rounded-[var(--radius)] bg-[var(--surface)] p-3 text-start",
                      "transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary)]",
                      "enabled:hover:-translate-y-0.5 enabled:hover:bg-[var(--surface-2)] enabled:hover:shadow-md",
                      "enabled:active:translate-y-0 enabled:active:scale-[0.98]",
                      "disabled:cursor-not-allowed disabled:opacity-50",
                    )}
                  >
                    {p.image_url && (
                      <img src={p.image_url} alt="" className="mb-2 h-16 w-full rounded object-cover" />
                    )}
                    <p className="line-clamp-2 text-sm font-medium transition-colors group-enabled:group-hover:text-[var(--primary)]">{p.name}</p>
                    {p.category && <p className="mt-0.5 text-xs text-[var(--muted)]">{p.category.name}</p>}
                    <p className="mt-2 font-semibold text-[var(--primary)]">{formatCurrency(p.selling_price)}</p>
                    <div className="flex items-center gap-1.5">
                      <p className={cn("text-xs", (p.stock ?? 0) <= 0 ? "text-[var(--color-danger)]" : (p.min_stock_level && (p.stock ?? 0) <= p.min_stock_level) ? "text-amber-600" : "text-[var(--muted)]")}>
                        Stock: {p.stock ?? 0}
                      </p>
                      {(p.min_stock_level ?? 0) > 0 && (p.stock ?? 0) <= (p.min_stock_level ?? 0) && (p.stock ?? 0) > 0 && (
                        <span className="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Low</span>
                      )}
                    </div>
                  </button>
                  <button
                    onClick={(e) => { e.stopPropagation(); toggleFavorite(p.id); }}
                    className="absolute top-1.5 right-1.5 h-6 w-6 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer bg-[var(--surface)]/80"
                  >
                    <Star className={cn("h-3.5 w-3.5", favorites.has(p.id) ? "fill-amber-400 text-amber-400" : "text-[var(--muted)]")} />
                  </button>
                </div>
              ))}
              {displayedProducts.length === 0 && (
                <div className="col-span-4 py-12 text-center text-sm text-[var(--muted)]">
                  No products found
                </div>
              )}
            </div>
          ) : (
            <div className="space-y-1">
              {displayedProducts.map((p) => (
                <div key={p.id} className="relative group flex items-center gap-3 rounded-[var(--radius)] bg-[var(--surface)] p-2 cursor-pointer transition-colors hover:bg-[var(--surface-2)]" onClick={() => addToCart(p)}>
                  {p.image_url ? (
                    <img src={p.image_url} alt="" className="h-10 w-10 rounded object-cover shrink-0" />
                  ) : (
                    <div className="h-10 w-10 rounded bg-[var(--surface-2)] shrink-0" />
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{p.name}</p>
                    {p.category && <p className="text-xs text-[var(--muted)]">{p.category.name}</p>}
                  </div>
                  <p className="text-sm font-semibold text-[var(--primary)] shrink-0">{formatCurrency(p.selling_price)}</p>
                  <p className={cn("text-xs shrink-0", (p.stock ?? 0) <= 0 ? "text-[var(--color-danger)]" : "text-[var(--muted)]")}>
                    Stock: {p.stock ?? 0}
                  </p>
                  <button
                    onClick={(e) => { e.stopPropagation(); toggleFavorite(p.id); }}
                    className="h-6 w-6 flex items-center justify-center shrink-0 cursor-pointer"
                  >
                    <Star className={cn("h-3.5 w-3.5", favorites.has(p.id) ? "fill-amber-400 text-amber-400" : "text-[var(--muted)]")} />
                  </button>
                </div>
              ))}
              {displayedProducts.length === 0 && (
                <div className="py-12 text-center text-sm text-[var(--muted)]">
                  No products found
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Right: cart */}
      <div className="flex w-80 shrink-0 flex-col rounded-[var(--radius)] bg-[var(--surface)]">        <div className="flex items-center justify-between border-b border-border/50 px-4 py-3">
          <button
            onClick={() => setShowHeld(true)}
            className="flex items-center gap-1.5 cursor-pointer text-xs font-medium text-[var(--muted)] transition-colors hover:text-[var(--primary)]"
          >
            <Pause className="h-3.5 w-3.5" />
            {t("pos.heldOrders.button", { count: heldCount })}
          </button>
        </div>
        <div className="flex items-center justify-between border-b border-border/50 px-4 py-3">
          <div className="flex items-center gap-2 font-semibold">
            <ShoppingCart className="h-4 w-4" />
            Cart
            {cart.length > 0 && (
              <Badge tone="info">{cart.reduce((s, i) => s + i.qty, 0)}</Badge>
            )}
          </div>
          {cart.length > 0 && (
            <button onClick={() => setCart([])} className="cursor-pointer text-xs text-[var(--muted)] transition-colors hover:text-[var(--color-danger)]">
              Clear
            </button>
          )}
        </div>

        {/* Customer selector */}
        <div className="border-b border-border/50 p-3">
          <div className="relative">
            <User className="absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--muted)]" />
            <Input
              className="ps-8 h-8 text-xs"
              placeholder={selectedCustomer ? selectedCustomer.name : t("pos.customerSearch.placeholder")}
              value={selectedCustomer ? selectedCustomer.name : customerSearch}
              onChange={(e) => { setCustomerSearch(e.target.value); setCustomerId(null); setShowCreateCustomer(false); }}
            />
            {customerId && (
              <button className="absolute end-2.5 top-1/2 -translate-y-1/2 cursor-pointer" onClick={() => { setCustomerId(null); setCustomerSearch(""); }}>
                <X className="h-3.5 w-3.5 text-[var(--muted)]" />
              </button>
            )}
          </div>
          {selectedCustomer && (
            <div className="mt-1.5 flex items-center gap-1.5 flex-wrap">
              {loyaltyPoints > 0 && (
                <Badge tone="info" className="text-[10px]">{loyaltyPoints} {t("pos.customerSearch.points")}</Badge>
              )}
              {selectedCustomer.balance_due > 0 && (
                <Badge tone="warning" className="text-[10px]">{t("pos.customerSearch.owing")}</Badge>
              )}
            </div>
          )}
          {customerSearch && !customerId && customersData?.data && customersData.data.length > 0 && (
            <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] shadow-lg">
              {customersData.data.map((c) => (
                <button
                  key={c.id}
                  onClick={() => { setCustomerId(c.id); setCustomerSearch(""); setShowCreateCustomer(false); }}
                  className="w-full cursor-pointer px-3 py-2 text-start text-sm transition-colors hover:bg-[var(--surface-2)]"
                >
                  {c.name} {c.phone && <span className="text-[var(--muted)]">· {c.phone}</span>}
                </button>
              ))}
            </div>
          )}
          {customerSearch && !customerId && customersData?.data && customersData.data.length === 0 && !showCreateCustomer && (
            <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] shadow-lg">
              <p className="px-3 py-2 text-xs text-[var(--muted)]">{t("pos.customerSearch.noMatch")}</p>
              <button
                onClick={() => {
                  setShowCreateCustomer(true);
                  setNewCustomerName(customerSearch);
                  setNewCustomerPhone("");
                }}
                className="w-full cursor-pointer border-t border-[var(--border)] px-3 py-2 text-start text-sm font-medium text-[var(--primary)] transition-colors hover:bg-[var(--surface-2)]"
              >
                <UserPlus className="inline h-3.5 w-3.5 me-1" />
                {t("pos.customerSearch.create")} &ldquo;{customerSearch}&rdquo;
              </button>
            </div>
          )}
          {showCreateCustomer && (
            <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] p-3 shadow-lg space-y-2">
              <Input
                className="h-8 text-xs"
                placeholder="Name"
                value={newCustomerName}
                onChange={(e) => setNewCustomerName(e.target.value)}
                autoFocus
              />
              <Input
                className="h-8 text-xs"
                placeholder="Phone (optional)"
                value={newCustomerPhone}
                onChange={(e) => setNewCustomerPhone(e.target.value)}
              />
              <div className="flex gap-2">
                <Button
                  size="sm"
                  className="flex-1 h-7 text-xs"
                  disabled={!newCustomerName.trim() || createCustomerMutation.isPending}
                  onClick={() => createCustomerMutation.mutate()}
                >
                  {createCustomerMutation.isPending ? t("common.saving") : t("pos.customerSearch.create")}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  className="h-7 text-xs"
                  onClick={() => { setShowCreateCustomer(false); setNewCustomerName(""); setNewCustomerPhone(""); }}
                >
                  {t("common.cancel")}
                </Button>
              </div>
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
              <div
                key={item.product.id}
                className="rounded-[var(--radius)] border border-[var(--border)] p-2.5 transition-colors hover:border-[var(--primary)] hover:bg-[var(--surface-2)]"
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="flex-1 text-sm font-medium leading-tight">{item.product.name}</p>
                  <button onClick={() => removeItem(item.product.id)} className="cursor-pointer">
                    <X className="h-3.5 w-3.5 text-[var(--muted)] hover:text-[var(--color-danger)]" />
                  </button>
                </div>
                {item.weight != null && (
                  <p className="text-xs text-[var(--muted)]">{t("pos.weight")}: {item.weight.toFixed(3)} {item.product.weight_unit}</p>
                )}
                <div className="mt-2 flex items-center justify-between">
                  <div className="flex items-center gap-1">
                    <Button variant="outline" size="icon" className="h-6 w-6" onClick={() => updateQty(item.product.id, item.qty - 1)}>
                      <Minus className="h-3 w-3" />
                    </Button>
                    <QuantityPopover
                      value={item.qty}
                      onChange={(qty) => updateQty(item.product.id, qty)}
                    />
                    <Button variant="outline" size="icon" className="h-6 w-6" onClick={() => updateQty(item.product.id, item.qty + 1)}>
                      <Plus className="h-3 w-3" />
                    </Button>
                  </div>
                  <span className="text-sm font-semibold">{formatCurrency(item.unit_price * item.qty)}</span>
                </div>
                {priceDisplay === "inclusive" && item.product.tax_rate > 0 && (
                  <div className="mt-0.5 text-xs text-[var(--muted)] text-end">
                    net {formatCurrency(lineTotals(item, priceDisplay).net)}
                  </div>
                )}
                {(canOverridePrice || canDiscount) && (
                  <div className="mt-2 flex items-center gap-2 text-xs">
                    {canOverridePrice && (
                      <label className="flex flex-1 items-center gap-1 text-[var(--muted)]">
                        Price
                        <Input
                          type="number"
                          min={0}
                          step="0.01"
                          value={item.unit_price}
                          onChange={(e) => updatePrice(item.product.id, Number(e.target.value))}
                          className="h-7 px-2 text-xs"
                        />
                      </label>
                    )}
                    {canDiscount && (
                      <label className="flex flex-1 items-center gap-1 text-[var(--muted)]">
                        Discount/unit
                        <Input
                          type="number"
                          min={0}
                          step="0.01"
                          value={item.discount}
                          onChange={(e) => updateDiscount(item.product.id, Number(e.target.value))}
                          className="h-7 px-2 text-xs"
                        />
                      </label>
                    )}
                  </div>
                )}
              </div>
            ))
          )}
        </div>

        {/* Cart footer */}
        <div className="sticky bottom-0 border-t border-[var(--border)] bg-[var(--surface)] p-4 space-y-3">
          <div className="flex justify-between text-sm">
            <span className="text-[var(--muted)]">Subtotal</span>
            <span>{formatCurrency(cartSubtotal)}</span>
          </div>
          {/* Per-component breakdown, e.g. "CGST 9%" and "SGST 9%" shown separately — not one
              blended "Tax" line. Falls back to a single "Tax" row for legacy single-rate
              products (see lineTotals() above). */}
          {cartTaxComponents.map((c) => (
            <div key={`${c.name}@${c.rate}`} className="flex justify-between text-sm">
              <span className="text-[var(--muted)]">{c.name} {c.rate > 0 ? `(${c.rate}%)` : ""}</span>
              <span>{formatCurrency(c.amount)}</span>
            </div>
          ))}
          <div className="flex justify-between font-semibold">
            <span>Total</span>
            <span>{formatCurrency(cartTotal)}</span>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              className="flex-1"
              disabled={cart.length === 0 || !defaultWarehouse || !user?.branch_id || parkMutation.isPending}
              onClick={() => parkMutation.mutate(undefined)}
            >
              <Pause className="h-4 w-4" />
              {t("pos.park")}
            </Button>
            <Button
              className="flex-1"
              disabled={cart.length === 0 || !defaultWarehouse || !user?.branch_id || checkoutMutation.isPending}
              onClick={() => setShowPayment(true)}
            >
              {t("pos.checkout")} · {formatCurrency(cartTotal)}
            </Button>
          </div>
          {!defaultWarehouse && (
            <p className="text-xs text-center text-[var(--color-danger)]">No warehouse configured</p>
          )}
        </div>
      </div>

      {showPayment && (
        <PaymentModal
          total={cartTotal}
          baseCurrency={businessSettings?.data?.currency ?? "USD"}
          onConfirm={(payments, tenderCurrency) => {
            setPendingPayments(payments);
            checkoutMutation.mutate({ payments, tenderCurrency });
          }}
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

      <ApprovalPinDialog
        challenge={challenge}
        onCancel={() => setChallenge(null)}
        onApproved={(token) => {
          setChallenge(null);
          // A parked cart has no tender; a checkout replays the tender the cashier
          // already confirmed, so the manager never has to re-enter the payment.
          if (showPayment) {
            checkoutMutation.mutate({ payments: pendingPayments, approvalToken: token });
          } else {
            parkMutation.mutate(token);
          }
        }}
      />

      <ShortcutsOverlay open={showHelp} onClose={() => setShowHelp(false)} />
      <HeldOrdersPanel
        open={showHeld}
        onClose={() => setShowHeld(false)}
        onResume={handleResume}
      />
    </div>
  );
}
