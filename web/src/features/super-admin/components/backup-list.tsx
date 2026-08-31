import { CheckCircle, Database, Download, Loader2, Plus, Trash2, XCircle } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { Pagination } from "@/components/ui/pagination";
import { useBackupMutations, useBackups } from "../hooks";
import type { BackupLog } from "../types";

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

function getStatusBadge(status: string): "success" | "warning" | "danger" | "info" {
  switch (status) {
    case "completed":
      return "success";
    case "pending":
      return "warning";
    case "failed":
      return "danger";
    default:
      return "info";
  }
}

function getStatusIcon(status: string) {
  switch (status) {
    case "completed":
      return <CheckCircle className="h-4 w-4 text-[var(--success)]" />;
    case "pending":
      return <Loader2 className="h-4 w-4 animate-spin text-[var(--warning)]" />;
    case "failed":
      return <XCircle className="h-4 w-4 text-[var(--danger)]" />;
    default:
      return <Database className="h-4 w-4 text-[var(--muted)]" />;
  }
}

export function BackupList() {
  const list = useBackups();
  const { create, remove, download } = useBackupMutations();
  const [deleting, setDeleting] = useState<BackupLog | null>(null);

  const hasPending = list.data?.some((b) => b.status === "pending") ?? false;

  const handleDownload = async (backup: BackupLog) => {
    const result = await download.mutateAsync(backup.id);
    if (result.data.url) {
      window.open(result.data.url, "_blank");
    }
  };

  const columns: DataTableColumn<BackupLog>[] = [
    {
      key: "filename",
      header: "Filename",
      render: (log) => (
        <div className="flex items-center gap-2">
          <Database className="h-4 w-4 text-[var(--muted)]" />
          <span className="text-sm font-medium">{log.filename}</span>
        </div>
      ),
    },
    {
      key: "size",
      header: "Size",
      render: (log) => <span className="text-sm text-[var(--muted)]">{log.size_human}</span>,
    },
    {
      key: "status",
      header: "Status",
      render: (log) => (
        <div className="flex items-center gap-2">
          {getStatusIcon(log.status)}
          <Badge tone={getStatusBadge(log.status)}>
            {log.status === "pending" ? "Processing..." : log.status}
          </Badge>
        </div>
      ),
    },
    {
      key: "created_by",
      header: "Created By",
      render: (log) => (
        <span className="text-sm text-[var(--muted)]">{log.created_by?.name ?? "—"}</span>
      ),
    },
    {
      key: "created_at",
      header: "Date",
      render: (log) => (
        <span className="text-sm text-[var(--muted)]">{formatDate(log.created_at)}</span>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Database Backups</h2>
          <p className="text-sm text-[var(--muted)]">
            Backups are stored securely in Cloudflare R2
          </p>
        </div>
        <Button onClick={() => create.mutate()} disabled={create.isPending}>
          {create.isPending ? (
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          ) : (
            <Plus className="mr-2 h-4 w-4" />
          )}
          Create Backup
        </Button>
      </div>

      {hasPending && (
        <div className="flex items-center gap-2 rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm text-[var(--muted)]">
          <Loader2 className="h-4 w-4 animate-spin" />
          Processing backup... auto-refreshing every 3 seconds
        </div>
      )}

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={list.data}
          rowKey={(log) => log.id}
          loading={list.isLoading}
          loadingRows={5}
          error={list.error}
          onRetry={list.refetch}
          emptyMessage="No backups found."
          actions={(log) => (
            <div className="flex items-center gap-1">
              {log.status === "completed" && (
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => handleDownload(log)}
                  title="Download"
                >
                  <Download className="h-3.5 w-3.5" />
                </Button>
              )}
              <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8 text-[var(--danger)]"
                onClick={() => setDeleting(log)}
                title="Delete"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            </div>
          )}
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
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Delete Backup"
        description={`Are you sure you want to delete "${deleting?.filename}"? This will also remove the file from R2 storage. This action cannot be undone.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        onConfirm={() => {
          if (deleting) {
            remove.mutate(deleting.id, {
              onSuccess: () => setDeleting(null),
            });
          }
        }}
        loading={remove.isPending}
      />
    </div>
  );
}
