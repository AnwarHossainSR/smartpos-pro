import { SystemHealth } from "@/features/super-admin/components/system-health";

export default function SuperAdminHealthPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-lg font-semibold">System Health</h1>
      <SystemHealth />
    </div>
  );
}
