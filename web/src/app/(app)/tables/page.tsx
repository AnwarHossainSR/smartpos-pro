"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Grid2x2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError, type Paginated } from "@/lib/api";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { cn } from "@/lib/utils";
import { useAuth } from "@/stores/auth";

type TableStatus = "available" | "occupied" | "reserved";

interface DiningTable {
  id: string;
  name: string;
  seats: number;
  status: TableStatus;
  area?: string | null;
}

const STATUSES: TableStatus[] = ["available", "occupied", "reserved"];

const STATUS_TONE: Record<TableStatus, "success" | "warning" | "danger"> = {
  available: "success",
  occupied: "warning",
  reserved: "danger",
};

const STATUS_RING: Record<TableStatus, string> = {
  available: "border-green-500/40",
  occupied: "border-amber-500/40",
  reserved: "border-red-500/40",
};

interface FormState {
  name: string;
  seats: string;
  area: string;
  status: TableStatus;
}

const EMPTY_FORM: FormState = { name: "", seats: "4", area: "", status: "available" };

function TableForm({
  table,
  onDone,
}: {
  table: DiningTable | null;
  onDone: () => void;
}) {
  const t = useTranslation();
  const qc = useQueryClient();
  const [form, setForm] = useState<FormState>(
    table
      ? {
          name: table.name,
          seats: String(table.seats),
          area: table.area ?? "",
          status: table.status,
        }
      : EMPTY_FORM,
  );
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const save = useMutation({
    mutationFn: () => {
      const body = {
        name: form.name.trim(),
        seats: Number(form.seats),
        area: form.area.trim() || null,
        status: form.status,
      };
      return table
        ? api(`/tables/${table.id}`, { method: "PUT", body })
        : api("/tables", { method: "POST", body });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["dining-tables"] });
      qc.invalidateQueries({ queryKey: ["dining-tables-all"] });
      toast(table ? t("tables.updated") : t("tables.created"));
      onDone();
    },
    onError: (e: unknown) => {
      if (e instanceof ApiError && e.errors) setErrors(e.errors);
      toast(e instanceof ApiError ? e.message : t("tables.saveFailed"), "error");
    },
  });

  const seats = Number(form.seats);
  const localError =
    !form.name.trim()
      ? t("tables.nameRequired")
      : !Number.isFinite(seats) || seats < 1
        ? t("tables.seatsInvalid")
        : null;

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        if (localError) {
          setErrors({ name: [localError] });
          return;
        }
        setErrors({});
        save.mutate();
      }}
    >
      <div>
        <Label htmlFor="table-name">{t("tables.name")} *</Label>
        <Input
          id="table-name"
          value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
        />
        {errors.name?.[0] && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name[0]}</p>}
      </div>

      <div>
        <Label htmlFor="table-seats">{t("tables.seats")} *</Label>
        <Input
          id="table-seats"
          type="number"
          min={1}
          value={form.seats}
          onChange={(e) => setForm((f) => ({ ...f, seats: e.target.value }))}
        />
        {errors.seats?.[0] && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.seats[0]}</p>}
      </div>

      <div>
        <Label htmlFor="table-area">{t("tables.area")}</Label>
        <Input
          id="table-area"
          value={form.area}
          placeholder={t("tables.areaPlaceholder")}
          onChange={(e) => setForm((f) => ({ ...f, area: e.target.value }))}
        />
      </div>

      <div>
        <Label htmlFor="table-status">{t("tables.status")}</Label>
        <Select
          id="table-status"
          value={form.status}
          onChange={(e) => setForm((f) => ({ ...f, status: e.target.value as TableStatus }))}
        >
          {STATUSES.map((status) => (
            <option key={status} value={status}>
              {t(`tables.${status}`)}
            </option>
          ))}
        </Select>
      </div>

      <Button type="submit" className="w-full" disabled={save.isPending}>
        {save.isPending ? t("common.saving") : t("common.save")}
      </Button>
    </form>
  );
}

