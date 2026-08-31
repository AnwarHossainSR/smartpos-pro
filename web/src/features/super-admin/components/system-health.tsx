import { useQuery, useQueryClient } from "@tanstack/react-query";
import { RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { queryKeys } from "@/lib/query-keys";
import { superAdminApi } from "../api";

export function SystemHealth() {
  const qc = useQueryClient();

  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: queryKeys.superAdmin.health(),
    queryFn: superAdminApi.health.get,
  });

  const h = data?.data;

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Card key={i}>
            <CardHeader>
              <Skeleton className="h-4 w-24" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-6 w-16 mb-2" />
              <Skeleton className="h-3 w-32" />
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <Card>
        <CardContent className="py-8 text-center">
          <p className="text-sm text-[var(--muted)]">Failed to load system health.</p>
          <Button variant="ghost" className="mt-2" onClick={() => refetch()}>
            Retry
          </Button>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div />
        <Button
          variant="ghost"
          size="sm"
          onClick={() => qc.invalidateQueries({ queryKey: queryKeys.superAdmin.health() })}
          disabled={isFetching}
        >
          <RefreshCw className={`mr-2 h-4 w-4 ${isFetching ? "animate-spin" : ""}`} />
          Refresh
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>PHP Version</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">{h?.php_version ?? "—"}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Laravel Version</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">{h?.laravel_version ?? "—"}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Database</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <div className="flex items-center gap-2">
              <Badge tone={h?.database.connected ? "success" : "danger"}>
                {h?.database.connected ? "Connected" : "Disconnected"}
              </Badge>
            </div>
            <p className="text-sm text-[var(--muted)]">Size: {h?.database.size ?? "—"}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Queue</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p className="text-sm">
              Driver: <span className="font-medium">{h?.queue.connection ?? "—"}</span>
            </p>
            <div className="flex gap-2">
              <Badge tone={h?.queue.pending === 0 ? "success" : "warning"}>
                {h?.queue.pending ?? 0} pending
              </Badge>
              <Badge tone={h?.queue.failed === 0 ? "success" : "danger"}>
                {h?.queue.failed ?? 0} failed
              </Badge>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Cache</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p className="text-sm">
              Driver: <span className="font-medium">{h?.cache.driver ?? "—"}</span>
            </p>
            <Badge tone={h?.cache.working ? "success" : "danger"}>
              {h?.cache.working ? "Working" : "Not working"}
            </Badge>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Disk Storage</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p className="text-sm">
              Total: <span className="font-medium">{h?.storage.total ?? "—"}</span>
            </p>
            <p className="text-sm">
              Free: <span className="font-medium">{h?.storage.free ?? "—"}</span>
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              Cloudflare R2
              {h?.r2?.configured ? (
                <Badge tone={h.r2.connected ? "success" : "danger"}>
                  {h.r2.connected ? "Connected" : "Error"}
                </Badge>
              ) : (
                <Badge tone="warning">Not Configured</Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {h?.r2?.configured ? (
              <>
                <p className="text-sm">
                  Bucket: <span className="font-medium">{h.r2.bucket ?? "—"}</span>
                </p>
              </>
            ) : (
              <p className="text-sm text-[var(--muted)]">
                Configure R2_* env variables to enable cloud backups
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
