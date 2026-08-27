import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Pencil, X, ToggleLeft, ToggleRight } from "lucide-react";
import { superAdminApi } from "../api";
import { queryKeys } from "@/lib/query-keys";
import { toast } from "@/components/ui/toast";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import type { TenantDetail } from "../types";
import { EditTenantDialog, type EditTenantFormData } from "./edit-tenant-dialog";
import { useTenantModuleOverrides, useUpdateTenantModules } from "../hooks";

const TABS = ["Overview", "Businesses", "Users", "Products", "Sales", "Subscription", "Modules"] as const;
type Tab = (typeof TABS)[number];

function fmt(amount: number, currency = "USD") {
  return new Intl.NumberFormat("en-US", { style: "currency", currency }).format(amount);
}

const MODULE_LABELS: Record<string, string> = {
  pos: "Point of Sale",
  inventory: "Inventory Management",
  accounting: "Accounting",
  hr: "Human Resources",
  crm: "Customer Relations",
  reports: "Reports & Analytics",
  multi_branch: "Multi-Branch",
  ecommerce: "E-Commerce Integration",
};

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between py-1.5">
      <span className="text-sm text-[var(--muted)]">{label}</span>
      <span className="text-sm font-medium">{value ?? "\u2014"}</span>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="rounded-[var(--radius)] border border-[var(--border)] p-3 text-center">
      <p className="text-2xl font-bold">{value}</p>
      <p className="text-xs text-[var(--muted)]">{label}</p>
    </div>
  );
}

function Overview({ d, onEdit }: { d: TenantDetail; onEdit?: () => void }) {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat label="Users" value={d.users_count} />
        <Stat label="Businesses" value={d.businesses_count} />
        <Stat label="Products" value={d.products_count} />
        <Stat label="Categories" value={d.categories_count} />
      </div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat label="Branches" value={d.branches_count} />
        <Stat label="Warehouses" value={d.warehouses_count} />
        <Stat label="Total Sales" value={d.total_sales} />
        <Stat label="Revenue" value={fmt(d.total_revenue, d.base_currency)} />
      </div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] divide-y divide-[var(--border)]">
        <Row label="Tenant ID" value={<span className="font-mono text-xs">{d.id}</span>} />
        <Row label="Slug" value={<span className="font-mono text-xs">{d.slug}</span>} />
        <Row
          label="Status"
          value={<Badge tone={d.status === "active" ? "success" : "danger"}>{d.status}</Badge>}
        />
        <Row
          label="Plan"
          value={
            <span className="flex items-center gap-2">
              {d.plan ?? "\u2014"}
              <Button variant="ghost" size="icon" onClick={onEdit}>
                <Pencil className="h-3.5 w-3.5" />
              </Button>
            </span>
          }
        />
        <Row label="Currency" value={d.base_currency ?? "\u2014"} />
        <Row
          label="Created"
          value={new Date(d.created_at).toLocaleDateString("en-US", {
            year: "numeric", month: "long", day: "numeric",
          })}
        />
        <Row
          label="Updated"
          value={new Date(d.updated_at).toLocaleDateString("en-US", {
            year: "numeric", month: "long", day: "numeric",
          })}
        />
        {d.trial_ends_at && (
          <Row
            label="Trial Ends"
            value={new Date(d.trial_ends_at).toLocaleDateString("en-US", {
              year: "numeric", month: "long", day: "numeric",
            })}
          />
        )}
      </div>
    </div>
  );
}

function Businesses({ d }: { d: TenantDetail }) {
  if (!d.businesses?.length) return <p className="text-sm text-[var(--muted)]">No businesses.</p>;
  return (
    <div className="space-y-3">
      {d.businesses.map((b) => (
        <div key={b.id} className="rounded-[var(--radius)] border border-[var(--border)] p-4">
          <div className="flex items-center justify-between">
            <p className="font-medium">{b.name}</p>
            <span className="font-mono text-xs text-[var(--muted)]">{b.id}</span>
          </div>
          <p className="mt-1 text-sm text-[var(--muted)]">Currency: {b.currency}</p>
        </div>
      ))}
    </div>
  );
}

