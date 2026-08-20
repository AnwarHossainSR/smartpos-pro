"use client";

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
import { api, ApiError, type Paginated } from "@/lib/api";
import { type PermissionSlug } from "@/lib/permissions";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { useAuth } from "@/stores/auth";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Pencil, Trash2 } from "lucide-react";
import { useState } from "react";

export interface ResourceRecord {
  id: string;
  [key: string]: unknown;
}

export interface ResourceField {
  name: string;
  label: string;
  type: "text" | "number" | "email" | "textarea" | "checkbox" | "select";
  required?: boolean;
  placeholder?: string;
  options?: { value: string; label: string }[];
  help?: string;
}

export interface ResourceColumn<T> {
  header: string;
  cell: (row: T) => React.ReactNode;
  align?: "start" | "end";
}

interface ResourceManagerProps<T extends ResourceRecord> {
  endpoint: string;
  queryKey: string;
  singular: string;
  columns: ResourceColumn<T>[];
  fields: ResourceField[];
  permissions: { create: PermissionSlug; update: PermissionSlug; delete: PermissionSlug };
  searchPlaceholder?: string;
  emptyLabel?: string;
  perPage?: number;
}

type FormState = Record<string, string | boolean>;

function initialForm(fields: ResourceField[], row?: ResourceRecord): FormState {
  const state: FormState = {};
  for (const field of fields) {
    const value = row?.[field.name];
    state[field.name] =
      field.type === "checkbox" ? value !== false : value === null || value === undefined ? "" : String(value);
  }
  return state;
}

function payloadFrom(fields: ResourceField[], form: FormState): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    const value = form[field.name];
    if (field.type === "checkbox") payload[field.name] = Boolean(value);
    else if (value === "") payload[field.name] = null;
    else if (field.type === "number") payload[field.name] = Number(value);
    else payload[field.name] = value;
  }
  return payload;
}

