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
