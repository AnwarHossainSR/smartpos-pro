import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Search, Pencil, Trash2, Clock, KeyRound, Shield } from "lucide-react";
import { Pagination } from "@/components/ui/pagination";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { api, errorMessage, type Paginated } from "@/lib/api";
import { useRoles } from "@/lib/lookups";
import { buildListQuery, useResourceList } from "@/lib/use-resource-list";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Drawer } from "@/components/ui/drawer";
import { Dialog } from "@/components/ui/dialog";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { toast } from "@/components/ui/toast";
import { useAuth } from "@/stores/auth";
import { useTranslation } from "@/i18n/context";
import { queryKeys } from "@/lib/query-keys";
import { RolesTab } from "@/features/roles/roles-tab";
import type { Role } from "@/types/domain";

interface TeamUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  roles: string[];
  has_approval_pin?: boolean;
}

interface ShiftRow {
  user_id: string;
  user_name: string;
  period: string;
  shifts: number;
  minutes: number;
  hours: number;
  overtime_minutes: number;
  overtime_hours: number;
  is_overtime: boolean;
}

interface ShiftReport {
  from: string;
  to: string;
  group_by: string;
  rows: ShiftRow[];
    by_user: { user_id: string; user_name: string; shifts: number; hours: number; overtime_hours: number; is_overtime: boolean }[];
  totals: { shifts: number; minutes: number; hours: number; overtime_minutes: number; overtime_hours: number };
}

const schema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  password: z.string().min(8).optional().or(z.literal("")),
  roles: z.array(z.string()).min(1, "Assign at least one role"),
});
type FormValues = z.infer<typeof schema>;

function UserForm({ defaultValues, userId, isEdit, roles, onSuccess }: { defaultValues?: Partial<FormValues>; userId?: string; isEdit: boolean; roles: Role[]; onSuccess: () => void }) {
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
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.team.all }); toast(isEdit ? "User updated" : "User invited"); onSuccess(); },
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

