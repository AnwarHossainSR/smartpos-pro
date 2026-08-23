import { useCallback, useEffect, useRef, useState } from "react";
import {
  ArrowLeft,
  Clock,
  MessageSquare,
  Send,
  Headset,
} from "lucide-react";
import {
  useMyTickets,
  useMyTicket,
  useCreateTicket,
  useReplyToTicket,
  useCloseTicket,
  useReopenTicket,
} from "@/features/support/hooks";
import type { SupportTicket } from "@/features/super-admin/types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Pagination } from "@/components/ui/pagination";
import { ListToolbar } from "@/components/ui/list-toolbar";
import { Textarea } from "@/components/ui/textarea";
import { Dialog } from "@/components/ui/dialog";

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
  waiting_on_customer: "Waiting on Customer",
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

const CATEGORY_LABELS: Record<string, string> = {
  technical: "Technical Issue",
  billing: "Billing",
  feature_request: "Feature Request",
  bug_report: "Bug Report",
  general: "General Inquiry",
  account: "Account",
  other: "Other",
};

function formatRelativeTime(dateStr: string | null): string {
  if (!dateStr) return "\u2014";
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

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function LoadingSkeleton() {
  return (
    <div className="space-y-4">
      <ListToolbar
        search=""
        onSearchChange={() => {}}
        searchPlaceholder="Search tickets\u2026"
        filters={
          <div className="flex gap-2">
            <Skeleton className="h-10 w-40" />
          </div>
        }
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        <div className="space-y-4 p-4">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-14 w-full" />
          ))}
        </div>
      </div>
    </div>
  );
}

