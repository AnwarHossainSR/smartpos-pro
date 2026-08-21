import { useEffect, useMemo, useRef, useState, useCallback } from "react";
import { useNavigate } from "react-router";
import { permissionsForPathname } from "@/lib/permissions";
import { hasPermission, useAuth } from "@/stores/auth";
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Receipt, Users, Truck,
  Wallet, BarChart3, Settings, BookOpen, CreditCard, UserCog, Gift, Crown, Bell,
  Layers, Building2, Mail, Percent, Webhook, ScrollText, LifeBuoy, Barcode, ShieldCheck,
  Grid2x2, ClipboardList, AlertTriangle,
} from "lucide-react";

interface NavItem {
  label: string;
  href: string;
  icon: React.ElementType;
  keywords?: string;
}

const items: NavItem[] = [
  { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { label: "POS Terminal", href: "/pos", icon: ShoppingCart, keywords: "point of sale checkout" },
  { label: "Products", href: "/products", icon: Package, keywords: "catalog items sku" },
  { label: "Print barcode labels", href: "/products/labels", icon: Barcode, keywords: "label sheet price tag shelf edge barcode print ean13 code128 avery" },
  { label: "Inventory", href: "/inventory", icon: Boxes, keywords: "stock balance" },
  { label: "Sales", href: "/sales", icon: Receipt, keywords: "orders transactions" },
  { label: "Purchases", href: "/purchases", icon: Truck, keywords: "purchase orders suppliers" },
  { label: "Customers", href: "/customers", icon: Users },
  { label: "Suppliers", href: "/suppliers", icon: Truck },
  { label: "Expenses", href: "/expenses", icon: Wallet },
  { label: "Reports", href: "/reports", icon: BarChart3, keywords: "analytics charts" },
  { label: "Accounting", href: "/accounting", icon: BookOpen, keywords: "journals ledger" },
  { label: "Cash Register", href: "/cash-register", icon: CreditCard },
  { label: "Team", href: "/team", icon: UserCog, keywords: "users roles" },
  { label: "Loyalty & Coupons", href: "/loyalty", icon: Gift, keywords: "points discounts promo" },
  { label: "Subscription", href: "/subscription", icon: Crown, keywords: "plan billing" },
  { label: "Notifications", href: "/notifications", icon: Bell },
  { label: "Settings", href: "/settings", icon: Settings },
  { label: "Categories & brands", href: "/catalog", icon: Layers, keywords: "category brand unit catalog setup" },
  { label: "Branches & warehouses", href: "/settings/locations", icon: Building2, keywords: "branch warehouse location store" },
  { label: "Email templates", href: "/settings/email-templates", icon: Mail, keywords: "email template message" },
  { label: "Approval limits", href: "/settings/approvals", icon: ShieldCheck, keywords: "manager pin supervisor discount limit override void approval" },
  { label: "Tax settings", href: "/settings/tax", icon: Percent, keywords: "vat gst tax rate group" },
  { label: "Webhooks", href: "/settings/webhooks", icon: Webhook, keywords: "integration events" },
  { label: "Dining tables", href: "/tables", icon: Grid2x2, keywords: "floor plan restaurant seats table" },
  { label: "Stocktakes", href: "/stocktakes", icon: ClipboardList, keywords: "count cycle count stock take variance" },
  { label: "Reorder suggestions", href: "/reorder-suggestions", icon: AlertTriangle, keywords: "restock low stock replenish purchase" },
  { label: "Audit log", href: "/audit-log", icon: ScrollText, keywords: "history changes" },
  { label: "Support", href: "/support", icon: LifeBuoy, keywords: "help ticket" },
  { label: "Tax report", href: "/reports/tax", icon: Percent, keywords: "vat return filing" },
];

interface CommandPaletteProps {
  open: boolean;
  onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [index, setIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  // The palette is navigation, so it hides the same destinations the sidebar does — jumping
  // to a page the route guard will refuse is worse than the command not existing.
  const user = useAuth((s) => s.user);
  const allowed = useMemo(
    () =>
      items.filter((it) => {
        const required = permissionsForPathname(it.href);
        return required.length === 0 || required.some((slug) => hasPermission(user, slug));
      }),
    [user],
  );

  const filtered = query
    ? allowed.filter((it) =>
        `${it.label} ${it.keywords ?? ""}`.toLowerCase().includes(query.toLowerCase()),
      )
    : allowed;

  // Reset search + selection when the palette opens, and selection whenever the query
  // changes — adjusted directly during render (React's documented pattern for deriving state
  // from a prop/state change) rather than in an effect, since nothing here is an external
  // side effect. Only the input focus below is, so that stays in a real effect. (Refs can't be
  // read during render either, so the "previous value" has to live in state, not a ref.)
  const [prevOpen, setPrevOpen] = useState(open);
  if (open !== prevOpen) {
    setPrevOpen(open);
    if (open) {
      setQuery("");
      setIndex(0);
    }
  }
  const [prevQuery, setPrevQuery] = useState(query);
  if (query !== prevQuery) {
    setPrevQuery(query);
    setIndex(0);
  }

  useEffect(() => {
    if (open) {
      const id = setTimeout(() => inputRef.current?.focus(), 10);
      return () => clearTimeout(id);
    }
  }, [open]);

  const onNavigate = useCallback(
    (href: string) => {
      navigate(href);
      onClose();
    },
    [navigate, onClose],
  );

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (!open) return;
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowDown") { e.preventDefault(); setIndex((i) => Math.min(i + 1, filtered.length - 1)); }
      if (e.key === "ArrowUp") { e.preventDefault(); setIndex((i) => Math.max(i - 1, 0)); }
      if (e.key === "Enter") { e.preventDefault(); if (filtered[index]) onNavigate(filtered[index].href); }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, filtered, index, onNavigate, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-lg rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] shadow-2xl overflow-hidden">
        <input
          ref={inputRef}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search pages…"
          className="w-full border-b border-[var(--border)] bg-transparent px-4 py-3.5 text-sm outline-none placeholder:text-[var(--muted)]"
        />
        <ul className="max-h-72 overflow-y-auto py-1">
          {filtered.length === 0 ? (
            <li className="px-4 py-3 text-sm text-[var(--muted)]">No results</li>
          ) : (
            filtered.map((it, i) => {
              const Icon = it.icon;
              return (
                <li key={it.href}>
                  <button
                    className={`flex w-full items-center gap-3 px-4 py-2.5 text-sm text-start transition-colors ${
                      i === index ? "bg-[var(--primary)] text-[var(--primary-fg)]" : "hover:bg-[var(--surface-2)]"
                    }`}
                    onMouseEnter={() => setIndex(i)}
                    onClick={() => onNavigate(it.href)}
                  >
                    <Icon className="h-4 w-4 shrink-0" />
                    {it.label}
                  </button>
                </li>
              );
            })
          )}
        </ul>
        <div className="border-t border-[var(--border)] px-4 py-2 text-xs text-[var(--muted)] flex gap-4">
          <span><kbd className="font-mono">↑↓</kbd> navigate</span>
          <span><kbd className="font-mono">↵</kbd> open</span>
          <span><kbd className="font-mono">Esc</kbd> close</span>
        </div>
      </div>
    </div>
  );
}