export function ResourceManager<T extends ResourceRecord>({
  endpoint,
  queryKey,
  singular,
  columns,
  fields,
  permissions,
  searchPlaceholder,
  emptyLabel,
  perPage = 20,
}: ResourceManagerProps<T>) {
  const { can } = useAuth();
  const qc = useQueryClient();

  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<T | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<T | null>(null);
  const [form, setForm] = useState<FormState>(() => initialForm(fields));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const debouncedSearch = useDebouncedValue(search);

  const { data, isLoading } = useQuery({
    queryKey: [queryKey, debouncedSearch, page],
    queryFn: () => {
      const params = new URLSearchParams({ per_page: String(perPage), page: String(page) });
      if (debouncedSearch) params.set("search", debouncedSearch);
      return api<Paginated<T>>(`${endpoint}?${params.toString()}`);
    },
  });

  const closeForm = () => {
    setCreating(false);
    setEditing(null);
    setFieldErrors({});
  };

  const save = useMutation({
    mutationFn: () => {
      const body = payloadFrom(fields, form);
      return editing
        ? api(`${endpoint}/${editing.id}`, { method: "PUT", body })
        : api(endpoint, { method: "POST", body });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [queryKey] });
      toast(editing ? `${singular} updated` : `${singular} created`);
      closeForm();
    },
    onError: (e: unknown) => {
      if (e instanceof ApiError && e.errors) setFieldErrors(e.errors);
      toast(e instanceof ApiError ? e.message : `Could not save the ${singular.toLowerCase()}`, "error");
    },
  });

  const remove = useMutation({
    mutationFn: () => api(`${endpoint}/${deleting!.id}`, { method: "DELETE" }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [queryKey] });
      toast(`${singular} deleted`);
      setDeleting(null);
    },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Delete failed", "error"),
  });

  const rows = data?.data ?? [];
  const lastPage = data?.meta?.last_page ?? 1;

  return (
    <div className="space-y-4">
      <ListToolbar
        search={search}
        onSearchChange={(value) => {
          setSearch(value);
          setPage(1);
        }}
        searchPlaceholder={searchPlaceholder ?? `Search ${singular.toLowerCase()}…`}
        addLabel={can(permissions.create) ? `Add ${singular.toLowerCase()}` : undefined}
        onAdd={
          can(permissions.create)
            ? () => {
                setForm(initialForm(fields));
                setCreating(true);
              }
            : undefined
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((column) => (
                <TableHead key={column.header} className={column.align === "end" ? "text-end" : undefined}>
                  {column.header}
                </TableHead>
              ))}
              <TableHead className="w-24 text-end">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 5 }).map((_, index) => (
                <TableRow key={index}>
                  <TableCell colSpan={columns.length + 1}>
                    <Skeleton className="h-5 w-full" />
                  </TableCell>
                </TableRow>
              ))}

            {!isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={columns.length + 1} className="py-10 text-center text-[var(--muted)]">
                  {emptyLabel ?? `No ${singular.toLowerCase()} yet`}
                </TableCell>
              </TableRow>
            )}

            {rows.map((row) => (
              <TableRow key={row.id}>
                {columns.map((column) => (
                  <TableCell key={column.header} className={column.align === "end" ? "text-end" : undefined}>
                    {column.cell(row)}
                  </TableCell>
                ))}
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {can(permissions.update) && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        aria-label="Edit"
                        onClick={() => {
                          setForm(initialForm(fields, row));
                          setEditing(row);
                        }}
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </Button>
                    )}
                    {can(permissions.delete) && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-[var(--color-danger)]"
                        aria-label="Delete"
                        onClick={() => setDeleting(row)}
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
      </div>

      <Pagination
        page={page}
        lastPage={lastPage}
        onPageChange={setPage}
        label={`Page ${page} of ${lastPage}`}
      />

      <Drawer
        open={creating || !!editing}
        onClose={closeForm}
        title={editing ? `Edit ${singular.toLowerCase()}` : `Add ${singular.toLowerCase()}`}
      >
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            save.mutate();
          }}
        >
          {fields.map((field) => (
            <div key={field.name} className="space-y-1">
              {field.type === "checkbox" ? (
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={Boolean(form[field.name])}
                    onChange={(e) => setForm((f) => ({ ...f, [field.name]: e.target.checked }))}
                  />
                  {field.label}
                </label>
              ) : (
                <>
                  <Label htmlFor={field.name}>
                    {field.label} {field.required && "*"}
                  </Label>
                  {field.type === "select" ? (
                    <Select
                      id={field.name}
                      value={String(form[field.name] ?? "")}
                      onChange={(e) => setForm((f) => ({ ...f, [field.name]: e.target.value }))}
                    >
                      <option value="">— None —</option>
                      {field.options?.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </Select>
                  ) : field.type === "textarea" ? (
                    <textarea
                      id={field.name}
                      rows={3}
                      value={String(form[field.name] ?? "")}
                      placeholder={field.placeholder}
                      onChange={(e) => setForm((f) => ({ ...f, [field.name]: e.target.value }))}
                      className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] p-3 text-sm"
                    />
                  ) : (
                    <Input
                      id={field.name}
                      type={field.type}
                      value={String(form[field.name] ?? "")}
                      placeholder={field.placeholder}
                      onChange={(e) => setForm((f) => ({ ...f, [field.name]: e.target.value }))}
                    />
                  )}
                </>
              )}
              {field.help && <p className="text-xs text-[var(--muted)]">{field.help}</p>}
              {fieldErrors[field.name]?.map((message) => (
                <p key={message} className="text-xs text-[var(--color-danger)]">
                  {message}
                </p>
              ))}
            </div>
          ))}

          <Button type="submit" className="w-full" disabled={save.isPending}>
            {save.isPending ? "Saving…" : "Save"}
          </Button>
        </form>
      </Drawer>

      {deleting && (
        <Dialog
          open
          title={`Delete ${singular.toLowerCase()}`}
          description={`Delete "${String(deleting.name ?? deleting.id)}"? This cannot be undone.`}
          confirmLabel="Delete"
          confirmVariant="danger"
          loading={remove.isPending}
          onConfirm={() => remove.mutate()}
          onClose={() => setDeleting(null)}
        />
      )}
    </div>
  );
}

export function ActiveBadge({ active }: { active: boolean }) {
  return <Badge tone={active ? "success" : "default"}>{active ? "Active" : "Inactive"}</Badge>;
}
