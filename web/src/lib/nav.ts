import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Receipt, Users, Truck,
  Wallet, BarChart3, Settings, BookOpen, CreditCard, UserCog, Gift, Crown, Bell, ScrollText, Percent,
  LayoutGrid, Tag, TruckIcon, UsersRound, Landmark, PieChart, CircleUserRound, FlaskConical,
} from "lucide-react";

export interface NavItem {
  href: string;
  /** i18n key under the `nav` namespace, e.g. "dashboard" -> t("nav.dashboard"). */
  key: string;
  icon: React.ElementType;
}

export interface NavGroup {
  /** i18n key under the `navGroups` namespace. */
  key: string;
  /** Icon displayed next to the group label in the sidebar. */
  groupIcon: React.ElementType;
  items: NavItem[];
}

/** Single source of truth for app routes, grouped for the sidebar. AppLayout derives page
 *  titles from the flattened list, so titles and sidebar never drift out of sync. */
export const NAV_GROUPS: NavGroup[] = [
  {
    key: "overview",
    groupIcon: LayoutGrid,
    items: [{ href: "/dashboard", key: "dashboard", icon: LayoutDashboard }],
  },
  {
    key: "sales",
    groupIcon: Tag,
    items: [
      { href: "/pos", key: "pos", icon: ShoppingCart },
      { href: "/sales", key: "sales", icon: Receipt },
      { href: "/cash-register", key: "cashRegister", icon: CreditCard },
    ],
  },
  {
    key: "catalog",
    groupIcon: Package,
    items: [
      { href: "/products", key: "products", icon: Package },
      { href: "/inventory", key: "inventory", icon: Boxes },
    ],
  },
  {
    key: "purchasing",
    groupIcon: TruckIcon,
    items: [
      { href: "/purchases", key: "purchases", icon: Truck },
      { href: "/suppliers", key: "suppliers", icon: Truck },
    ],
  },
  {
    key: "people",
    groupIcon: UsersRound,
    items: [
      { href: "/customers", key: "customers", icon: Users },
      { href: "/team", key: "team", icon: UserCog },
    ],
  },
  {
    key: "finance",
    groupIcon: Landmark,
    items: [
      { href: "/expenses", key: "expenses", icon: Wallet },
      { href: "/accounting", key: "accounting", icon: BookOpen },
    ],
  },
  {
    key: "insights",
    groupIcon: PieChart,
    items: [
      // More specific routes are listed before their parent so navKeyForPathname()
      // (a first-match .find()) resolves the page title correctly.
      { href: "/reports/tax", key: "taxReport", icon: Percent },
      { href: "/reports", key: "reports", icon: BarChart3 },
      { href: "/loyalty", key: "loyalty", icon: Gift },
    ],
  },
  {
    key: "account",
    groupIcon: CircleUserRound,
    items: [
      { href: "/subscription", key: "subscription", icon: Crown },
      { href: "/notifications", key: "notifications", icon: Bell },
      { href: "/audit-log", key: "auditLog", icon: ScrollText },
      { href: "/settings/tax", key: "taxSettings", icon: Percent },
      { href: "/settings", key: "settings", icon: Settings },
      { href: "/test-data", key: "testData", icon: FlaskConical },
    ],
  },
];

/** Flattened route list, derived from NAV_GROUPS so both stay in sync. */
export const NAV_ITEMS: NavItem[] = NAV_GROUPS.flatMap((g) => g.items);

/** Looks up the `nav.*` i18n key for the route a pathname belongs to. */
export function navKeyForPathname(pathname: string): string | undefined {
  return NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))?.key;
}
