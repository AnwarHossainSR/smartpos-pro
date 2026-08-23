export type { Tenant, TenantDetail, RevenueMetrics, Impersonation, PlanDistribution, PlatformUser, AuditLog } from "@/types/domain";

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
