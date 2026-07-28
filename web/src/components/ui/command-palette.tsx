"use client";

import { useEffect, useRef, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Receipt, Users, Truck,
  Wallet, BarChart3, Settings, BookOpen, CreditCard, UserCog, Gift, Crown, Bell,
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
];

interface CommandPaletteProps {
  open: boolean;
  onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
  const router = useRouter();
  const [query, setQuery] = useState("");
  const [index, setIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  const filtered = query
    ? items.filter((it) =>
        `${it.label} ${it.keywords ?? ""}`.toLowerCase().includes(query.toLowerCase()),
      )
    : items;

  useEffect(() => {
    if (open) {
      setQuery("");
      setIndex(0);
      setTimeout(() => inputRef.current?.focus(), 10);
    }
  }, [open]);

  useEffect(() => { setIndex(0); }, [query]);

  const navigate = useCallback(
    (href: string) => {
      router.push(href);
      onClose();
    },
    [router, onClose],
  );

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (!open) return;
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowDown") { e.preventDefault(); setIndex((i) => Math.min(i + 1, filtered.length - 1)); }
      if (e.key === "ArrowUp") { e.preventDefault(); setIndex((i) => Math.max(i - 1, 0)); }
      if (e.key === "Enter") { e.preventDefault(); if (filtered[index]) navigate(filtered[index].href); }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, filtered, index, navigate, onClose]);

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
                    className={`flex w-full items-center gap-3 px-4 py-2.5 text-sm text-left transition-colors ${
                      i === index ? "bg-[var(--primary)] text-[var(--primary-fg)]" : "hover:bg-[var(--surface-2)]"
                    }`}
                    onMouseEnter={() => setIndex(i)}
                    onClick={() => navigate(it.href)}
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