function ShiftsTab() {
  const t = useTranslation();
  const { can } = useAuth();
  const [weekOffset, setWeekOffset] = useState(0);

  const weekStart = new Date();
  weekStart.setDate(weekStart.getDate() - weekStart.getDay() + 1 + weekOffset * 7);
  const weekEnd = new Date(weekStart);
  weekEnd.setDate(weekEnd.getDate() + 6);

  const from = weekStart.toISOString().slice(0, 10);
  const to = weekEnd.toISOString().slice(0, 10);

  const { data, isLoading } = useQuery<{ data: ShiftReport }>({
    queryKey: queryKeys.shifts.report({ from, to }),
    queryFn: () => api<{ data: ShiftReport }>(`/reports/shifts?from=${from}&to=${to}&group_by=week`),
  });

  const report = data?.data;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => setWeekOffset((w) => w - 1)}>←</Button>
          <span className="text-sm font-medium">{from} — {to}</span>
          <Button variant="outline" size="sm" onClick={() => setWeekOffset((w) => w + 1)}>→</Button>
          {weekOffset !== 0 && <Button variant="ghost" size="sm" onClick={() => setWeekOffset(0)}>Today</Button>}
        </div>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-12 rounded-[var(--radius)]" />)}</div>
      ) : report && report.by_user.length > 0 ? (
        <>
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("shifts.employee")}</TableHead>
                  <TableHead className="text-end">{t("shifts.shifts")}</TableHead>
                  <TableHead className="text-end">{t("shifts.hours")}</TableHead>
                  <TableHead className="text-end">{t("shifts.overtime")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {report.by_user.map((u) => (
                  <TableRow key={u.user_id}>
                    <TableCell className="font-medium">{u.user_name}</TableCell>
                    <TableCell className="text-end">{u.shifts}</TableCell>
                    <TableCell className="text-end">{u.hours.toFixed(1)}h</TableCell>
                    <TableCell className="text-end">
                      {u.overtime_hours > 0 ? (
                        <Badge tone="warning">{u.overtime_hours.toFixed(1)}h</Badge>
                      ) : (
                        <span className="text-[var(--muted)]">—</span>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {can("reports.view") && report.rows.length > 0 && (
            <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
              <h3 className="mb-2 text-sm font-medium">{t("shifts.dailyBreakdown")}</h3>
              <div className="rounded-[var(--radius)] border border-[var(--border)]">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("shifts.employee")}</TableHead>
                      <TableHead>{t("shifts.date")}</TableHead>
                      <TableHead className="text-end">{t("shifts.shifts")}</TableHead>
                      <TableHead className="text-end">{t("shifts.hours")}</TableHead>
                      <TableHead className="text-end">{t("shifts.overtime")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {report.rows.map((r, i) => (
                      <TableRow key={i}>
                        <TableCell>{r.user_name}</TableCell>
                        <TableCell className="text-sm">{r.period}</TableCell>
                        <TableCell className="text-end">{r.shifts}</TableCell>
                        <TableCell className="text-end">{r.hours.toFixed(1)}h</TableCell>
                        <TableCell className="text-end">
                          {r.is_overtime ? <Badge tone="warning">{r.overtime_hours.toFixed(1)}h</Badge> : "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>
          )}

          <div className="flex gap-4 text-sm text-[var(--muted)]">
            <span>{t("shifts.totalHours")}: <strong className="text-[var(--foreground)]">{report.totals.hours.toFixed(1)}h</strong></span>
            <span>{t("shifts.totalOvertime")}: <strong className="text-amber-600">{report.totals.overtime_hours.toFixed(1)}h</strong></span>
          </div>
        </>
      ) : (
        <div className="py-12 text-center text-[var(--muted)]">{t("shifts.empty")}</div>
      )}
    </div>
  );
}

type TeamTab = "members" | "roles" | "shifts";

export default function TeamPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const qc = useQueryClient();

  const canViewMembers = can("users.view");
  const canViewRoles = can("roles.view");
  const canViewShifts = can("shifts.view");

  // The route guard lets anyone through who holds *any* of the three grants, so the landing
  // tab has to be the first one this particular user can actually see.
  const [tab, setTab] = useState<TeamTab>(() =>
    canViewMembers ? "members" : canViewRoles ? "roles" : "shifts",
  );
  const [drawerOpen, setDrawerOpen] = useState(false); const [editing, setEditing] = useState<TeamUser | null>(null);
  const [deleting, setDeleting] = useState<TeamUser | null>(null);
  const [revokingPin, setRevokingPin] = useState<TeamUser | null>(null);

  const list = useResourceList<TeamUser>({
    queryKey: queryKeys.team.lists(),
    queryFn: (p) => api<Paginated<TeamUser>>(`/users?${buildListQuery(p)}`),
    enabled: canViewMembers,
  });

  // `/roles` is guarded by `roles.view` — fetching it unconditionally 403s for anyone who can
  // manage members but not roles, which is exactly the branch-manager case.
  const { roles } = useRoles({ enabled: canViewRoles });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api(`/users/${id}`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.team.all }); toast("User removed"); setDeleting(null); },
  });

  const revokePinMutation = useMutation({
    mutationFn: (id: string) => api(`/users/${id}/approval-pin`, { method: "DELETE" }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: queryKeys.team.all }); toast("Approval PIN revoked"); setRevokingPin(null); },
  });

  const statusColor: Record<string, "success" | "warning" | "default"> = { active: "success", invited: "warning", suspended: "danger" as "default" };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("team.title")}</h1>
        {can("users.create") && tab === "members" && <Button size="sm" onClick={() => { setEditing(null); setDrawerOpen(true); }}><Plus className="h-4 w-4" />{t("team.inviteUser")}</Button>}
      </div>

      <div role="tablist" aria-label={t("team.title")} className="flex gap-2 border-b border-[var(--border)] pb-2">
        {canViewMembers && <Button role="tab" aria-selected={tab === "members"} size="sm" variant={tab === "members" ? "primary" : "ghost"} onClick={() => setTab("members")}>{t("team.members")}</Button>}
        {canViewRoles && <Button role="tab" aria-selected={tab === "roles"} size="sm" variant={tab === "roles" ? "primary" : "ghost"} onClick={() => setTab("roles")}><Shield className="h-3.5 w-3.5" /> {t("team.rolesTab")}</Button>}
        {canViewShifts && <Button role="tab" aria-selected={tab === "shifts"} size="sm" variant={tab === "shifts" ? "primary" : "ghost"} onClick={() => setTab("shifts")}><Clock className="h-3.5 w-3.5" /> {t("team.shiftsTab")}</Button>}
      </div>

      {tab === "roles" ? (
        <RolesTab />
      ) : tab === "members" ? (
        <>
          <div className="relative"><Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" /><Input className="ps-9" placeholder={t("team.searchPlaceholder")} value={list.searchInput} onChange={(e) => list.setSearch(e.target.value)} /></div>
          <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
            <Table>
              <TableHeader><TableRow><TableHead>{t("team.name")}</TableHead><TableHead>{t("team.email")}</TableHead><TableHead>{t("team.roles")}</TableHead><TableHead>{t("team.status")}</TableHead><TableHead className="w-20" /></TableRow></TableHeader>
              <TableBody>
                {list.isLoading ? Array.from({ length: 5 }).map((_, i) => <TableRow key={i}>{Array.from({ length: 5 }).map((__, j) => <TableCell key={j}><Skeleton className="h-4" /></TableCell>)}</TableRow>)
                  : list.isError ? <TableRow><TableCell colSpan={5} className="py-12 text-center"><p className="text-sm text-[var(--muted)]">{errorMessage(list.error, t("team.empty"))}</p><Button variant="outline" size="sm" className="mt-3" onClick={() => list.refetch()}>{t("tables.retry")}</Button></TableCell></TableRow>
                  : list.isEmpty ? <TableRow><TableCell colSpan={5} className="py-12 text-center text-[var(--muted)]">{t("team.empty")}</TableCell></TableRow>
                  : list.data.map((u) => (
                    <TableRow key={u.id}>
                      <TableCell className="font-medium">{u.name}{u.phone && <span className="block text-xs text-[var(--muted)]">{u.phone}</span>}</TableCell>
                      <TableCell className="text-sm">{u.email}</TableCell>
                      <TableCell><div className="flex flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} tone="info">{r}</Badge>)}{u.has_approval_pin && <Badge tone="success">PIN</Badge>}</div></TableCell>
                      <TableCell><Badge tone={statusColor[u.status] ?? "default"}>{u.status}</Badge></TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          {can("users.update") && <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => { setEditing(u); setDrawerOpen(true); }}><Pencil className="h-3.5 w-3.5" /></Button>}
                          {can("users.update") && u.has_approval_pin && <Button variant="ghost" size="icon" className="h-8 w-8" title="Revoke approval PIN" onClick={() => setRevokingPin(u)}><KeyRound className="h-3.5 w-3.5" /></Button>}
                          {can("users.delete") && <Button variant="ghost" size="icon" className="h-8 w-8 text-[var(--color-danger)]" onClick={() => setDeleting(u)}><Trash2 className="h-3.5 w-3.5" /></Button>}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
              </TableBody>
            </Table>
            {list.meta && (
              <Pagination
                page={list.page}
                lastPage={list.lastPage}
                onPageChange={list.setPage}
                label={`Page ${list.meta.current_page} of ${list.meta.last_page}`}
              />
            )}
          </div>
          <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)} title={editing ? `Edit: ${editing.name}` : t("team.inviteUser")}>
            <UserForm key={editing?.id ?? "new"} isEdit={!!editing} userId={editing?.id} roles={roles} defaultValues={editing ? { name: editing.name, email: editing.email, phone: editing.phone ?? undefined, roles: editing.roles } : undefined} onSuccess={() => setDrawerOpen(false)} />
          </Drawer>
          <Dialog open={!!deleting} onClose={() => setDeleting(null)} title={t("team.removeUser")} description={`${t("team.removeConfirm")} "${deleting?.name}"?`} confirmLabel={t("team.remove")} confirmVariant="danger" onConfirm={() => deleting && deleteMutation.mutate(deleting.id)} loading={deleteMutation.isPending} />
          <Dialog
            open={!!revokingPin}
            onClose={() => setRevokingPin(null)}
            title="Revoke approval PIN"
            description={`${revokingPin?.name} will no longer be able to authorise discounts, price overrides or voids until they set a new PIN. You cannot set one for them.`}
            confirmLabel="Revoke PIN"
            confirmVariant="danger"
            onConfirm={() => revokingPin && revokePinMutation.mutate(revokingPin.id)}
            loading={revokePinMutation.isPending}
          />
        </>
      ) : (
        <ShiftsTab />
      )}
    </div>
  );
}