export default function TablesPage() {
  const t = useTranslation();
  const { can } = useAuth();
  const user = useAuth((s) => s.user);

  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<TableStatus | "all">("all");
  const [editing, setEditing] = useState<DiningTable | null>(null);
  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<DiningTable | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  const { data, isLoading, isError, refetch } = useQuery<Paginated<DiningTable>>({
    queryKey: ["dining-tables", user?.branch_id],
    // `/tables` returns the whole (small, per-branch) floor plan in one un-paginated
    // collection, so search and status filtering happen client-side below.
    queryFn: () => {
      const params = new URLSearchParams();
      if (user?.branch_id) params.set("branch_id", user.branch_id);
      const query = params.toString();
      return api<Paginated<DiningTable>>(query ? `/tables?${query}` : "/tables");
    },
  });

  const all = useMemo(() => data?.data ?? [], [data]);

  const tables = useMemo(() => {
    const needle = debouncedSearch.trim().toLowerCase();
    return all.filter((table) => {
      if (statusFilter !== "all" && table.status !== statusFilter) return false;
      if (!needle) return true;
      return (
        table.name.toLowerCase().includes(needle) ||
        (table.area ?? "").toLowerCase().includes(needle) ||
        String(table.seats).includes(needle)
      );
    });
  }, [all, debouncedSearch, statusFilter]);

  const canCreate = can("tables.create");
  const canUpdate = can("tables.update");

  return (
    <div className="space-y-4">
      <ListToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder={t("tables.searchPlaceholder")}
        filters={
          <Select
            className="sm:w-44"
            aria-label={t("tables.status")}
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value as TableStatus | "all")}
          >
            <option value="all">{t("tables.allStatuses")}</option>
            {STATUSES.map((status) => (
              <option key={status} value={status}>
                {t(`tables.${status}`)}
              </option>
            ))}
          </Select>
        }
        addLabel={canCreate ? t("tables.add") : undefined}
        onAdd={canCreate ? () => setCreating(true) : undefined}
      />

      {isLoading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <Skeleton key={i} className="h-28 rounded-[var(--radius)]" />
          ))}
        </div>
      ) : isError ? (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] py-12 text-center">
          <p className="text-sm text-[var(--muted)]">{t("tables.loadFailed")}</p>
          <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>
            {t("tables.retry")}
          </Button>
        </div>
      ) : tables.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] py-14 text-center">
          <Grid2x2 className="h-8 w-8 text-[var(--muted)]" aria-hidden="true" />
          <p className="text-sm text-[var(--muted)]">
            {debouncedSearch || statusFilter !== "all" ? t("tables.emptySearch") : t("tables.empty")}
          </p>
        </div>
      ) : (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {tables.map((table) => (
            <li key={table.id}>
              <button
                type="button"
                onClick={() => setSelected(table)}
                className={cn(
                  "flex w-full flex-col gap-1 rounded-[var(--radius)] border-2 bg-[var(--surface)] p-4 text-start transition-colors hover:bg-[var(--surface-2)]",
                  STATUS_RING[table.status],
                )}
              >
                <span className="truncate font-medium">{table.name}</span>
                <span className="text-xs text-[var(--muted)]">
                  {t("tables.seatsCount", { count: table.seats })}
                  {table.area ? ` · ${table.area}` : ""}
                </span>
                <Badge tone={STATUS_TONE[table.status]} className="mt-1 self-start">
                  {t(`tables.${table.status}`)}
                </Badge>
              </button>
            </li>
          ))}
        </ul>
      )}

      <Drawer
        open={creating || !!editing}
        onClose={() => {
          setCreating(false);
          setEditing(null);
        }}
        title={editing ? t("tables.edit") : t("tables.add")}
      >
        <TableForm
          key={editing?.id ?? "new"}
          table={editing}
          onDone={() => {
            setCreating(false);
            setEditing(null);
          }}
        />
      </Drawer>

      <Dialog
        open={!!selected}
        onClose={() => setSelected(null)}
        title={selected?.name ?? ""}
        description={
          selected
            ? `${t("tables.seatsCount", { count: selected.seats })} · ${t(`tables.${selected.status}`)}`
            : undefined
        }
      >
        <div className="flex flex-col gap-2">
          {canUpdate && selected && (
            <Button
              variant="outline"
              onClick={() => {
                setEditing(selected);
                setSelected(null);
              }}
            >
              {t("tables.edit")}
            </Button>
          )}
          <Button variant="ghost" onClick={() => setSelected(null)}>
            {t("common.close")}
          </Button>
        </div>
      </Dialog>
    </div>
  );
}
