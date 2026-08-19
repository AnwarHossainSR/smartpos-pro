"use client";

import { ActiveBadge, ResourceManager, type ResourceColumn, type ResourceRecord } from "@/components/ui/resource-manager";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

interface Branch extends ResourceRecord {
  id: string;
  name: string;
  code: string | null;
  phone: string | null;
  email: string | null;
  is_active: boolean;
  is_default?: boolean;
}

interface Warehouse extends ResourceRecord {
  id: string;
  name: string;
  code: string | null;
  is_active: boolean;
  branch?: { id: string; name: string } | null;
}

const TABS = ["branches", "warehouses"] as const;
type Tab = (typeof TABS)[number];

const TAB_LABELS: Record<Tab, string> = { branches: "Branches", warehouses: "Warehouses" };

export default function LocationsPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("branches");

  const { data: branches } = useQuery({
    queryKey: ["branches-options"],
    queryFn: () => api<{ data: Branch[] }>("/branches?per_page=200"),
    enabled: can("branches.view"),
  });

  if (!can("branches.view") && !can("warehouses.view")) {
    return <div className="py-16 text-center text-[var(--muted)]">You do not have access to locations.</div>;
  }

  const branchColumns: ResourceColumn<Branch>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Code", cell: (row) => row.code ?? "—" },
    { header: "Phone", cell: (row) => row.phone ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active} /> },
  ];

  const warehouseColumns: ResourceColumn<Warehouse>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Code", cell: (row) => row.code ?? "—" },
    { header: "Branch", cell: (row) => row.branch?.name ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active} /> },
  ];

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link href="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
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
        <ResourceManager<Branch>
          endpoint="/branches"
          queryKey="branches"
          singular="Branch"
          columns={branchColumns}
          permissions={{ create: "branches.create", update: "branches.update", delete: "branches.delete" }}
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
        <ResourceManager<Warehouse>
          endpoint="/warehouses"
          queryKey="warehouses"
          singular="Warehouse"
          columns={warehouseColumns}
          permissions={{ create: "warehouses.create", update: "warehouses.update", delete: "warehouses.delete" }}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            { name: "code", label: "Code", type: "text", placeholder: "WH-01" },
            {
              name: "branch_id",
              label: "Branch",
              type: "select",
              options: (branches?.data ?? []).map((b) => ({ value: b.id, label: b.name })),
            },
            { name: "address", label: "Address", type: "textarea" },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}
    </div>
  );
}
