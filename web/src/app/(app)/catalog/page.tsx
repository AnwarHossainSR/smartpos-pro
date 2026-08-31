import { useState } from "react";
import {
  ActiveBadge,
  type ResourceColumn,
  ResourceManager,
  type ResourceRecord,
} from "@/components/ui/resource-manager";
import { useCategories, useUnits } from "@/lib/lookups";
import { queryKeys } from "@/lib/query-keys";
import { useAuth } from "@/stores/auth";
import type { Brand, Category, Unit } from "@/types/domain";

/**
 * `ResourceManager` indexes rows generically, so its records need the `ResourceRecord`
 * index signature. Intersecting is what keeps the shared entity types authoritative —
 * re-declaring `Category`/`Brand`/`Unit` here is exactly the drift this refactor removed.
 */
type CategoryRow = Category & ResourceRecord;
type BrandRow = Brand & ResourceRecord;
type UnitRow = Unit & ResourceRecord;

const TABS = ["categories", "brands", "units"] as const;
type Tab = (typeof TABS)[number];

const TAB_LABELS: Record<Tab, string> = {
  categories: "Categories",
  brands: "Brands",
  units: "Units",
};

const PERMISSIONS = {
  create: "products.create",
  update: "products.update",
  delete: "products.delete",
} as const;

export default function CatalogSetupPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<Tab>("categories");

  // The parent-category and base-unit pickers read the same shared lookups the product
  // form does, so opening this tab after Products costs no extra request.
  const canView = can("products.view");
  const { categories } = useCategories({ enabled: canView });
  const { units } = useUnits({ enabled: canView });

  if (!canView) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You do not have access to the catalog.
      </div>
    );
  }

  const categoryColumns: ResourceColumn<CategoryRow>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Parent", cell: (row) => row.parent?.name ?? "—" },
    { header: "Description", cell: (row) => row.description ?? "—" },
    { header: "Status", cell: (row) => <ActiveBadge active={!!row.is_active} /> },
  ];

  const brandColumns: ResourceColumn<BrandRow>[] = [
    { header: "Name", cell: (row) => row.name },
    { header: "Status", cell: (row) => <ActiveBadge active={!!row.is_active} /> },
  ];

  const unitColumns: ResourceColumn<UnitRow>[] = [
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
        <ResourceManager<CategoryRow>
          endpoint="/categories"
          // ResourceManager keys off the resource *name* and prefixes it itself, so passing
          // the factory's root keeps its cache (and its invalidations) inside this namespace.
          queryKey={queryKeys.categories.all[0]}
          singular="Category"
          columns={categoryColumns}
          permissions={PERMISSIONS}
          fields={[
            { name: "name", label: "Name", type: "text", required: true },
            {
              name: "parent_id",
              label: "Parent category",
              type: "select",
              options: categories.map((c) => ({ value: c.id, label: c.name })),
            },
            { name: "description", label: "Description", type: "textarea" },
            { name: "is_active", label: "Active", type: "checkbox" },
          ]}
        />
      )}

      {tab === "brands" && (
        <ResourceManager<BrandRow>
          endpoint="/brands"
          queryKey={queryKeys.brands.all[0]}
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
        <ResourceManager<UnitRow>
          endpoint="/units"
          queryKey={queryKeys.units.all[0]}
          singular="Unit"
          columns={unitColumns}
          permissions={PERMISSIONS}
          fields={[
            { name: "name", label: "Name", type: "text", required: true, placeholder: "Kilogram" },
            {
              name: "short_name",
              label: "Short name",
              type: "text",
              required: true,
              placeholder: "kg",
            },
            {
              name: "base_unit_id",
              label: "Base unit",
              type: "select",
              options: units.map((u) => ({ value: u.id, label: u.name })),
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