function EmptyState({ onNewTicket }: { onNewTicket: () => void }) {
  return (
    <div className="flex flex-col items-center gap-3 py-12 text-center">
      <Headset className="h-12 w-12 text-[var(--muted)]" />
      <p className="text-sm font-medium">No tickets yet</p>
      <p className="text-sm text-[var(--muted)]">
        Submit a support ticket and we&apos;ll get back to you.
      </p>
      <Button onClick={onNewTicket}>Create a Ticket</Button>
    </div>
  );
}

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      onClick={onClick}
      className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
        active
          ? "border-[var(--primary)] text-[var(--primary)]"
          : "border-transparent text-[var(--muted)] hover:text-[var(--foreground)]"
      }`}
    >
      {children}
    </button>
  );
}

function TicketList({
  onNewTicket,
  onViewTicket,
}: {
  onNewTicket: () => void;
  onViewTicket: (ticket: SupportTicket) => void;
}) {
  const [searchInput, setSearchInput] = useState("");
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState("");

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
    status: statusFilter || undefined,
  };

  const { data, isLoading, error, refetch } = useMyTickets(params);

  const tickets = data?.data ?? [];
  const meta = data?.meta;
  const lastPage = meta?.last_page ?? 1;

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
  }, []);

  if (isLoading) return <LoadingSkeleton />;

  if (tickets.length === 0 && !debouncedSearch && !statusFilter) {
    return (
      <div className="space-y-4">
        <ListToolbar
          search={searchInput}
          onSearchChange={handleSearchChange}
          searchPlaceholder="Search tickets\u2026"
          filters={
            <Select
              className="w-40"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All statuses</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting_on_customer">Waiting</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </Select>
          }
          addLabel="New Ticket"
          onAdd={onNewTicket}
        />
        <EmptyState onNewTicket={onNewTicket} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <ListToolbar
        search={searchInput}
        onSearchChange={handleSearchChange}
        searchPlaceholder="Search tickets\u2026"
        filters={
          <Select
            className="w-40"
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All statuses</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting_on_customer">Waiting</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </Select>
        }
        addLabel="New Ticket"
        onAdd={onNewTicket}
      />
      <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]">
        {error ? (
          <div className="flex flex-col items-center gap-3 py-12 text-center">
            <p className="text-sm text-[var(--color-danger)]">Failed to load tickets.</p>
            <Button variant="outline" size="sm" onClick={() => refetch()}>
              Retry
            </Button>
          </div>
        ) : tickets.length === 0 ? (
          <div className="py-12 text-center text-sm text-[var(--muted)]">
            No tickets found.
          </div>
        ) : (
          <div className="divide-y divide-[var(--border)]">
            {tickets.map((ticket) => (
              <button
                key={ticket.id}
                onClick={() => onViewTicket(ticket)}
                className="flex w-full items-start gap-4 p-4 text-left transition-colors hover:bg-[var(--surface-2)]"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <span className="truncate text-sm font-medium">
                      {ticket.subject}
                    </span>
                    <Badge tone={STATUS_TONES[ticket.status] ?? "default"}>
                      {STATUS_LABELS[ticket.status] ?? ticket.status}
                    </Badge>
                    <Badge tone={PRIORITY_TONES[ticket.priority] ?? "default"}>
                      {PRIORITY_LABELS[ticket.priority] ?? ticket.priority}
                    </Badge>
                  </div>
                  <div className="mt-1 flex items-center gap-3 text-xs text-[var(--muted)]">
                    <span className="flex items-center gap-1">
                      <Clock className="h-3 w-3" />
                      {formatRelativeTime(ticket.last_reply_at ?? ticket.created_at)}
                    </span>
                    <span className="flex items-center gap-1">
                      <MessageSquare className="h-3 w-3" />
                      {ticket.replies_count} {ticket.replies_count === 1 ? "reply" : "replies"}
                    </span>
                    {ticket.category && (
                      <span>{CATEGORY_LABELS[ticket.category] ?? ticket.category}</span>
                    )}
                  </div>
                </div>
                <ArrowLeft className="h-4 w-4 shrink-0 rotate-180 text-[var(--muted)]" />
              </button>
            ))}
          </div>
        )}
        {meta && (
          <Pagination
            page={page}
            lastPage={lastPage}
            onPageChange={setPage}
            label={`Page ${meta.current_page} of ${meta.last_page}`}
          />
        )}
      </div>
    </div>
  );
}

function NewTicketForm({ onCreated }: { onCreated: () => void }) {
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [category, setCategory] = useState("");
  const [priority, setPriority] = useState("");

  const createTicket = useCreateTicket();

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!subject.trim() || !message.trim()) return;
    createTicket.mutate(
      {
        subject: subject.trim(),
        message: message.trim(),
        category: category || undefined,
        priority: priority || undefined,
      },
      {
        onSuccess: () => {
          setSubject("");
          setMessage("");
          setCategory("");
          setPriority("");
          onCreated();
        },
      },
    );
  }

  return (
    <Card>
      <CardContent className="pt-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="subject">Subject *</Label>
            <Input
              id="subject"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              placeholder="Brief description of your issue"
              className="mt-1.5"
              required
            />
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="category">Category</Label>
              <Select
                id="category"
                className="mt-1.5"
                value={category}
                onChange={(e) => setCategory(e.target.value)}
              >
                <option value="">Select category</option>
                <option value="technical">Technical Issue</option>
                <option value="billing">Billing</option>
                <option value="feature_request">Feature Request</option>
                <option value="bug_report">Bug Report</option>
                <option value="general">General Inquiry</option>
                <option value="account">Account</option>
                <option value="other">Other</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="priority">Priority</Label>
              <Select
                id="priority"
                className="mt-1.5"
                value={priority}
                onChange={(e) => setPriority(e.target.value)}
              >
                <option value="">Select priority</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </Select>
            </div>
          </div>
          <div>
            <Label htmlFor="message">Message *</Label>
            <Textarea
              id="message"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Describe your issue in detail\u2026"
              rows={6}
              className="mt-1.5"
              required
            />
          </div>
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={createTicket.isPending || !subject.trim() || !message.trim()}
            >
              {createTicket.isPending ? (
                "Submitting\u2026"
              ) : (
                <>
                  <Send className="mr-2 h-4 w-4" />
                  Submit Ticket
                </>
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

function TicketDetail({
  ticketId,
  onBack,
}: {
  ticketId: string;
  onBack: () => void;
}) {
  const [replyText, setReplyText] = useState("");
  const [closingTicket, setClosingTicket] = useState<SupportTicket | null>(null);

  const ticketQuery = useMyTicket(ticketId);
  const replyMutation = useReplyToTicket();
  const closeMutation = useCloseTicket();
  const reopenMutation = useReopenTicket();

  const ticket = ticketQuery.data?.data;

  function handleReply() {
    if (!ticket || !replyText.trim()) return;
    replyMutation.mutate(
      { id: ticket.id, message: replyText.trim() },
      {
        onSuccess: () => {
          setReplyText("");
          ticketQuery.refetch();
        },
      },
    );
  }

  function handleClose() {
    if (!closingTicket) return;
    closeMutation.mutate(closingTicket.id, {
      onSuccess: () => {
        setClosingTicket(null);
        ticketQuery.refetch();
      },
    });
  }

  function handleReopen() {
    if (!ticket) return;
    reopenMutation.mutate(ticket.id, {
      onSuccess: () => {
        ticketQuery.refetch();
      },
    });
  }

  if (ticketQuery.isLoading) {
    return (
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Skeleton className="h-8 w-8" />
          <Skeleton className="h-6 w-48" />
        </div>
        <Skeleton className="h-12 w-full" />
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-20 w-full" />
          ))}
        </div>
      </div>
    );
  }

  if (ticketQuery.isError || !ticket) {
    return (
      <div className="space-y-4">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back
        </Button>
        <div className="flex flex-col items-center gap-3 py-12 text-center">
          <p className="text-sm text-[var(--color-danger)]">Failed to load ticket.</p>
          <Button variant="outline" size="sm" onClick={() => ticketQuery.refetch()}>
            Retry
          </Button>
        </div>
      </div>
    );
  }

  const replies = (ticket.replies ?? []).filter((r) => !r.is_internal);
  const isOpen = ticket.status !== "closed" && ticket.status !== "resolved";

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back
        </Button>
      </div>

      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <h2 className="text-base font-semibold">{ticket.subject}</h2>
          <div className="mt-1 flex items-center gap-2">
            <Badge tone={STATUS_TONES[ticket.status] ?? "default"}>
              {STATUS_LABELS[ticket.status] ?? ticket.status}
            </Badge>
            <Badge tone={PRIORITY_TONES[ticket.priority] ?? "default"}>
              {PRIORITY_LABELS[ticket.priority] ?? ticket.priority}
            </Badge>
            {ticket.category && (
              <span className="text-xs text-[var(--muted)]">
                {CATEGORY_LABELS[ticket.category] ?? ticket.category}
              </span>
            )}
          </div>
        </div>
        <div className="flex shrink-0 gap-2">
          {isOpen ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setClosingTicket(ticket)}
            >
              Close Ticket
            </Button>
          ) : (
            <Button variant="outline" size="sm" onClick={handleReopen}>
              Reopen Ticket
            </Button>
          )}
        </div>
      </div>

      <div className="space-y-3">
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
          <div className="mb-2 flex items-center gap-2">
            <span className="text-sm font-medium">{ticket.user?.name ?? "You"}</span>
            <span className="text-xs text-[var(--muted)]">
              {formatDate(ticket.created_at)}
            </span>
          </div>
          <p className="whitespace-pre-wrap text-sm">{ticket.message}</p>
        </div>

        {replies.map((reply) => (
          <div
            key={reply.id}
            className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4"
          >
            <div className="mb-2 flex items-center gap-2">
              <span className="text-sm font-medium">
                {reply.user?.name ?? "Support"}
              </span>
              <Badge tone="info">Support</Badge>
              <span className="text-xs text-[var(--muted)]">
                {formatDate(reply.created_at)}
              </span>
            </div>
            <p className="whitespace-pre-wrap text-sm">{reply.message}</p>
          </div>
        ))}

        {replies.length === 0 && (
          <p className="py-8 text-center text-sm text-[var(--muted)]">
            No replies yet.
          </p>
        )}
      </div>

      {isOpen && (
        <div className="rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-4">
          <Textarea
            placeholder="Write a reply\u2026"
            value={replyText}
            onChange={(e) => setReplyText(e.target.value)}
            rows={3}
          />
          <div className="mt-2 flex justify-end">
            <Button
              size="sm"
              disabled={!replyText.trim() || replyMutation.isPending}
              onClick={handleReply}
            >
              <Send className="mr-1 h-3.5 w-3.5" />
              Reply
            </Button>
          </div>
        </div>
      )}

      <Dialog
        open={!!closingTicket}
        onClose={() => setClosingTicket(null)}
        title="Close ticket"
        description="Are you sure you want to close this ticket?"
        confirmLabel="Close"
        confirmVariant="primary"
        onConfirm={handleClose}
        loading={closeMutation.isPending}
      />
    </div>
  );
}

export default function SupportPage() {
  const [activeTab, setActiveTab] = useState<"tickets" | "new">("tickets");
  const [viewingTicketId, setViewingTicketId] = useState<string | null>(null);

  if (viewingTicketId) {
    return (
      <div className="max-w-5xl">
        <TicketDetail
          ticketId={viewingTicketId}
          onBack={() => setViewingTicketId(null)}
        />
      </div>
    );
  }

  return (
    <div className="max-w-5xl space-y-4">
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--primary)]/10">
          <Headset className="h-5 w-5 text-[var(--primary)]" />
        </div>
        <p className="text-sm text-[var(--muted)]">Get help with your SmartPOS Pro account</p>
      </div>

      <div className="flex border-b border-[var(--border)]">
        <TabButton
          active={activeTab === "tickets"}
          onClick={() => setActiveTab("tickets")}
        >
          My Tickets
        </TabButton>
        <TabButton
          active={activeTab === "new"}
          onClick={() => setActiveTab("new")}
        >
          New Ticket
        </TabButton>
      </div>

      {activeTab === "tickets" && (
        <TicketList
          onNewTicket={() => setActiveTab("new")}
          onViewTicket={(ticket) => setViewingTicketId(ticket.id)}
        />
      )}

      {activeTab === "new" && (
        <NewTicketForm
          onCreated={() => setActiveTab("tickets")}
        />
      )}
    </div>
  );
}
