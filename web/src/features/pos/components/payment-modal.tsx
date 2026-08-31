import { Plus, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { toast } from "@/components/ui/toast";
import { PaymentNumpad } from "@/features/pos/components/payment-numpad";
import { round2 } from "@/features/pos/totals";
import type { Payment } from "@/features/pos/types";
import { useTranslation } from "@/i18n/context";
import { formatCurrency } from "@/lib/utils";

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

interface PaymentRow {
  id: string;
  method: string;
  amount: string;
}
let paymentRowSeq = 0;

/** Split tender: the checkout endpoint already accepts a `payments` array (e.g. part cash,
 *  part card on one sale) — this was previously a UI gap where only a single row could be
 *  entered. Row 1 defaults to the full amount due so the common single-method case is still
 *  a one-field flow; "Add payment" only shows up when it's actually needed. */
export function PaymentModal({
  total,
  onConfirm,
  onClose,
  loading,
  baseCurrency,
}: {
  total: number;
  onConfirm: (payments: Payment[], tenderCurrency?: string) => void;
  onClose: () => void;
  loading: boolean;
  baseCurrency?: string;
}) {
  const t = useTranslation();
  const [rows, setRows] = useState<PaymentRow[]>(() => [
    { id: String(++paymentRowSeq), method: "cash", amount: total.toFixed(2) },
  ]);
  const [tenderCurrency, setTenderCurrency] = useState<string>("");
  const tenderAmount = rows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);

  const effectiveCurrency = tenderCurrency || baseCurrency || "USD";
  const isForeign = tenderCurrency && tenderCurrency !== (baseCurrency || "USD");

  const remaining = round2(total - tenderAmount);

  const addRow = () =>
    setRows((prev) => [
      ...prev,
      {
        id: String(++paymentRowSeq),
        method: "cash",
        amount: remaining > 0 ? remaining.toFixed(2) : "0",
      },
    ]);

  const removeRow = (id: string) =>
    setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.id !== id) : prev));
  const updateRow = (id: string, patch: Partial<Omit<PaymentRow, "id">>) =>
    setRows((prev) => prev.map((r) => (r.id === id ? { ...r, ...patch } : r)));

  const handleSubmit = () => {
    const payments = rows
      .map((r) => ({ method: r.method, amount: round2(parseFloat(r.amount) || 0) }))
      .filter((p) => p.amount > 0);
    if (payments.length === 0) {
      toast("Enter a valid amount", "error");
      return;
    }
    onConfirm(payments, tenderCurrency || undefined);
  };

  return (
    <Dialog open title="Payment" onClose={onClose}>
      <div className="space-y-4">
        <div>
          <Label>Amount due</Label>
          <p className="text-2xl font-bold">
            {formatCurrency(total)}{" "}
            {baseCurrency && (
              <span className="text-sm font-normal text-[var(--muted)]">{baseCurrency}</span>
            )}
          </p>
          {isForeign && (
            <p className="text-sm text-[var(--muted)]">
              ~ {formatCurrency(round2(total))} {effectiveCurrency}
            </p>
          )}
        </div>

        <div>
          <Label>{t("currency.tender")}</Label>
          <Select value={tenderCurrency} onChange={(e) => setTenderCurrency(e.target.value)}>
            <option value="">
              {baseCurrency || "USD"} ({t("currency.base")})
            </option>
            {CURRENCIES.filter((c) => c.code !== (baseCurrency || "USD")).map((c) => (
              <option key={c.code} value={c.code}>
                {c.code} — {c.name}
              </option>
            ))}
          </Select>
        </div>

        <div className="space-y-2">
          {rows.map((row, i) => (
            <div key={row.id} className="space-y-1">
              <div className="flex items-center gap-2">
                <Select
                  className="w-40 shrink-0"
                  value={row.method}
                  onChange={(e) => updateRow(row.id, { method: e.target.value })}
                >
                  {PAYMENT_METHODS.map((m) => (
                    <option key={m.value} value={m.value}>
                      {m.label}
                    </option>
                  ))}
                </Select>
                <Input
                  type="number"
                  step="0.01"
                  value={row.amount}
                  onChange={(e) => updateRow(row.id, { amount: e.target.value })}
                  autoFocus={i === 0}
                />
                {rows.length > 1 && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-10 w-10 shrink-0 text-[var(--color-danger)]"
                    onClick={() => removeRow(row.id)}
                  >
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
          <p className="text-sm text-amber-600">
            Remaining: {formatCurrency(remaining)} (recorded as due if you confirm now)
          </p>
        ) : remaining < -0.001 ? (
          <p className="text-sm text-green-600">Change: {formatCurrency(-remaining)}</p>
        ) : null}

        <div className="flex gap-2">
          <Button variant="outline" onClick={onClose} disabled={loading} className="flex-1">
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading} className="flex-1">
            {loading ? "Processing…" : "Confirm payment"}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
