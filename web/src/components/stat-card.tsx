import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

export function StatCard({
  label,
  value,
  hint,
  loading,
  tone = "default",
}: {
  label: string;
  value: string | number;
  hint?: string;
  loading?: boolean;
  tone?: "default" | "success" | "warning" | "danger";
}) {
  const toneColor = {
    default: "text-foreground",
    success: "text-green-600 dark:text-green-400",
    warning: "text-amber-600 dark:text-amber-400",
    danger: "text-red-600 dark:text-red-400",
  }[tone];

  return (
    <Card className="p-5">
      <div className="text-sm text-muted-foreground">{label}</div>
      {loading ? (
        <Skeleton className="mt-2 h-8 w-24" />
      ) : (
        <div className={cn("mt-1 text-2xl font-semibold tracking-tight", toneColor)}>{value}</div>
      )}
      {hint && <div className="mt-1 text-xs text-muted-foreground">{hint}</div>}
    </Card>
  );
}
