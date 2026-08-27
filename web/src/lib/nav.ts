import {
  Activity,
  AlertTriangle,
  BarChart3,
  Barcode,
  Bell,
  BookOpen,
  Boxes,
  Building2,
  CircleUserRound,
  ClipboardList,
  CreditCard,
  Crown,
  Database,
  DollarSign,
  Gift,
  Headset,
  Landmark,
  Layers,
  LayoutDashboard,
  LayoutGrid,
  Package,
  Percent,
  PieChart,
  Receipt,
  ScrollText,
  Settings,
  Shield,
  ShoppingCart,
  Tag,
  Truck,
  TruckIcon,
  UserCog,
  Users,
  UsersRound,
  Wallet
} from "lucide-react";

export interface NavItem {
  href: string;
  /** i18n key under the `nav` namespace, e.g. "dashboard" -> t("nav.dashboard"). */
  key: string;
  icon: React.ElementType;
  /** Module key — when set the item is only visible if the module is enabled. */
  module?: string;
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
      { href: "/pos", key: "pos", icon: ShoppingCart, module: "pos" },
      { href: "/sales", key: "sales", icon: Receipt, module: "pos" },
      { href: "/cash-register", key: "cashRegister", icon: CreditCard, module: "pos" },
    ],
  },
  {
    key: "catalog",
    groupIcon: Package,
    items: [
      // Listed before /products so navKeyForPathname() titles the labels page correctly.
      { href: "/products/labels", key: "labels", icon: Barcode, module: "inventory" },
      { href: "/products", key: "products", icon: Package, module: "inventory" },
      { href: "/inventory", key: "inventory", icon: Boxes, module: "inventory" },
      { href: "/stocktakes", key: "stocktakes", icon: ClipboardList, module: "inventory" },
      { href: "/catalog", key: "catalogSetup", icon: Layers, module: "inventory" },
    ],
  },
  {
    key: "purchasing",
    groupIcon: TruckIcon,
    items: [
      { href: "/purchases", key: "purchases", icon: Truck, module: "inventory" },
      { href: "/suppliers", key: "suppliers", icon: Truck, module: "inventory" },
      { href: "/reorder-suggestions", key: "reorderSuggestions", icon: AlertTriangle, module: "inventory" },
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
      { href: "/expenses", key: "expenses", icon: Wallet, module: "accounting" },
      { href: "/accounting", key: "accounting", icon: BookOpen, module: "accounting" },
    ],
  },
  {
    key: "insights",
    groupIcon: PieChart,
    items: [
      // More specific routes are listed before their parent so navKeyForPathname()
      // (a first-match .find()) resolves the page title correctly.
      { href: "/reports/tax", key: "taxReport", icon: Percent, module: "reports" },
      { href: "/reports", key: "reports", icon: BarChart3, module: "reports" },
      { href: "/loyalty", key: "loyalty", icon: Gift, module: "loyalty" },
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
      { href: "/settings/locations", key: "locations", icon: Building2 },
      { href: "/settings/security", key: "security", icon: Shield },
      { href: "/settings", key: "settings", icon: Settings },
    ],
  },
];

/** Flattened route list, derived from NAV_GROUPS so both stay in sync. */
export const NAV_ITEMS: NavItem[] = NAV_GROUPS.flatMap((g) => g.items);

export const SUPER_ADMIN_NAV_GROUPS: NavGroup[] = [
  { key: "superAdminDashboard", groupIcon: Shield, items: [{ href: "/super-admin", key: "superAdminDashboard", icon: LayoutDashboard }] },
  { key: "superAdminTenants", groupIcon: Shield, items: [{ href: "/super-admin/tenants", key: "tenants", icon: Building2 }] },
  { key: "superAdminPlans", groupIcon: Shield, items: [{ href: "/super-admin/plans", key: "plans", icon: CreditCard }] },
  { key: "superAdminUsers", groupIcon: Shield, items: [{ href: "/super-admin/users", key: "users", icon: UserCog }] },
  { key: "superAdminRevenue", groupIcon: Shield, items: [{ href: "/super-admin/revenue", key: "revenue", icon: DollarSign }] },
  { key: "superAdminImpersonations", groupIcon: Shield, items: [{ href: "/super-admin/impersonations", key: "impersonations", icon: Users }] },
  { key: "superAdminAudit", groupIcon: Shield, items: [{ href: "/super-admin/audit", key: "auditLogs", icon: ScrollText }] },
  { key: "superAdminSupport", groupIcon: Shield, items: [{ href: "/super-admin/support-tickets", key: "supportTickets", icon: Headset }] },
  { key: "superAdminHealth", groupIcon: Shield, items: [{ href: "/super-admin/health", key: "health", icon: Activity }] },
  { key: "superAdminSettings", groupIcon: Shield, items: [{ href: "/super-admin/settings", key: "platformSettings", icon: Settings }] },
  { key: "superAdminBackups", groupIcon: Shield, items: [{ href: "/super-admin/backups", key: "databaseBackups", icon: Database }] },
];

export const SUPER_ADMIN_NAV_ITEMS: NavItem[] = SUPER_ADMIN_NAV_GROUPS.flatMap((g) => g.items);

/** Combined nav items for title lookup — includes super-admin items. */
export const ALL_NAV_ITEMS: NavItem[] = [...NAV_ITEMS, ...SUPER_ADMIN_NAV_ITEMS];

/** Looks up the `nav.*` i18n key for the route a pathname belongs to. */
export function navKeyForPathname(pathname: string): string | undefined {
  return ALL_NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))?.key;
}

export function superAdminNavKeyForPathname(pathname: string): string | undefined {
  return SUPER_ADMIN_NAV_ITEMS.find(
    (item) => pathname === item.href || pathname.startsWith(`${item.href}/`),
  )?.key;
}
