"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Receipt, Users, Truck,
  Wallet, BarChart3, Settings, BookOpen, CreditCard, UserCog, Gift, Crown, Bell, X,
} from "lucide-react";
import { cn } from "@/lib/utils";

const nav = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/pos", label: "POS Terminal", icon: ShoppingCart },
  { href: "/products", label: "Products", icon: Package },
  { href: "/inventory", label: "Inventory", icon: Boxes },
  { href: "/sales", label: "Sales", icon: Receipt },
  { href: "/purchases", label: "Purchases", icon: Truck },
  { href: "/customers", label: "Customers", icon: Users },
  { href: "/suppliers", label: "Suppliers", icon: Truck },
  { href: "/expenses", label: "Expenses", icon: Wallet },
  { href: "/reports", label: "Reports", icon: BarChart3 },
  { href: "/accounting", label: "Accounting", icon: BookOpen },
  { href: "/cash-register", label: "Cash Register", icon: CreditCard },
  { href: "/team", label: "Team", icon: UserCog },
  { href: "/loyalty", label: "Loyalty & Coupons", icon: Gift },
  { href: "/subscription", label: "Subscription", icon: Crown },
  { href: "/notifications", label: "Notifications", icon: Bell },
  { href: "/settings", label: "Settings", icon: Settings },
];

interface SidebarProps {
  open?: boolean;
  onClose?: () => void;
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const pathname = usePathname();

  const content = (
    <div className="flex h-full flex-col text-[var(--sidebar-fg)]">
      <div className="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
        <div className="flex items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--primary)] font-bold text-[var(--primary-fg)]">
            S
          </div>
          <span className="text-lg font-semibold">SmartPOS</span>
        </div>
        {onClose && (
          <button onClick={onClose} className="md:hidden rounded p-1 hover:bg-white/10">
            <X className="h-5 w-5" />
          </button>
        )}
      </div>
      <nav className="flex-1 overflow-y-auto space-y-0.5 px-3 py-4">
        {nav.map(({ href, label, icon: Icon }) => {
          const active = pathname === href || pathname.startsWith(href + "/");
          return (
            <Link
              key={href}
              href={href}
              onClick={onClose}
              className={cn(
                "flex items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-sm font-medium transition-colors",
                active
                  ? "bg-[var(--sidebar-active)] text-[var(--sidebar-active-fg)]"
                  : "text-[var(--sidebar-muted)] hover:bg-white/10 hover:text-[var(--sidebar-fg)]",
              )}
            >
              <Icon className="h-4 w-4 shrink-0" />
              {label}
            </Link>
          );
        })}
      </nav>
    </div>
  );

  return (
    <>
      {/* Desktop sidebar */}
      <aside className="hidden w-60 shrink-0 bg-[var(--sidebar-bg)] md:block">{content}</aside>

      {/* Mobile sidebar */}
      {open && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={onClose} />
          <aside className="relative h-full w-64 bg-[var(--sidebar-bg)] shadow-xl">
            {content}
          </aside>
        </div>
      )}
    </>
  );
}
