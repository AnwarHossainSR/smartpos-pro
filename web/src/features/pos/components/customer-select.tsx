import { User, UserPlus, X } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  useCreateCustomer,
  useCustomerLoyalty,
  useCustomerSearch,
} from "@/features/pos/hooks/use-pos-customers";
import { useTranslation } from "@/i18n/context";

interface CustomerSelectProps {
  customerId: string | null;
  search: string;
  onSearchChange: (value: string) => void;
  onSelect: (customerId: string | null) => void;
}

/** Attach a customer to the sale — search, pick, or create one without leaving the till. */
export function CustomerSelect({
  customerId,
  search,
  onSearchChange,
  onSelect,
}: CustomerSelectProps) {
  const t = useTranslation();
  const [showCreate, setShowCreate] = useState(false);
  const [newName, setNewName] = useState("");
  const [newPhone, setNewPhone] = useState("");

  const { results } = useCustomerSearch(search);
  const selected = customerId ? results?.find((c) => c.id === customerId) : null;
  const loyaltyPoints = useCustomerLoyalty(customerId) ?? selected?.loyalty_points ?? 0;

  const closeCreateForm = () => {
    setShowCreate(false);
    setNewName("");
    setNewPhone("");
  };

  const createCustomer = useCreateCustomer((customer) => {
    onSelect(customer.id);
    onSearchChange("");
    closeCreateForm();
  });

  return (
    <div className="border-b border-[var(--border)] p-3">
      <div className="relative">
        <User className="absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--muted)]" />
        <Input
          className="ps-8 h-8 text-xs"
          placeholder={selected ? selected.name : t("pos.customerSearch.placeholder")}
          value={selected ? selected.name : search}
          onChange={(e) => {
            onSearchChange(e.target.value);
            onSelect(null);
            setShowCreate(false);
          }}
        />
        {customerId && (
          <button
            className="absolute end-2.5 top-1/2 -translate-y-1/2 cursor-pointer"
            onClick={() => {
              onSelect(null);
              onSearchChange("");
            }}
          >
            <X className="h-3.5 w-3.5 text-[var(--muted)]" />
          </button>
        )}
      </div>

      {selected && (
        <div className="mt-1.5 flex items-center gap-1.5 flex-wrap">
          {loyaltyPoints > 0 && (
            <Badge tone="info" className="text-[10px]">
              {loyaltyPoints} {t("pos.customerSearch.points")}
            </Badge>
          )}
          {selected.balance_due > 0 && (
            <Badge tone="warning" className="text-[10px]">
              {t("pos.customerSearch.owing")}
            </Badge>
          )}
        </div>
      )}

      {search && !customerId && results && results.length > 0 && (
        <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] shadow-lg">
          {results.map((c) => (
            <button
              key={c.id}
              onClick={() => {
                onSelect(c.id);
                onSearchChange("");
                setShowCreate(false);
              }}
              className="w-full cursor-pointer px-3 py-2 text-start text-sm transition-colors hover:bg-[var(--surface-2)]"
            >
              {c.name} {c.phone && <span className="text-[var(--muted)]">· {c.phone}</span>}
            </button>
          ))}
        </div>
      )}

      {search && !customerId && results && results.length === 0 && !showCreate && (
        <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] shadow-lg">
          <p className="px-3 py-2 text-xs text-[var(--muted)]">{t("pos.customerSearch.noMatch")}</p>
          <button
            onClick={() => {
              setShowCreate(true);
              setNewName(search);
              setNewPhone("");
            }}
            className="w-full cursor-pointer border-t border-[var(--border)] px-3 py-2 text-start text-sm font-medium text-[var(--primary)] transition-colors hover:bg-[var(--surface-2)]"
          >
            <UserPlus className="inline h-3.5 w-3.5 me-1" />
            {t("pos.customerSearch.create")} &ldquo;{search}&rdquo;
          </button>
        </div>
      )}

      {showCreate && (
        <div className="mt-1 rounded border border-[var(--border)] bg-[var(--surface)] p-3 shadow-lg space-y-2">
          <Input
            className="h-8 text-xs"
            placeholder="Name"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            autoFocus
          />
          <Input
            className="h-8 text-xs"
            placeholder="Phone (optional)"
            value={newPhone}
            onChange={(e) => setNewPhone(e.target.value)}
          />
          <div className="flex gap-2">
            <Button
              size="sm"
              className="flex-1 h-7 text-xs"
              disabled={!newName.trim() || createCustomer.isPending}
              onClick={() => createCustomer.mutate({ name: newName, phone: newPhone || undefined })}
            >
              {createCustomer.isPending ? t("common.saving") : t("pos.customerSearch.create")}
            </Button>
            <Button size="sm" variant="outline" className="h-7 text-xs" onClick={closeCreateForm}>
              {t("common.cancel")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
