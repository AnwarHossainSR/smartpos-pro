"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Lock, Pencil, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Drawer } from "@/components/ui/drawer";
import { Input, Label } from "@/components/ui/input";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/provider";
import { api, ApiError, type Paginated } from "@/lib/api";
import { PERMISSION_MATRIX, splitSlug } from "@/lib/permissions";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { useAuth } from "@/stores/auth";

export interface Role {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  permissions?: string[];
  users_count?: number;
}

type RoleFilter = "all" | "custom" | "system";

const PER_PAGE = 10;

/** `GET /permissions` returns the server's `PermissionCatalog::MATRIX` verbatim. */
type PermissionMatrix = Record<string, string[]>;

function useTranslatedLabels() {
  const t = useTranslation();
  return {
    moduleLabel: (module: string) => {
      const key = `permissions.modules.${module}`;
      const label = t(key);
      return label === key ? module.replace(/-/g, " ") : label;
    },
    actionLabel: (action: string) => {
      const key = `permissions.actions.${action}`;
      const label = t(key);
      return label === key ? action.replace(/_/g, " ") : label;
    },
  };
}

function ModuleSection({
  module,
  actions,
  selected,
  disabled,
  onToggleAction,
  onToggleModule,
}: {
  module: string;
  actions: string[];
  selected: Set<string>;
  disabled: boolean;
  onToggleAction: (slug: string, checked: boolean) => void;
  onToggleModule: (module: string, actions: string[], checked: boolean) => void;
}) {
  const t = useTranslation();
  const { moduleLabel, actionLabel } = useTranslatedLabels();

  const slugs = actions.map((action) => `${module}.${action}`);
  const checkedCount = slugs.filter((slug) => selected.has(slug)).length;
  const allChecked = checkedCount === slugs.length && slugs.length > 0;

  return (
    <fieldset className="rounded-[var(--radius)] border border-[var(--border)] p-3">
      <legend className="flex items-center gap-2 px-1">
        <label className="flex cursor-pointer items-center gap-2 text-sm font-medium">
          <input
            type="checkbox"
            checked={allChecked}
            disabled={disabled}
            ref={(node) => {
              if (node) node.indeterminate = checkedCount > 0 && !allChecked;
            }}
            onChange={(e) => onToggleModule(module, actions, e.target.checked)}
          />
          {moduleLabel(module)}
        </label>
        <span className="text-xs text-[var(--muted)]">
          {checkedCount}/{slugs.length}
        </span>
      </legend>
      <div className="mt-1 grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-3">
        {actions.map((action) => {
          const slug = `${module}.${action}`;
          return (
            <label key={slug} className="flex cursor-pointer items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={selected.has(slug)}
                disabled={disabled}
                onChange={(e) => onToggleAction(slug, e.target.checked)}
              />
              <span className="truncate" title={slug}>
                {actionLabel(action)}
              </span>
            </label>
          );
        })}
      </div>
      {slugs.length === 0 && <p className="text-xs text-[var(--muted)]">{t("roles.noPermissions")}</p>}
    </fieldset>
  );
}

