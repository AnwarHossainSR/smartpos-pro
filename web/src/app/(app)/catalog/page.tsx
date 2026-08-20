"use client";

import { ActiveBadge, ResourceManager, type ResourceColumn, type ResourceRecord } from "@/components/ui/resource-manager";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

interface Category extends ResourceRecord {
  id: string;
  name: string;
  description: string | null;
  is_active: boolean;
  parent?: { id: string; name: string } | null;
}

interface Brand extends ResourceRecord {
  id: string;
  name: string;
  is_active: boolean;
}

interface Unit extends ResourceRecord {
  id: string;
  name: string;
  short_name: string;
  operator_value: number;
}

const TABS = ["categories", "brands", "units"] as const;
type Tab = (typeof TABS)[number];

const TAB_LABELS: Record<Tab, string> = {
  categories: "Categories",
  brands: "Brands",
  units: "Units",
};

const PERMISSIONS = { create: "products.create", update: "products.update", delete: "products.delete" } as const;

export default function CatalogSetupPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("categories");

  const { data: categories } = useQuery({
    queryKey: ["categories-options"],
    queryFn: () => api<{ data: Category[] }>("/categories?per_page=200"),
    enabled: can("products.view"),
  });

  const { data: units } = useQuery({
    queryKey: ["units-options"],
    queryFn: () => api<{ data: Unit[] }>("/units?per_page=200"),
    enabled: can("products.view"),
  });

  if (!can("products.view")) {
    return <div className="py-16 text-center text-[var(--muted)]">You do not have access to the catalog.</div>;
  }

  const categoryColumns: ResourceColumn<Category>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Parent", cell: (row) => row.parent?.name ?? "—" },
    { header: "Description", cell: (row) => row.description ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active} /> },
  ];

  const brandColumns: ResourceColumn<Brand>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Status", cell: (row) => <ActiveBadge active={row.is_active} /> },
  ];

  const unitColumns: ResourceColumn<Unit>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Short name", cell: (row) => row.short_name },
    { header: "Factor", cell: (row) => row.operator_value, align: "end" },
  ];

  return (
    <div className="space-y-5">
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

      {tab === "categories" && (
        <ResourceManager<Category>
          endpoint="/categories"
          queryKey="categories"
          singular="Category"
          columns={categoryColumns}
          permissions={PERMISSIONS}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            {
              name: "parent_id",
              label: "Parent category",
              type: "select",
              options: (categories?.data ?? []).map((c) => ({ value: c.id, label: c.name })),
            },
            { name: "description", label: "Description", type: "textarea" },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}

      {tab === "brands" && (
        <ResourceManager<Brand>
          endpoint="/brands"
          queryKey="brands"
          singular="Brand"
          columns={brandColumns}
          permissions={PERMISSIONS}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}

      {tab === "units" && (
        <ResourceManager<Unit>
          endpoint="/units"
          queryKey="units"
          singular="Unit"
          columns={unitColumns}
          permissions={PERMISSIONS}
          fields={[
            { name: "name", label: "Name", type: "text", required: true, placeholder: "Kilogram" },
            { name: "short_name", label: "Short name", type: "text", required: true, placeholder: "kg" },
            {
              name: "base_unit_id",
              label: "Base unit",
              type: "select",
              options: (units?.data ?? []).map((u) => ({ value: u.id, label: u.name })),
              help: "Set when this unit converts to another, e.g. a box of 12 pieces.",
            },
            {
              name: "operator_value",
              label: "Conversion factor",
              type: "number",
              help: "How many base units one of this unit is worth.",
            },
          ]}
        />
      )}
    </div>
  );
}
