import { useState } from "react";
import {
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  Clock,
  Send,
  UserMinus,
} from "lucide-react";
import { useSupportTicket, useSupportAgents, useUpdateTicketStatus, useAssignTicket, useUnassignTicket, useAddInternalNote } from "../hooks";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

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

const STATUSES = ["open", "in_progress", "waiting_on_customer", "resolved", "closed"];

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between py-2">
      <span className="text-sm text-[var(--muted)]">{label}</span>
      <span className="text-sm font-medium text-right">{value ?? "\u2014"}</span>
    </div>
  );
}

function SLABanner({ ticket }: { ticket: { is_sla_breached: boolean; sla_due_at: string | null } }) {
  if (ticket.is_sla_breached) {
    return (
      <div className="flex items-center gap-2 rounded-[var(--radius)] bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-400">
        <AlertTriangle className="h-4 w-4 shrink-0" />
        <span className="font-medium">SLA breached</span>
        {ticket.sla_due_at && (
          <span className="text-red-600/70 dark:text-red-400/70">
            &mdash; due {formatDate(ticket.sla_due_at)}
          </span>
        )}
      </div>
    );
  }

  if (ticket.sla_due_at) {
    const due = new Date(ticket.sla_due_at);
    const now = new Date();
    const hoursLeft = (due.getTime() - now.getTime()) / (1000 * 60 * 60);
    if (hoursLeft <= 4 && hoursLeft > 0) {
      return (
        <div className="flex items-center gap-2 rounded-[var(--radius)] bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-400">
          <Clock className="h-4 w-4 shrink-0" />
          <span className="font-medium">SLA approaching</span>
          <span className="text-amber-600/70 dark:text-amber-40/70">
            &mdash; due {formatDate(ticket.sla_due_at)}
          </span>
        </div>
      );
    }
  }

  return null;
}

interface Props {
  ticketId: string | null;
  onClose: () => void;
}

