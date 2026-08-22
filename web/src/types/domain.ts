/**
 * Entity shapes shared by more than one screen.
 *
 * Before this file, `Warehouse` was declared in 6 pages, `Branch` in 5 and
 * `Category`/`Brand`/`Unit`/`Plan` in 2–3 each — each a slightly different subset of the
 * same API resource, so adding a field meant hunting every copy. Page-local shapes that
 * genuinely belong to one screen (a form's draft type, a report row) stay in that page.
 */

export interface Named {
  id: string;
  name: string;
}

export interface Branch extends Named {
  code?: string | null;
  phone?: string | null;
  email?: string | null;
  is_active?: boolean;
  is_default?: boolean;
}

export interface Warehouse extends Named {
  code?: string | null;
  is_active?: boolean;
  is_default?: boolean;
  branch?: Named | null;
}

export interface Category extends Named {
  description?: string | null;
  is_active?: boolean;
  parent?: Named | null;
}

export interface Brand extends Named {
  is_active?: boolean;
}

export interface Unit extends Named {
  short_name?: string;
  operator_value?: number;
  is_active?: boolean;
}

export interface Role extends Named {
  slug: string;
}

export interface PlanFeature {
  key: string;
  label: string;
  /** Quantified features carry their limit here ("5 branches"); flag features omit it. */
  value?: string;
  included: boolean;
}

export interface Plan extends Named {
  slug: string;
  price: number;
  interval: string;
  limits: Record<string, number | null>;
  features: PlanFeature[];
  is_active: boolean;
}

export interface Tenant extends Named {
  slug: string;
  status: "active" | "suspended";
  created_at: string;
  updated_at: string;
  users_count?: number;
  businesses_count?: number;
}

export interface TenantDetail extends Tenant {
  businesses?: { id: string; name: string; currency: string }[];
  subscription: {
    id: string;
    status: string;
    plan: { id: string; name: string; slug: string; price: number } | null;
    created_at: string;
  } | null;
  usage: {
    sales_count: number;
    users_count: number;
  };
}

export interface PlanDistribution {
  plan_name: string;
  slug: string;
  count: number;
  mrr: number;
}

export interface RevenueMetrics {
  total_mrr: number;
  active_tenants: number;
  suspended_tenants: number;
  new_tenants_last_days: number;
  plan_distribution: PlanDistribution[];
  recent_subscriptions: Array<{
    id: string;
    status: string;
    created_at: string;
    plan: { name: string; slug: string; price: number } | null;
  }>;
}

export interface Impersonation {
  id: string;
  tenant_id: string;
  super_admin_id: string;
  target_user_id: string;
  reason: string;
  started_at: string;
  ended_at: string | null;
  ended_by: string | null;
  tenant?: { id: string; name: string };
  superAdmin?: { id: string; name: string; email: string };
  targetUser?: { id: string; name: string; email: string };
}
