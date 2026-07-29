import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Receipt, Users, Truck,
  Wallet, BarChart3, Settings, BookOpen, CreditCard, UserCog, Gift, Crown, Bell,
} from "lucide-react";

export interface NavItem {
  href: string;
  /** i18n key under the `nav` namespace, e.g. "dashboard" -> t("nav.dashboard"). */
  key: string;
  icon: React.ElementType;
}

/** Single source of truth for app routes: Sidebar renders this list, AppLayout derives page
 *  titles from it, so the two never drift out of sync. */
export const NAV_ITEMS: NavItem[] = [
  { href: "/dashboard", key: "dashboard", icon: LayoutDashboard },
  { href: "/pos", key: "pos", icon: ShoppingCart },
  { href: "/products", key: "products", icon: Package },
  { href: "/inventory", key: "inventory", icon: Boxes },
  { href: "/sales", key: "sales", icon: Receipt },
  { href: "/purchases", key: "purchases", icon: Truck },
  { href: "/customers", key: "customers", icon: Users },
  { href: "/suppliers", key: "suppliers", icon: Truck },
  { href: "/expenses", key: "expenses", icon: Wallet },
  { href: "/reports", key: "reports", icon: BarChart3 },
  { href: "/accounting", key: "accounting", icon: BookOpen },
  { href: "/cash-register", key: "cashRegister", icon: CreditCard },
  { href: "/team", key: "team", icon: UserCog },
  { href: "/loyalty", key: "loyalty", icon: Gift },
  { href: "/subscription", key: "subscription", icon: Crown },
  { href: "/notifications", key: "notifications", icon: Bell },
  { href: "/settings", key: "settings", icon: Settings },
];

/** Looks up the `nav.*` i18n key for the route a pathname belongs to. */
export function navKeyForPathname(pathname: string): string | undefined {
  return NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))?.key;
}