export function SupportTicketDetailModal({ ticketId, onClose }: Props) {
  const [noteText, setNoteText] = useState("");
  const [showInternalNotes, setShowInternalNotes] = useState(false);

  const ticketQuery = useSupportTicket(ticketId);
  const agentsQuery = useSupportAgents();
  const updateStatus = useUpdateTicketStatus();
  const assignTicket = useAssignTicket();
  const unassignTicket = useUnassignTicket();
  const addNote = useAddInternalNote();

  if (!ticketId) return null;

  const ticket = ticketQuery.data?.data;
  const agents = agentsQuery.data?.data ?? [];

  const handleStatusChange = (newStatus: string) => {
    if (!ticket) return;
    updateStatus.mutate(
      { id: ticket.id, status: newStatus },
      {
        onSuccess: () => {
          ticketQuery.refetch();
        },
      },
    );
  };

  const handleAssign = (agentId: string) => {
    if (!ticket || !agentId) return;
    assignTicket.mutate(
      { id: ticket.id, assignedTo: agentId },
      {
        onSuccess: () => {
          ticketQuery.refetch();
        },
      },
    );
  };

  const handleUnassign = () => {
    if (!ticket) return;
    unassignTicket.mutate(ticket.id, {
      onSuccess: () => {
        ticketQuery.refetch();
      },
    });
  };

  const handleAddNote = () => {
    if (!ticket || !noteText.trim()) return;
    addNote.mutate(
      { id: ticket.id, message: noteText.trim() },
      {
        onSuccess: () => {
          setNoteText("");
          ticketQuery.refetch();
        },
      },
    );
  };

  const replies = ticket?.replies ?? [];
  const internalNotes = replies.filter((r) => r.is_internal);
  const externalReplies = replies.filter((r) => !r.is_internal);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative flex h-[85vh] w-full max-w-5xl flex-col rounded-[var(--radius)] bg-[var(--surface)] shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--border)] px-6 py-4">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-3">
              <h2 className="truncate text-base font-semibold">
                {ticket?.subject ?? (ticketQuery.isLoading ? "Loading…" : "Ticket")}
              </h2>
              {ticket && (
                <>
                  <Badge tone={STATUS_TONES[ticket.status] ?? "default"}>
                    {STATUS_LABELS[ticket.status] ?? ticket.status}
                  </Badge>
                  <Badge tone={PRIORITY_TONES[ticket.priority] ?? "default"}>
                    {PRIORITY_LABELS[ticket.priority] ?? ticket.priority}
                  </Badge>
                </>
              )}
            </div>
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <span className="sr-only">Close</span>
            <span className="text-lg leading-none">&times;</span>
          </Button>
        </div>

        {/* SLA Banner */}
        {ticket && (
          <div className="px-6 pt-4">
            <SLABanner ticket={ticket} />
          </div>
        )}

        {/* Body */}
        <div className="flex min-h-0 flex-1 overflow-hidden">
          {ticketQuery.isLoading ? (
            <div className="flex-1 p-6">
              <div className="space-y-3">
                {Array.from({ length: 8 }).map((_, i) => (
                  <div key={i} className="h-8 animate-pulse rounded bg-[var(--muted)]/10" />
                ))}
              </div>
            </div>
          ) : ticketQuery.isError ? (
            <div className="flex flex-1 items-center justify-center p-6">
              <p className="text-sm text-[var(--color-danger)]">Failed to load ticket details.</p>
            </div>
          ) : ticket ? (
            <>
              {/* Left Panel — Details */}
              <div className="w-72 shrink-0 overflow-y-auto border-r border-[var(--border)] p-4">
                <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--muted)]">
                  Details
                </h3>
                <div className="divide-y divide-[var(--border)]">
                  <Row
                    label="Customer"
                    value={
                      ticket.user ? (
                        <span>
                          {ticket.user.name}
                          {ticket.user.email && (
                            <span className="block text-xs text-[var(--muted)]">{ticket.user.email}</span>
                          )}
                        </span>
                      ) : (
                        "\u2014"
                      )
                    }
                  />
                  <Row label="Category" value={CATEGORY_LABELS[ticket.category] ?? ticket.category} />
                  <Row
                    label="Status"
                    value={
                      <Select
                        className="w-36"
                        value={ticket.status}
                        onChange={(e) => handleStatusChange(e.target.value)}
                        disabled={updateStatus.isPending}
                      >
                        {STATUSES.map((s) => (
                          <option key={s} value={s}>
                            {STATUS_LABELS[s] ?? s}
                          </option>
                        ))}
                      </Select>
                    }
                  />
                  <Row
                    label="Priority"
                    value={
                      <Badge tone={PRIORITY_TONES[ticket.priority] ?? "default"}>
                        {PRIORITY_LABELS[ticket.priority] ?? ticket.priority}
                      </Badge>
                    }
                  />
                  <Row
                    label="Assigned To"
                    value={
                      ticket.assigned_to ? (
                        <div className="flex items-center gap-1">
                          <span>{ticket.assigned_to.name}</span>
                          <button
                            onClick={handleUnassign}
                            disabled={unassignTicket.isPending}
                            className="ml-1 text-[var(--muted)] hover:text-[var(--color-danger)]"
                            title="Unassign"
                          >
                            <UserMinus className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      ) : (
                        <Select
                          className="w-36"
                          value=""
                          onChange={(e) => handleAssign(e.target.value)}
                          disabled={assignTicket.isPending}
                        >
                          <option value="">Assign…</option>
                          {agents.map((a) => (
                            <option key={a.id} value={a.id}>
                              {a.name}
                            </option>
                          ))}
                        </Select>
                      )
                    }
                  />
                  <Row
                    label="SLA Due"
                    value={
                      ticket.sla_due_at ? (
                        <span className="flex items-center gap-1">
                          {ticket.is_sla_breached && <AlertTriangle className="h-3 w-3 text-red-500" />}
                          {formatDate(ticket.sla_due_at)}
                        </span>
                      ) : (
                        "\u2014"
                      )
                    }
                  />
                  <Row
                    label="Created"
                    value={formatDate(ticket.created_at)}
                  />
                  <Row
                    label="Last Reply"
                    value={
                      ticket.last_reply_at ? (
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatDate(ticket.last_reply_at)}
                        </span>
                      ) : (
                        "\u2014"
                      )
                    }
                  />
                </div>
              </div>

              {/* Right Panel — Thread */}
              <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="flex-1 overflow-y-auto p-4">
                  {/* Original Message */}
                  <div className="mb-4 rounded-[var(--radius)] border border-[var(--border)] p-4">
                    <div className="mb-2 flex items-center gap-2">
                      <span className="text-sm font-medium">{ticket.user?.name ?? "Customer"}</span>
                      <span className="text-xs text-[var(--muted)]">{formatDate(ticket.created_at)}</span>
                    </div>
                    <p className="whitespace-pre-wrap text-sm">{ticket.message}</p>
                  </div>

                  {/* External Replies */}
                  {externalReplies.length > 0 && (
                    <div className="mb-4 space-y-3">
                      {externalReplies.map((reply) => (
                        <div
                          key={reply.id}
                          className="rounded-[var(--radius)] border border-[var(--border)] p-4"
                        >
                          <div className="mb-2 flex items-center gap-2">
                            <span className="text-sm font-medium">
                              {reply.user?.name ?? "Agent"}
                            </span>
                            <Badge tone="info">Agent</Badge>
                            <span className="text-xs text-[var(--muted)]">
                              {formatDate(reply.created_at)}
                            </span>
                          </div>
                          <p className="whitespace-pre-wrap text-sm">{reply.message}</p>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Internal Notes Toggle */}
                  {internalNotes.length > 0 && (
                    <div className="mb-4">
                      <button
                        onClick={() => setShowInternalNotes(!showInternalNotes)}
                        className="flex w-full items-center gap-2 rounded-[var(--radius)] border border-amber-500/30 bg-amber-500/5 px-4 py-2.5 text-sm font-medium text-amber-600 dark:text-amber-400"
                      >
                        {showInternalNotes ? (
                          <ChevronUp className="h-4 w-4" />
                        ) : (
                          <ChevronDown className="h-4 w-4" />
                        )}
                        Internal Notes ({internalNotes.length})
                      </button>
                      {showInternalNotes && (
                        <div className="mt-2 space-y-2">
                          {internalNotes.map((note) => (
                            <div
                              key={note.id}
                              className="rounded-[var(--radius)] border border-amber-500/20 bg-amber-500/10 p-4"
                            >
                              <div className="mb-2 flex items-center gap-2">
                                <span className="text-sm font-medium">
                                  {note.user?.name ?? "Agent"}
                                </span>
                                <Badge tone="warning">Internal</Badge>
                                <span className="text-xs text-[var(--muted)]">
                                  {formatDate(note.created_at)}
                                </span>
                              </div>
                              <p className="whitespace-pre-wrap text-sm">{note.message}</p>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}

                  {externalReplies.length === 0 && internalNotes.length === 0 && (
                    <p className="py-8 text-center text-sm text-[var(--muted)]">
                      No replies yet.
                    </p>
                  )}
                </div>

                {/* Reply / Note Form */}
                <div className="border-t border-[var(--border)] p-4">
                  <Textarea
                    placeholder="Write a reply or internal note…"
                    value={noteText}
                    onChange={(e) => setNoteText(e.target.value)}
                    rows={3}
                  />
                  <div className="mt-2 flex items-center justify-between">
                    <p className="text-xs text-[var(--muted)]">
                      Replies are visible to the customer. Use &quot;Add Note&quot; for internal-only comments.
                    </p>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={!noteText.trim() || addNote.isPending}
                        onClick={handleAddNote}
                      >
                        Add Note
                      </Button>
                      <Button
                        size="sm"
                        disabled={!noteText.trim() || addNote.isPending}
                        onClick={handleAddNote}
                      >
                        <Send className="mr-1 h-3.5 w-3.5" />
                        Reply
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </>
          ) : (
            <div className="flex flex-1 items-center justify-center p-6">
              <p className="text-sm text-[var(--muted)]">No data.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
