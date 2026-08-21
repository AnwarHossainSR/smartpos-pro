import { ActiveBadge, ResourceManager, type ResourceColumn, type ResourceRecord } from "@/components/ui/resource-manager";
import { useBranches } from "@/lib/lookups";
import { useAuth } from "@/stores/auth";
import type { Branch, Warehouse } from "@/types/domain";
import { ArrowLeft } from "lucide-react";
import { Link } from "react-router";
import { useState } from "react";

/**
 * `ResourceManager` needs an index signature to read arbitrary form fields off a row;
 * the entity shapes themselves stay in `@/types/domain` rather than being re-declared.
 */
type BranchRow = Branch & ResourceRecord;
type WarehouseRow = Warehouse & ResourceRecord;

const TABS = ["branches", "warehouses"] as const;
type Tab = (typeof TABS)[number];

const TAB_LABELS: Record<Tab, string> = { branches: "Branches", warehouses: "Warehouses" };

export default function LocationsPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("branches");

  // The shared lookup, so the warehouse form's branch select and every other branch
  // picker in the app share one cache entry and one request.
  const { branches } = useBranches({ enabled: can("branches.view") });

  if (!can("branches.view") && !can("warehouses.view")) {
    return <div className="py-16 text-center text-[var(--muted)]">You do not have access to locations.</div>;
  }

  const branchColumns: ResourceColumn<BranchRow>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Code", cell: (row) => row.code ?? "—" },
    { header: "Phone", cell: (row) => row.phone ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active ?? false} /> },
  ];

  const warehouseColumns: ResourceColumn<WarehouseRow>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Code", cell: (row) => row.code ?? "—" },
    { header: "Branch", cell: (row) => row.branch?.name ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active ?? false} /> },
  ];

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <h1 className="text-lg font-semibold">Branches & warehouses</h1>
      </div>

      <div className="flex gap-1 border-b border-[var(--border)]">
        {TABS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`px-4 py-2 text-sm transition-colors ${
              tab === key
                ? "border-b-2 border-[var(--primary)] font-medium text-[var(--fg)]"
                : "text-[var(--muted)] hover:text-[var(--fg)]"
            }`}
          >
            {TAB_LABELS[key]}
          </button>
        ))}
      </div>

      {tab === "branches" && (
        <ResourceManager<BranchRow>
          endpoint="/branches"
          queryKey="branches"
          singular="Branch"
          columns={branchColumns}
          permissions={{ create: "branches.create", update: "branches.update", delete: "branches.delete" } as const}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            { name: "code", label: "Code", type: "text", placeholder: "BR-01" },
            { name: "phone", label: "Phone", type: "text" },
            { name: "email", label: "Email", type: "email" },
            { name: "address", label: "Address", type: "textarea" },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}

      {tab === "warehouses" && (
        <ResourceManager<WarehouseRow>
          endpoint="/warehouses"
          queryKey="warehouses"
          singular="Warehouse"
          columns={warehouseColumns}
          permissions={{ create: "warehouses.create", update: "warehouses.update", delete: "warehouses.delete" } as const}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            { name: "code", label: "Code", type: "text", placeholder: "WH-01" },
            {
              name: "branch_id",
              label: "Branch",
              type: "select",
              options: branches.map((b) => ({ value: b.id, label: b.name })),
            },
            { name: "address", label: "Address", type: "textarea" },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}
    </div>
  );
}
