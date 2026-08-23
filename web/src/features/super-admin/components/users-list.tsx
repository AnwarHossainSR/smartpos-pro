import { useState } from "react";
import { Ban, Play, LogOut, ShieldCheck } from "lucide-react";
import { useUsers, useUserMutations } from "../hooks";
import { useAuth } from "@/stores/auth";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog } from "@/components/ui/dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import type { PlatformUser } from "../types";

export function UsersList() {
  const [toggling, setToggling] = useState<PlatformUser | null>(null);
  const [loggingOut, setLoggingOut] = useState<PlatformUser | null>(null);

  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- super-admin slugs not in permission union yet
  const canManage = useAuth((s) => s.can("super-admin.tenants.manage" as any));

  const list = useUsers();
  const mutations = useUserMutations();

  const columns: DataTableColumn<PlatformUser>[] = [
    {
      key: "name",
      header: "Name",
      className: "font-medium",
      render: (u) => (
        <>
          {u.name}
          {u.is_super_admin && (
            <Badge tone="info" className="ml-2">Super Admin</Badge>
          )}
        </>
      ),
    },
    {
      key: "email",
      header: "Email",
      render: (u) => (
        <>
          {u.email}
          {u.email_verified_at && (
            <ShieldCheck className="ml-1 inline h-3.5 w-3.5 text-[var(--color-success)]" />
          )}
        </>
      ),
    },
    {
      key: "tenant_name",
      header: "Tenant",
      render: (u) => u.tenant_name ?? "—",
    },
    {
      key: "status",
      header: "Status",
      render: (u) => (
        <Badge tone={u.status === "active" ? "success" : u.status === "suspended" ? "danger" : "warning"}>
          {u.status}
        </Badge>
      ),
    },
    {
      key: "roles",
      header: "Roles",
      render: (u) =>
        u.roles && u.roles.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {u.roles.slice(0, 3).map((role) => (
              <Badge key={role} tone="info" className="text-xs">{role}</Badge>
            ))}
            {u.roles.length > 3 && (
              <Badge tone="info" className="text-xs">+{u.roles.length - 3}</Badge>
            )}
          </div>
        ) : (
          "—"
        ),
    },
    {
      key: "last_login_at",
      header: "Last Login",
      render: (u) =>
        u.last_login_at
          ? new Date(u.last_login_at).toLocaleDateString()
          : "Never",
    },
  ];

  return (
    <div className="space-y-4">
      <ListToolbar
        search={list.searchInput}
        onSearchChange={list.setSearch}
        searchPlaceholder="Search users…"
        filters={
          <Select
            className="w-40"
            value={(list.filters as Record<string, string>).status ?? ""}
            onChange={(e) =>
              list.setFilter(
                "status" as never,
                e.target.value || (undefined as never),
              )
            }
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="inactive">Inactive</option>
          </Select>
        }
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(u) => u.id}
          loading={list.isLoading}
          loadingRows={6}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No users found."
          actions={(u) =>
            canManage && !u.is_super_admin ? (
              <>
                {u.status === "active" ? (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-[var(--color-warning)]"
                    onClick={() => setToggling(u)}
                    title="Suspend user"
                  >
                    <Ban className="h-3.5 w-3.5" />
                  </Button>
                ) : (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-[var(--color-success)]"
                    onClick={() => setToggling(u)}
                    title="Activate user"
                  >
                    <Play className="h-3.5 w-3.5" />
                  </Button>
                )}
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-[var(--color-danger)]"
                  onClick={() => setLoggingOut(u)}
                  title="Force logout"
                >
                  <LogOut className="h-3.5 w-3.5" />
                </Button>
              </>
            ) : null
          }
        />
        {list.meta && (
          <Pagination
            page={list.page}
            lastPage={list.lastPage}
            onPageChange={list.setPage}
            label={`Page ${list.meta.current_page} of ${list.meta.last_page}`}
          />
        )}
      </div>

      <Dialog
        open={!!toggling}
        onClose={() => setToggling(null)}
        title={toggling?.status === "active" ? "Suspend user" : "Activate user"}
        description={
          toggling?.status === "active"
            ? `Suspend "${toggling?.name}"? They will lose access to the platform.`
            : `Activate "${toggling?.name}"?`
        }
        confirmLabel={toggling?.status === "active" ? "Suspend" : "Activate"}
        confirmVariant={toggling?.status === "active" ? "danger" : "primary"}
        onConfirm={() => {
          if (!toggling) return;
          const newStatus = toggling.status === "active" ? "suspended" : "active";
          mutations.toggleStatus.mutate(
            { id: toggling.id, status: newStatus },
            { onSuccess: () => setToggling(null) },
          );
        }}
        loading={mutations.toggleStatus.isPending}
      />

      <Dialog
        open={!!loggingOut}
        onClose={() => setLoggingOut(null)}
        title="Force logout"
        description={`Force "${loggingOut?.name}" to log out? Their current session will be terminated.`}
        confirmLabel="Force logout"
        confirmVariant="danger"
        onConfirm={() => {
          if (!loggingOut) return;
          mutations.forceLogout.mutate(loggingOut.id, {
            onSuccess: () => setLoggingOut(null),
          });
        }}
        loading={mutations.forceLogout.isPending}
      />
    </div>
  );
}
