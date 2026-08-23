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

// Support Ticket types
export interface SupportTicketUser {
  id: string;
  name: string;
  email?: string;
}

export interface SupportTicketAssignedTo {
  id: string;
  name: string;
}

export interface SupportTicket {
  id: string;
  subject: string;
  message: string;
  category: string;
  priority: string;
  status: string;
  is_sla_breached: boolean;
  sla_due_at: string | null;
  last_reply_at: string | null;
  user?: SupportTicketUser;
  assigned_to?: SupportTicketAssignedTo;
  replies_count: number;
  created_at: string;
  updated_at: string;
}

export interface TicketReply {
  id: string;
  message: string;
  is_internal: boolean;
  user?: SupportTicketUser;
  created_at: string;
}

export interface TicketMetrics {
  total_open: number;
  total_in_progress: number;
  total_waiting: number;
  total_resolved: number;
  total_closed: number;
  sla_breached: number;
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
