"use client";

import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Pencil, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, ApiError, type Paginated } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";

interface Role { id: number; slug: string; name: string; }
interface TeamUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  roles: string[];
}

const schema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  password: z.string().min(8).optional().or(z.literal("")),
  roles: z.array(z.string()).min(1, "Assign at least one role"),
});
type FormValues = z.infer<typeof schema>;

function UserForm({ defaultValues, userId, isEdit, roles, onSuccess }: { defaultValues?: Partial<FormValues>; userId?: number; isEdit: boolean; roles: Role[]; onSuccess: () => void }) {
  const qc = useQueryClient();
  const { register, handleSubmit, formState: { errors }, watch, setValue } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { roles: [], ...defaultValues },
  });

  const selectedRoles = watch("roles") ?? [];

  const toggleRole = (slug: string) => {
    setValue("roles", selectedRoles.includes(slug) ? selectedRoles.filter((r) => r !== slug) : [...selectedRoles, slug]);
  };

  const mutation = useMutation({
    mutationFn: (data: FormValues) =>
      isEdit ? api(`/users/${userId}`, { method: "PUT", body: data }) : api("/users", { method: "POST", body: data }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["team"] }); toast(isEdit ? "User updated" : "User invited"); onSuccess(); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  return (
    <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="space-y-4">
      <div><Label>Name *</Label><Input {...register("name")} />{errors.name && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.name.message}</p>}</div>
      <div><Label>Email *</Label><Input type="email" {...register("email")} />{errors.email && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.email.message}</p>}</div>
      <div><Label>Phone</Label><Input {...register("phone")} /></div>
      <div><Label>{isEdit ? "New password (leave blank to keep)" : "Password (optional)"}</Label><Input type="password" {...register("password")} placeholder={isEdit ? "Unchanged" : "Min 8 chars"} /></div>
      <div>
        <Label>Roles *</Label>
        <div className="mt-1 flex flex-wrap gap-2">
          {roles.map((r) => (
            <button key={r.slug} type="button" onClick={() => toggleRole(r.slug)}
              className={`rounded-full px-3 py-1 text-sm border transition ${selectedRoles.includes(r.slug) ? "bg-[var(--primary)] text-[var(--primary-fg)] border-[var(--primary)]" : "border-[var(--border)]"}`}
            >{r.name}</button>
          ))}
        </div>
        {errors.roles && <p className="mt-1 text-xs text-[var(--color-danger)]">{errors.roles.message}</p>}
      </div>
      <Button type="submit" disabled={mutation.isPending} className="w-full">{mutation.isPending ? "Saving…" : isEdit ? "Update user" : "Invite user"}</Button>
    </form>
  );
}

export default function TeamPage() {
  const { can } = useAuth();
  const qc = useQueryClient();
  const [search, setSearch] = useState(""); const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<TeamUser | null>(null);
  const [deleting, setDeleting] = useState<TeamUser | null>(null);

  const { data, isLoading } = useQuery<Paginated<TeamUser>>({
    queryKey: ["team", search, page],
    queryFn: () => api<Paginated<TeamUser>>(`/users?search=${encodeURIComponent(search)}&page=${page}&per_page=20`),
  });

  const { data: rolesData } = useQuery<{ data: Role[] }>({
    queryKey: ["roles-list"],
    queryFn: () => api<{ data: Role[] }>("/roles?per_page=50"),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api(`/users/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["team"] }); toast("User removed"); setDeleting(null); },
    onError: (e: unknown) => toast(e instanceof ApiError ? e.message : "Error", "error"),
  });

  const roles = rolesData?.data ?? [];
  const statusColor: Record<string, "success" | "warning" | "default"> = { active: "success", invited: "warning", suspended: "danger" as "default" };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Team</h1>
        {can("team.create") && <Button size="sm" onClick={() => { setEditing(null); setDrawerOpen(true); }}><Plus className="h-4 w-4" />Invite user</Button>}
      </div>
      <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder="Search…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} /></div>
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <Table>
          <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Email</TableHead><TableHead>Roles</TableHead><TableHead>Status</TableHead><TableHead className="w-20" /></TableRow></TableHeader>
          <TableBody>
            {isLoading ? Array.from({ length: 5 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 5 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
              : data?.data?.length === 0 ? <TableRow><TableCell colSpan={5} className="py-12 text-center text-[var(--muted)]">No team members yet.</TableCell></TableRow>
              : data?.data?.map((u) => (
                <TableRow key={u.id}>
                  <TableCell className="font-medium">{u.name}{u.phone && <span className="block text-xs text-[var(--muted)]">{u.phone}</span>}</TableCell>
                  <TableCell className="text-sm">{u.email}</TableCell>
                  <TableCell><div className="flex flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} tone="info">{r}</Badge>)}</div></TableCell>
                  <TableCell><Badge tone={statusColor[u.status] ?? "default"}>{u.status}</Badge></TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {can("team.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(u); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
                      {can("team.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(u)}><Trash2 className="h-3.5 w-3.5" /></Button>}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>
        {data?.meta && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-3">
            <p className="text-sm text-[var(--muted)]">Page {data.meta.current_page} of {data.meta.last_page}</p>
            <div className="flex gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}><ChevronLeft className="h-4 w-4 rtl:scale-x-[-1]" /></Button>
              <Button variant="outline" size="icon" className="h-8 w-8" disabled={page >= (data.meta.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}><ChevronRight className="h-4 w-4 rtl:scale-x-[-1]" /></Button>
            </div>
          </div>
        )}
      </div>
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : "Invite user"}>
        <UserForm key={editing?.id ?? "new"} isEdit={!!editing} userId={editing?.id} roles={roles} defaultValues={editing ? { name: editing.name, email: editing.email, phone: editing.phone ?? undefined, roles: editing.roles } : undefined} onSuccess={() => setDrawerOpen(false)} />
      </Drawer>
      <Dialog open={!!deleting} onClose={() => setDeleting(null)} title="Remove user" description={`Remove "${deleting?.name}" from the team?`} confirmLabel="Remove" confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />
    </div>
  );
}