function RoleForm({
  role,
  matrix,
  readOnly,
  onDone,
}: {
  role: Role | null;
  matrix: PermissionMatrix;
  readOnly: boolean;
  onDone: () => void;
}) {
  const t = useTranslation();
  const qc = useQueryClient();

  const [name, setName] = useState(role?.name ?? "");
  const [description, setDescription] = useState(role?.description ?? "");
  const [selected, setSelected] = useState<Set<string>>(() => new Set(role?.permissions ?? []));
  const [filter, setFilter] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const modules = useMemo(() => Object.keys(matrix), [matrix]);

  const visibleModules = useMemo(() => {
    const needle = filter.trim().toLowerCase();
    if (!needle) return modules;
    return modules.filter(
      (module) =>
        module.includes(needle) ||
        (matrix[module] ?? []).some((action) => `${module}.${action}`.includes(needle)),
    );
  }, [filter, matrix, modules]);

  const totalSlugs = useMemo(
    () => modules.reduce((sum, module) => sum + (matrix[module]?.length ?? 0), 0),
    [matrix, modules],
  );

  const toggleAction = (slug: string, checked: boolean) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (checked) next.add(slug);
      else next.delete(slug);
      return next;
    });
  };

  const toggleModule = (module: string, actions: string[], checked: boolean) => {
    setSelected((prev) => {
      const next = new Set(prev);
      for (const action of actions) {
        const slug = `${module}.${action}`;
        if (checked) next.add(slug);
        else next.delete(slug);
      }
      return next;
    });
  };

  const setAll = (checked: boolean) => {
    setSelected(
      checked
        ? new Set(modules.flatMap((module) => (matrix[module] ?? []).map((a) => `${module}.${a}`)))
        : new Set(),
    );
  };

  const save = useMutation({
    mutationFn: () => {
      const body = {
        name: name.trim(),
        description: description.trim() || null,
        permissions: [...selected],
      };
      return role
        ? api(`/roles/${role.id}`, { method: "PUT", body })
        : api("/roles", { method: "POST", body });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["roles"] });
      qc.invalidateQueries({ queryKey: ["roles-list"] });
      // The signed-in user may have just changed their own effective grants.
      qc.invalidateQueries({ queryKey: ["me"] });
      toast(role ? t("roles.updated") : t("roles.created"));
      onDone();
    },
    onError: (e: unknown) => {
      if (e instanceof ApiError && e.errors) setErrors(e.errors);
      toast(e instanceof ApiError ? e.message : t("roles.saveFailed"), "error");
    },
  });

  const nameError = errors.name?.[0];

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        if (readOnly) return;
        if (!name.trim()) {
          setErrors({ name: [t("roles.nameRequired")] });
          return;
        }
        setErrors({});
        save.mutate();
      }}
    >
      {readOnly && (
        <p className="rounded-[var(--radius)] bg-[var(--surface-2)] px-3 py-2 text-sm text-[var(--muted)]">
          {t("roles.systemLocked")}
        </p>
      )}

      <div>
        <Label htmlFor="role-name">{t("roles.name")} *</Label>
        <Input
          id="role-name"
          value={name}
          disabled={readOnly}
          aria-invalid={!!nameError}
          onChange={(e) => setName(e.target.value)}
        />
        {nameError && <p className="mt-1 text-xs text-[var(--color-danger)]">{nameError}</p>}
      </div>

      <div>
        <Label htmlFor="role-description">{t("roles.description")}</Label>
        <Input
          id="role-description"
          value={description}
          disabled={readOnly}
          onChange={(e) => setDescription(e.target.value)}
        />
        {errors.description?.[0] && (
          <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.description[0]}</p>
        )}
      </div>

      <div className="space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Label className="mb-0">
            {t("roles.permissions")}{" "}
            <span className="font-normal text-[var(--muted)]">
              ({selected.size}/{totalSlugs})
            </span>
          </Label>
          {!readOnly && (
            <div className="flex gap-1">
              <Button type="button" variant="ghost" size="sm" onClick={() => setAll(true)}>
                {t("roles.selectAll")}
              </Button>
              <Button type="button" variant="ghost" size="sm" onClick={() => setAll(false)}>
                {t("roles.clearAll")}
              </Button>
            </div>
          )}
        </div>

        <Input
          value={filter}
          placeholder={t("roles.filterPermissions")}
          aria-label={t("roles.filterPermissions")}
          onChange={(e) => setFilter(e.target.value)}
        />

        {errors.permissions?.[0] && (
          <p className="text-xs text-[var(--color-danger)]">{errors.permissions[0]}</p>
        )}

        <div className="space-y-2">
          {visibleModules.length === 0 ? (
            <p className="py-6 text-center text-sm text-[var(--muted)]">{t("roles.noMatches")}</p>
          ) : (
            visibleModules.map((module) => (
              <ModuleSection
                key={module}
                module={module}
                actions={matrix[module] ?? []}
                selected={selected}
                disabled={readOnly}
                onToggleAction={toggleAction}
                onToggleModule={toggleModule}
              />
            ))
          )}
        </div>
      </div>

      {!readOnly && (
        <Button type="submit" className="w-full" disabled={save.isPending}>
          {save.isPending ? t("common.saving") : t("common.save")}
        </Button>
      )}
    </form>
  );
}

