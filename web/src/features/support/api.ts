import { api, type Paginated } from "@/lib/api";
import type { SupportTicket, TicketReply } from "../super-admin/types";

export async function getMyTickets(params?: Record<string, string | number | undefined>) {
  const searchParams = new URLSearchParams();
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== "") {
        searchParams.set(key, String(value));
      }
    });
  }
  const qs = searchParams.toString();
  return api<Paginated<SupportTicket>>(`/support/tickets${qs ? `?${qs}` : ""}`);
}

export async function getMyTicket(id: string) {
  return api<{ data: SupportTicket }>(`/support/tickets/${id}`);
}

export async function createTicket(data: {
  subject: string;
  message: string;
  category?: string;
  priority?: string;
}) {
  return api<{ data: SupportTicket }>("/support/tickets", {
    method: "POST",
    body: data,
  });
}

export async function replyToTicket(id: string, message: string) {
  return api<{ data: TicketReply }>(`/support/tickets/${id}/replies`, {
    method: "POST",
    body: { message },
  });
}

export async function closeTicket(id: string) {
  return api<{ data: SupportTicket }>(`/support/tickets/${id}/close`, {
    method: "PUT",
  });
}

export async function reopenTicket(id: string) {
  return api<{ data: SupportTicket }>(`/support/tickets/${id}/reopen`, {
    method: "PUT",
  });
}