function Users({ d }: { d: TenantDetail }) {
  if (!d.users?.length) return <p className="text-sm text-[var(--muted)]">No users.</p>;
  return (
    <div className="space-y-2">
      {d.users.map((u) => (
        <div
          key={u.id}
          className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] p-3"
        >
          <div>
            <p className="font-medium">{u.name}</p>
            <p className="text-xs text-[var(--muted)]">{u.email}</p>
          </div>
          <div className="flex items-center gap-2">
            {u.roles.map((r) => (
              <Badge key={r} tone="info">{r}</Badge>
            ))}
            <Badge tone={u.status === "active" ? "success" : "danger"}>{u.status}</Badge>
          </div>
        </div>
      ))}
    </div>
  );
}

function Products({ d }: { d: TenantDetail }) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <Stat label="Products" value={d.products_count} />
        <Stat label="Categories" value={d.categories_count} />
      </div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] divide-y divide-[var(--border)]">
        <Row label="Branches" value={d.branches_count} />
        <Row label="Warehouses" value={d.warehouses_count} />
      </div>
    </div>
  );
}

function Sales({ d }: { d: TenantDetail }) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <Stat label="Total Sales" value={d.total_sales} />
        <Stat label="Revenue" value={fmt(d.total_revenue, d.base_currency)} />
      </div>
      {d.recent_sales?.length ? (
        <div className="space-y-2">
          <h4 className="text-sm font-medium">Recent Sales</h4>
          {d.recent_sales.map((s) => (
            <div
              key={s.id}
              className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] p-3"
            >
              <div>
                <p className="font-medium">{fmt(s.total, d.base_currency)}</p>
                <p className="text-xs text-[var(--muted)]">
                  {s.business_name ?? "\u2014"} &middot;{" "}
                  {new Date(s.created_at).toLocaleDateString()}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={s.payment_status === "paid" ? "success" : "warning"}>
                  {s.payment_status}
                </Badge>
                <Badge tone={s.status === "completed" ? "success" : "info"}>{s.status}</Badge>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <p className="text-sm text-[var(--muted)]">No recent sales.</p>
      )}
    </div>
  );
}

function Subscription({ d }: { d: TenantDetail }) {
  if (!d.subscription) return <p className="text-sm text-[var(--muted)]">No subscription.</p>;
  const s = d.subscription;
  return (
    <div className="space-y-4">
      <div className="rounded-[var(--radius)] border border-[var(--border)] divide-y divide-[var(--border)]">
        <Row label="Plan" value={s.plan?.name ?? "\u2014"} />
        <Row label="Price" value={s.plan ? `${fmt(s.plan.price)}/${s.plan.interval}` : "\u2014"} />
        <Row
          label="Status"
          value={<Badge tone={s.status === "active" ? "success" : "danger"}>{s.status}</Badge>}
        />
        {s.trial_ends_at && (
          <Row
            label="Trial Ends"
            value={new Date(s.trial_ends_at).toLocaleDateString("en-US", {
              year: "numeric", month: "long", day: "numeric",
            })}
          />
        )}
        {s.current_period_ends_at && (
          <Row
            label="Period Ends"
            value={new Date(s.current_period_ends_at).toLocaleDateString("en-US", {
              year: "numeric", month: "long", day: "numeric",
            })}
          />
        )}
        <Row
          label="Subscribed"
          value={new Date(s.created_at).toLocaleDateString("en-US", {
            year: "numeric", month: "long", day: "numeric",
          })}
        />
      </div>
    </div>
  );
}