export function RolesTab() {
  const t = useTranslation();
  const qc = useQueryClient();
  const { can } = useAuth();

  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState<RoleFilter>("all");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Role | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Role | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  // A tenant has a handful of roles, so the whole set comes back in one request and search,
  // filtering and paging happen here. Filtering server-side would need query params the
  // endpoint does not declare — and `QueryPipeline` 422s on an undeclared key.
  const { data, isLoading, isError, refetch } = useQuery<Paginated<Role>>({
    queryKey: ["roles", "manage"],
    queryFn: () => api<Paginated<Role>>("/roles?per_page=100"),
  });

  const { data: matrixData } = useQuery<{ data: PermissionMatrix }>({
    queryKey: ["permissions", "matrix"],
    queryFn: () => api<{ data: PermissionMatrix }>("/permissions"),
  });

  // Fall back to the compiled-in mirror so the editor still works if `/permissions` fails.
  const matrix: PermissionMatrix = useMemo(
    () => matrixData?.data ?? (PERMISSION_MATRIX as unknown as PermissionMatrix),
    [matrixData],
  );

  const remove = useMutation({
    mutationFn: (role: Role) => api(`/roles/${role.id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["roles"] });
      qc.invalidateQueries({ queryKey: ["roles-list"] });
      toast(t("roles.deleted"));
      setDeleting(null);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : t("roles.deleteFailed"), "error"),
  });

  const all = useMemo(() => data?.data ?? [], [data]);

  const matched = useMemo(() => {
    const needle = debouncedSearch.trim().toLowerCase();
    return all.filter((role) => {
      if (filter === "system" && !role.is_system) return false;
      if (filter === "custom" && role.is_system) return false;
      if (!needle) return true;
      return (
        role.name.toLowerCase().includes(needle) ||
        role.slug.toLowerCase().includes(needle) ||
        (role.description ?? "").toLowerCase().includes(needle)
      );
    });
  }, [all, debouncedSearch, filter]);

  const lastPage = Math.max(1, Math.ceil(matched.length / PER_PAGE));
  const currentPage = Math.min(page, lastPage);
  const rows = matched.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE);

  const summarise = (role: Role) => {
    const permissions = role.permissions ?? [];
    if (role.slug === "owner") return t("roles.allPermissions");
    if (permissions.length === 0) return t("roles.noPermissions");
    const modules = new Set(permissions.map((slug) => splitSlug(slug)?.module).filter(Boolean));
    return t("roles.permissionSummary", { count: permissions.length, modules: modules.size });
  };

  const canCreate = can("roles.create");
  const canUpdate = can("roles.update");
  const canDelete = can("roles.delete");

  return (
    <div className="space-y-4">
      <ListToolbar
        search={search}
        onSearchChange={(value) => {
          setSearch(value);
          setPage(1);
        }}
        searchPlaceholder={t("roles.searchPlaceholder")}
        filters={
          <Select
            className="sm:w-40"
            aria-label={t("roles.type")}
            value={filter}
            onChange={(e) => {
              setFilter(e.target.value as RoleFilter);
              setPage(1);
            }}
          >
            <option value="all">{t("roles.all")}</option>
            <option value="custom">{t("roles.custom")}</option>
            <option value="system">{t("roles.system")}</option>
          </Select>
        }
        addLabel={canCreate ? t("roles.new") : undefined}
        onAdd={canCreate ? () => setCreating(true) : undefined}
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t("roles.name")}</TableHead>
              <TableHead>{t("roles.permissions")}</TableHead>
              <TableHead className="text-end">{t("roles.members")}</TableHead>
              <TableHead className="w-20" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 4 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && isError && (
              <TableRow>
                <TableCell colSpan={4} className="py-10 text-center">
                  <p className="text-sm text-[var(--muted)]">{t("roles.loadFailed")}</p>
                  <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>
                    {t("roles.retry")}
                  </Button>
                </TableCell>
              </TableRow>
            )}

            {!isLoading && !isError && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-12 text-center text-[var(--muted)]">
                  {debouncedSearch || filter !== "all" ? t("roles.emptySearch") : t("roles.empty")}
                </TableCell>
              </TableRow>
            )}

            {rows.map((role) => (
              <TableRow key={role.id}>
                <TableCell>
                  <div className="flex items-center gap-2 font-medium">
                    {role.name}
                    {role.is_system && (
                      <Badge tone="info" className="gap-1">
                        <Lock className="h-3 w-3" aria-hidden="true" />
                        {t("roles.system")}
                      </Badge>
                    )}
                  </div>
                  <span className="text-xs text-[var(--muted)]">
                    {role.description || role.slug}
                  </span>
                </TableCell>
                <TableCell className="text-sm text-[var(--muted)]">{summarise(role)}</TableCell>
                <TableCell className="text-end text-sm">{role.users_count ?? 0}</TableCell>
                <TableCell>
                  <div className="flex justify-end gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      aria-label={
                        role.is_system || !canUpdate
                          ? t("roles.viewRole", { name: role.name })
                          : t("roles.editRole", { name: role.name })
                      }
                      onClick={() => setEditing(role)}
                    >
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    {canDelete && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-[var(--color-danger)]"
                        disabled={role.is_system}
                        title={role.is_system ? t("roles.systemLocked") : undefined}
                        aria-label={t("roles.deleteRole", { name: role.name })}
                        onClick={() => setDeleting(role)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <Pagination
          page={currentPage}
          lastPage={lastPage}
          onPageChange={setPage}
          label={`${t("common.page")} ${currentPage} ${t("common.of")} ${lastPage}`}
        />
      </div>

      <Drawer
        open={creating || !!editing}
        onClose={() => {
          setCreating(false);
          setEditing(null);
        }}
        width="max-w-2xl"
        title={creating ? t("roles.new") : (editing?.name ?? t("roles.edit"))}
      >
        <RoleForm
          key={editing?.id ?? "new"}
          role={editing}
          matrix={matrix}
          readOnly={!!editing?.is_system || (!!editing && !canUpdate) || (creating && !canCreate)}
          onDone={() => {
            setCreating(false);
            setEditing(null);
          }}
        />
      </Drawer>

      <Dialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title={t("roles.delete")}
        description={
          deleting && (deleting.users_count ?? 0) > 0
            ? t("roles.deleteWithMembers", {
                name: deleting.name,
                count: deleting.users_count ?? 0,
              })
            : t("roles.deleteConfirm", { name: deleting?.name ?? "" })
        }
        confirmLabel={t("common.delete")}
        confirmVariant="danger"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
      />
    </div>
  );
}
