export type { Tenant, TenantDetail, RevenueMetrics, Impersonation, PlanDistribution, PlatformUser, AuditLog } from "@/types/domain";

export interface SuperAdminPlan {
  id: string;
  name: string;
  slug: string;
  price: number;
  currency: string;
  trial_days?: number;
  limits?: Record<string, unknown>;
  features?: string[];
  is_active: boolean;
  created_at: string;
}

export interface SystemHealth {
  php_version: string;
  laravel_version: string;
  database: {
    connected: boolean;
    size: string;
  };
  queue: {
    connection: string;
    pending: number;
    failed: number;
  };
  cache: {
    driver: string;
    working: boolean;
  };
  storage: {
    total: string;
    free: string;
  };
}