function Modules({ d }: { d: TenantDetail }) {
  const { data: overridesData, isLoading } = useTenantModuleOverrides();
  const updateTenantModules = useUpdateTenantModules();

  const overrides = overridesData?.data ?? {};
  const tenantModules = overrides[d.id] ?? {};

  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="h-10 animate-pulse rounded bg-[var(--muted)]/10" />
        ))}
      </div>
    );
  }

  if (Object.keys(tenantModules).length === 0) {
    return <p className="text-sm text-[var(--muted)]">No module overrides for this tenant. Modules follow global platform settings.</p>;
  }

  return (
    <div className="space-y-3">
      <p className="text-sm text-[var(--muted)]">
        Per-tenant module overrides for this tenant.
      </p>
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        {Object.entries(tenantModules).map(([key, enabled]) => (
          <div
            key={key}
            className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] p-3"
          >
            <span className="text-sm font-medium">{MODULE_LABELS[key] ?? key}</span>
            {enabled ? (
              <ToggleRight className="h-5 w-5 text-[var(--success)]" />
            ) : (
              <ToggleLeft className="h-5 w-5 text-[var(--muted)]" />
            )}
          </div>
        ))}
      </div>
      {updateTenantModules.isPending && (
        <p className="text-xs text-[var(--muted)]">Updating...</p>
      )}
    </div>
  );
}

const TAB_COMPONENTS: Record<Tab, React.FC<{ d: TenantDetail; onEdit?: () => void }>> = {
  Overview,
  Businesses,
  Users,
  Products,
  Sales,
  Subscription,
  Modules,
};

interface Props {
  tenantId: string | null;
  onClose: () => void;
}

export function TenantDetailModal({ tenantId, onClose }: Props) {
  const [tab, setTab] = useState<Tab>("Overview");
  const [editing, setEditing] = useState(false);
  const qc = useQueryClient();

  const query = useQuery({
    queryKey: queryKeys.superAdmin.tenantDetail(tenantId ?? undefined),
    queryFn: () => superAdminApi.tenants.detail(tenantId!),
    enabled: !!tenantId,
  });

  const updateMutation = useMutation({
    mutationFn: (data: EditTenantFormData) =>
      superAdminApi.tenants.update(tenantId!, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.superAdmin.tenantDetail(tenantId ?? undefined) });
      toast("Tenant updated", "success");
      setEditing(false);
    },
  });

  if (!tenantId) return null;

  const detail = query.data?.data;
  const TabContent = TAB_COMPONENTS[tab];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative flex h-[85vh] w-full max-w-4xl flex-col rounded-[var(--radius)] bg-[var(--surface)] shadow-xl">
        <div className="flex items-center justify-between border-b border-[var(--border)] px-6 py-4">
          <div>
            <h2 className="text-base font-semibold">
              {detail?.name ?? query.data?.data?.name ?? "Tenant"}
            </h2>
            {detail && (
              <p className="text-xs text-[var(--muted)]">{detail.slug}</p>
            )}
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="flex gap-1 border-b border-[var(--border)] px-6">
          {TABS.map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`px-3 py-2 text-sm font-medium transition-colors ${
                tab === t
                  ? "border-b-2 border-[var(--primary)] text-[var(--primary)]"
                  : "text-[var(--muted)] hover:text-[var(--foreground)]"
              }`}
            >
              {t}
            </button>
          ))}
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-4">
          {query.isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="h-10 animate-pulse rounded bg-[var(--muted)]/10" />
              ))}
            </div>
          ) : query.isError ? (
            <p className="text-sm text-[var(--color-danger)]">Failed to load tenant details.</p>
          ) : detail ? (
            <TabContent d={detail} onEdit={tab === "Overview" ? () => setEditing(true) : undefined} />
          ) : (
            <p className="text-sm text-[var(--muted)]">No data.</p>
          )}
        </div>
      </div>
      <EditTenantDialog
        key={detail?.id}
        tenant={editing ? detail ?? null : null}
        onClose={() => setEditing(false)}
        onSubmit={(data) => updateMutation.mutate(data)}
        loading={updateMutation.isPending}
      />
    </div>
  );
}
