import { AlertTriangle, Clock, MessageSquare } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { useSupportTickets, useTicketMetrics } from "../hooks";
import type { SupportTicket, TicketMetrics } from "../types";
import { SupportTicketDetailModal } from "./support-ticket-detail-modal";

type TicketFilters = { status: string; priority: string };

const STATUS_TONES: Record<string, "info" | "warning" | "danger" | "success" | "default"> = {
  open: "info",
  in_progress: "warning",
  waiting_on_customer: "danger",
  resolved: "success",
  closed: "default",
};

const STATUS_LABELS: Record<string, string> = {
  open: "Open",
  in_progress: "In Progress",
  waiting_on_customer: "Waiting",
  resolved: "Resolved",
  closed: "Closed",
};

const PRIORITY_TONES: Record<string, "danger" | "warning" | "info" | "default"> = {
  urgent: "danger",
  high: "warning",
  medium: "info",
  low: "default",
};

const PRIORITY_LABELS: Record<string, string> = {
  urgent: "Urgent",
  high: "High",
  medium: "Medium",
  low: "Low",
};

function formatRelativeTime(dateStr: string | null): string {
  if (!dateStr) return "—";
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return "Just now";
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

function MetricsCards({ metrics }: { metrics: TicketMetrics | undefined }) {
  if (!metrics) return null;

  const cards = [
    { label: "Open", value: metrics.total_open, color: "text-blue-500" },
    { label: "In Progress", value: metrics.total_in_progress, color: "text-yellow-500" },
    { label: "Waiting", value: metrics.total_waiting, color: "text-orange-500" },
    { label: "SLA Breached", value: metrics.sla_breached, color: "text-red-500" },
  ];

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      {cards.map((card) => (
        <Card key={card.label}>
          <CardContent className="p-4">
            <CardTitle className="text-xs">{card.label}</CardTitle>
            <p className={`mt-1 text-2xl font-semibold ${card.color}`}>{card.value}</p>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

export function SupportTicketList() {
  const [viewingId, setViewingId] = useState<string | null>(null);

  const [searchInput, setSearchInput] = useState("");
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<TicketFilters>({ status: "", priority: "" });

  const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const [debouncedSearch, setDebouncedSearch] = useState("");

  useEffect(() => {
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(searchInput);
      setPage(1);
    }, 300);
    return () => clearTimeout(debounceRef.current);
  }, [searchInput]);

  const params = {
    search: debouncedSearch || undefined,
    page: page > 1 ? page : undefined,
    status: filters.status || undefined,
    priority: filters.priority || undefined,
  };

  const { data, isLoading, error, refetch } = useSupportTickets(params);
  const { data: metricsResponse } = useTicketMetrics();
  const metrics = metricsResponse?.data;

  const tickets = data?.data ?? [];
  const meta = data?.meta;
  const lastPage = meta?.last_page ?? 1;

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
  }, []);

  const columns: DataTableColumn<SupportTicket>[] = [
    {
      key: "subject",
      header: "Subject",
      className: "font-medium max-w-[300px]",
      render: (t) => (
        <div className="flex items-center gap-2">
          {t.is_sla_breached && <AlertTriangle className="h-4 w-4 shrink-0 text-red-500" />}
          <span className="truncate">{t.subject}</span>
        </div>
      ),
    },
    {
      key: "customer",
      header: "Customer",
      render: (t) => t.user?.name ?? "—",
    },
    {
      key: "priority",
      header: "Priority",
      render: (t) => (
        <Badge tone={PRIORITY_TONES[t.priority] ?? "default"}>
          {PRIORITY_LABELS[t.priority] ?? t.priority}
        </Badge>
      ),
    },
    {
      key: "status",
      header: "Status",
      render: (t) => (
        <Badge tone={STATUS_TONES[t.status] ?? "default"}>
          {STATUS_LABELS[t.status] ?? t.status}
        </Badge>
      ),
    },
    {
      key: "last_reply_at",
      header: "Last Reply",
      render: (t) => (
        <span className="flex items-center gap-1 text-sm text-[var(--muted)]">
          <Clock className="h-3 w-3" />
          {formatRelativeTime(t.last_reply_at)}
        </span>
      ),
    },
    {
      key: "replies_count",
      header: "Replies",
      align: "right",
      render: (t) => (
        <span className="flex items-center justify-end gap-1 text-sm text-[var(--muted)]">
          <MessageSquare className="h-3 w-3" />
          {t.replies_count}
        </span>
      ),
    },
    {
      key: "created_at",
      header: "Created",
      render: (t) => new Date(t.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div className="space-y-4">
      <MetricsCards metrics={metrics} />

      <ListToolbar
        search={searchInput}
        onSearchChange={handleSearchChange}
        searchPlaceholder="Search tickets…"
        filters={
          <div className="flex gap-2">
            <Select
              className="w-40"
              value={filters.status}
              onChange={(e) => {
                setFilters((f) => ({ ...f, status: e.target.value }));
                setPage(1);
              }}
            >
              <option value="">All statuses</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting_on_customer">Waiting on Customer</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </Select>
            <Select
              className="w-36"
              value={filters.priority}
              onChange={(e) => {
                setFilters((f) => ({ ...f, priority: e.target.value }));
                setPage(1);
              }}
            >
              <option value="">All priorities</option>
              <option value="urgent">Urgent</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </Select>
          </div>
        }
      />

      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <DataTable
          columns={columns}
          data={tickets}
          rowKey={(t) => t.id}
          loading={isLoading}
          loadingRows={6}
          error={error}
          onRetry={refetch}
          emptyMessage="No support tickets found."
          actions={(t) => (
            <Button variant="ghost" size="sm" onClick={() => setViewingId(t.id)}>
              View
            </Button>
          )}
        />
        {meta && (
          <Pagination
            page={page}
            lastPage={lastPage}
            onPageChange={setPage}
            label={`Page ${meta.current_page} of ${meta.last_page}`}
          />
        )}
      </div>
      <SupportTicketDetailModal ticketId={viewingId} onClose={() => setViewingId(null)} />
    </div>
  );
}
