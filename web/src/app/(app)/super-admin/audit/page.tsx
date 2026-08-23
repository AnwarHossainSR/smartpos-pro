import { AuditLogList } from "@/features/super-admin/components/audit-log-list";

export default function AuditLogsPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Audit Logs</h1>
        <p className="text-[var(--muted)]">
          Platform-wide audit trail of admin actions.
        </p>
      </div>
      <AuditLogList />
    </div>
  );
}
