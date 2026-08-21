import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { StopCircle } from "lucide-react";
import { superAdminApi } from "../api";
import { useImpersonationMutations } from "../hooks";
import { queryKeys } from "@/lib/query-keys";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Dialog } from "@/components/ui/dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import type { Impersonation } from "../types";

export function ImpersonationsList() {
  const [revoking, setRevoking] = useState<Impersonation | null>(null);
  const mutations = useImpersonationMutations();

  const query = useQuery({
    queryKey: queryKeys.superAdmin.impersonations(),
    queryFn: () => superAdminApi.impersonations.list(),
  });

  const data = query.data?.data ?? [];

  const columns: DataTableColumn<Impersonation>[] = [
    {
      key: "tenant",
      header: "Tenant",
      render: (row) => row.tenant?.name ?? "—",
    },
    {
      key: "targetUser",
      header: "Target User",
      render: (row) => (
        <>
          {row.targetUser?.name ?? "—"}
          <span className="block text-xs text-[var(--muted)]">
            {row.targetUser?.email ?? ""}
          </span>
        </>
      ),
    },
    {
      key: "superAdmin",
      header: "Started By",
      render: (row) => (
        <>
          {row.superAdmin?.name ?? "—"}
          <span className="block text-xs text-[var(--muted)]">
            {row.superAdmin?.email ?? ""}
          </span>
        </>
      ),
    },
    {
      key: "reason",
      header: "Reason",
      render: (row) => row.reason || "—",
    },
    {
      key: "started_at",
      header: "Started At",
      render: (row) => new Date(row.started_at).toLocaleString(),
    },
    {
      key: "status",
      header: "Status",
      render: (row) => (
        <Badge tone={row.ended_at ? "default" : "success"}>
          {row.ended_at ? "Ended" : "Active"}
        </Badge>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={data}
          rowKey={(row) => row.id}
          loading={query.isLoading}
          loadingRows={6}
          error={query.error}
          onRetry={query.refetch}
          emptyMessage="No active impersonation sessions."
          actions={(row) =>
            !row.ended_at ? (
              <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8 text-[var(--color-danger)]"
                onClick={() => setRevoking(row)}
              >
                <StopCircle className="h-3.5 w-3.5" />
              </Button>
            ) : null
          }
        />
      </div>

      <Dialog
        open={!!revoking}
        onClose={() => setRevoking(null)}
        title="Revoke impersonation"
        description={`End the impersonation session for "${revoking?.tenant?.name}"? The target user will be logged out.`}
        confirmLabel="Revoke"
        confirmVariant="danger"
        onConfirm={() => {
          if (!revoking) return;
          mutations.revoke.mutate(revoking.id, {
            onSuccess: () => setRevoking(null),
          });
        }}
        loading={mutations.revoke.isPending}
      />
    </div>
  );
}
